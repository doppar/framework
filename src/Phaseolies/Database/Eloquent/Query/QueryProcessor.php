<?php

namespace Phaseolies\Database\Eloquent\Query;

use PDO;
use Generator;
use RuntimeException;
use Phaseolies\Support\Collection;

trait QueryProcessor
{
    /**
     * Process records in chunks to reduce memory usage for large datasets.
     *
     * @param int $chunkSize
     * @param callable $processor
     * @param int|null $total Provide total records for progress tracking
     * @return void
     */
    public function chunk($chunkSize, callable $processor, ?int $total = null): void
    {
        $offset = 0;
        $processed = $chunkSize;

        while (true) {
            $chunkQuery = clone $this;
            $results = $chunkQuery->limit($chunkSize)
                ->offset($offset)
                ->get();

            if (!count($results)) {
                break;
            }

            $processor($results, $processed, $total);

            $processed += $results->count();
            $offset += $chunkSize;

            // prevent memory leaks
            // unsetting and invoking garbage collection
            unset($chunkQuery, $results);
            if (gc_enabled()) {
                gc_collect_cycles();
            }
        }
    }

    /**
     * Process records using a cursor for maximum memory efficiency
     *
     * @param callable $processor A function to process each record
     * @param int|null $total Provide total records for progress tracking
     * @return void
     */
    public function cursor(callable $processor, ?int $total = null): void
    {
        $processed = 1;
        $sql = $this->toSql();

        try {
            $stmt = $this->pdo->prepare($sql);
            $this->bindValues($stmt);
            $stmt->execute();

            $stmt->setFetchMode(PDO::FETCH_ASSOC);

            while ($row = $stmt->fetch()) {
                $model = new $this->modelClass($row);
                $processor($model, $processed, $total);
                $processed++;

                unset($model, $row);
                if (gc_enabled()) {
                    gc_collect_cycles();
                }
            }
        } catch (\PDOException $e) {
            throw new RuntimeException("Database error during cursor operation: " . $e->getMessage());
        } finally {
            if (isset($stmt) && $stmt instanceof \PDOStatement) {
                $stmt->closeCursor();
            }
        }
    }

    /**
     * Generator-based approach for memory-efficient iteration over large datasets.
     *
     * @param int $chunkSize Number of records to fetch per iteration.
     * @param callable|null $transform Optional callback to transform each model before yielding.
     * @return Generator
     */
    public function stream($chunkSize, ?callable $transform = null): Generator
    {
        $offset = 0;

        while (true) {
            $chunkQuery = clone $this;
            $results = $chunkQuery->limit($chunkSize)
                ->offset($offset)
                ->get();

            if (!count($results)) {
                break;
            }

            foreach ($results as $model) {
                yield $transform ? $transform($model) : $model;
            }

            $offset += $chunkSize;
            unset($chunkQuery, $results);
            if (gc_enabled()) {
                gc_collect_cycles();
            }
        }
    }

    /**
     * Process records with batch operations for efficiency
     * 
     * @param int chunkSize
     * @param callable $batchProcessor
     * @param int $batchSize
     * @return void
     */
    public function batch(int $chunkSize, callable $batchProcessor, int $batchSize = 1000): void
    {
        $batch = [];
        $offset = 0;

        while (true) {
            $chunkQuery = clone $this;
            $results = $chunkQuery->limit($chunkSize)
                ->offset($offset)
                ->get();

            // If no more results, flush any remaining batch and exit
            if (!count($results)) {
                if (!empty($batch)) {
                    $batchProcessor(new Collection($this->modelClass, $batch));
                }
                break;
            }

            foreach ($results as $model) {
                $batch[] = $model;

                // If batch limit is reached, process and reset
                if (count($batch) >= $batchSize) {
                    $batchProcessor(new Collection($this->modelClass, $batch));
                    $batch = [];
                }
            }

            $offset += $chunkSize;
            unset($chunkQuery, $results);
            if (gc_enabled()) {
                gc_collect_cycles();
            }
        }
    }

    /**
     * Parallel chunk processing using Fibers
     * 
     * @param int $chunkSize Records per chunk
     * @param callable $processor Callback to process chunks
     * @param int $concurrency Number of parallel fibers
     * @return void
     */
    public function fchunk(int $chunkSize, callable $processor, int $concurrency = 4): void
    {
        $offset = 0;
        $fibers = [];
        $running = true;

        while ($running) {
            while (count($fibers) < $concurrency) {
                $fiberOffset = $offset;
                $fiber = new \Fiber(function () use ($fiberOffset, $chunkSize, $processor) {
                    $chunkQuery = clone $this;
                    $results = $chunkQuery->limit($chunkSize)
                        ->offset($fiberOffset)
                        ->get();

                    if (!count($results)) {
                        // Signal completion
                        return false;
                    }

                    $processor($results, $fiberOffset + $results->count());
                    // More data available
                    return true;
                });

                $fibers[] = $fiber;
                $fiber->start();
                $offset += $chunkSize;
            }

            // Check fiber status
            $activeFibers = [];
            foreach ($fibers as $fiber) {
                if ($fiber->isTerminated()) {
                    if ($fiber->getReturn() === false) {
                        $running = false;
                        break;
                    }
                } else {
                    $activeFibers[] = $fiber;
                }
            }

            $fibers = $activeFibers;

            // Clean up memory
            unset($chunkQuery, $results);
            if (gc_enabled()) {
                gc_collect_cycles();
            }

            // Exit if no more data
            if (!$running) {
                $running = false;
            }
        }
    }

    /**
     * Fiber-based streaming with backpressure control
     * 
     * @param int $chunkSize Records per chunk
     * @param callable|null $transform Optional record transformer
     * @param int $bufferSize Maximum items to buffer
     * @return Generator
     */
    public function fstream(int $chunkSize, ?callable $transform = null, int $bufferSize = 1000): Generator
    {
        $offset = 0;
        $buffer = [];
        $fiber = null;

        while (true) {
            // Create a new fiber if none exists or previous completed
            if (!$fiber || $fiber->isTerminated()) {
                $currentOffset = $offset;
                $fiber = new \Fiber(function () use ($currentOffset, $chunkSize, $transform) {
                    $chunkQuery = clone $this;
                    $results = $chunkQuery->limit($chunkSize)
                        ->offset($currentOffset)
                        ->get();

                    if (!count($results)) {
                        return false; // No more data
                    }

                    foreach ($results as $model) {
                        \Fiber::suspend($transform ? $transform($model) : $model);
                    }

                    return true; // More data available
                });

                $fiber->start();
                $offset += $chunkSize;
            }

            // Get next item from fiber
            if (!$fiber->isTerminated()) {
                $buffer[] = $fiber->resume();
            }

            // Yield buffered items when buffer is full or fiber completed
            if (count($buffer) >= $bufferSize || $fiber->isTerminated()) {
                foreach ($buffer as $item) {
                    yield $item;
                }
                $buffer = [];
            }

            // Exit if no more data
            if ($fiber->isTerminated() && $fiber->getReturn() === false) {
                break;
            }

            // Clean up memory
            unset($chunkQuery, $results);
            if (gc_enabled()) {
                gc_collect_cycles();
            }
        }
    }

    /**
     * Hybrid fiber/cursor processing for maximum efficiency
     *
     * @param callable $processor Record processor
     * @param int $bufferSize Number of records to buffer
     * @return void
     */
    public function fcursor(callable $processor, int $bufferSize = 1000): void
    {
        $buffer = [];
        $sql = $this->toSql();

        try {
            $stmt = $this->pdo->prepare($sql);
            $this->bindValues($stmt);
            $stmt->execute();
            $stmt->setFetchMode(PDO::FETCH_ASSOC);

            $fiber = new \Fiber(function () use ($stmt, &$buffer, $bufferSize) {
                while ($row = $stmt->fetch()) {
                    $model = new $this->modelClass($row);
                    $buffer[] = $model;

                    if (count($buffer) >= $bufferSize) {
                        \Fiber::suspend($buffer);
                        $buffer = [];
                    }

                    unset($model, $row);
                }

                return $buffer; // Return remaining items
            });

            $fiber->start();

            while (!$fiber->isTerminated()) {
                $chunk = $fiber->resume();
                foreach ($chunk as $model) {
                    $processor($model);
                }
            }

            // Process remaining items
            $remaining = $fiber->getReturn();
            foreach ($remaining as $model) {
                $processor($model);
            }
        } catch (\PDOException $e) {
            throw new RuntimeException("Database error during fiber cursor operation: " . $e->getMessage());
        } finally {
            if (isset($stmt) && $stmt instanceof \PDOStatement) {
                $stmt->closeCursor();
            }
        }
    }
}
