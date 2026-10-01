<?php

namespace Phaseolies\Database\Entity\Query;

use PDO;
use Generator;
use RuntimeException;
use InvalidArgumentException;
use Phaseolies\Support\Collection;

trait InteractsWithBigDataProcessing
{
    /**
     * Process records in chunks to reduce memory usage for large datasets.
     *
     * @param int $chunkSize
     * @param callable $processor
     * @param int|null $total
     * @return void
     */
    public function chunk(int $chunkSize, callable $processor, ?int $total = null): void
    {
        $processed = 0;

        foreach ($this->pages($chunkSize) as $results) {
            $processed += $results->count();

            $processor($results, $processed, $total);

            unset($results);
        }
    }

    /**
     * Process records in chunks using keyset pagination (WHERE id > last).
     *
     * @param int $chunkSize
     * @param callable $processor
     * @param string|null $column
     * @param int|null $total
     * @return void
     */
    public function chunkById(int $chunkSize, callable $processor, ?string $column = null, ?int $total = null): void
    {
        $this->assertChunkSize($chunkSize);

        $column ??= app($this->modelClass)->getKeyName();
        $lastId = null;
        $processed = 0;

        while (true) {
            $query = clone $this;
            $query->orderBy = [];
            $query->offset = null;

            if ($lastId !== null) {
                $query->where($column, '>', $lastId);
            }

            $results = $query->orderBy($column)->limit($chunkSize)->get();
            $count = $results->count();

            if ($count === 0) {
                break;
            }

            $items = $results->all();
            $lastId = end($items)->{$column};
            unset($items);

            $processed += $count;

            $processor($results, $processed, $total);

            unset($query, $results);

            if ($count < $chunkSize) {
                break;
            }
        }
    }

    /**
     * Process records one at a time using a forward-only PDO cursor.
     *
     * @param callable $processor
     * @param int|null $total
     * @param bool $unbuffered
     * @return void
     */
    public function cursor(callable $processor, ?int $total = null, bool $unbuffered = false): void
    {
        $processed = 0;

        foreach ($this->openCursor($unbuffered) as $model) {
            $processed++;

            $processor($model, $processed, $total);

            unset($model);
        }
    }

    /**
     * Generator-based approach for memory-efficient iteration over large datasets.
     *
     * @param int $chunkSize
     * @param callable|null $transform
     * @return Generator
     */
    public function stream(int $chunkSize, ?callable $transform = null): Generator
    {
        foreach ($this->pages($chunkSize) as $results) {
            foreach ($results as $model) {
                yield $transform ? $transform($model) : $model;
            }

            unset($results);
        }
    }

    /**
     * Process records with batch operations for efficiency
     *
     * @param int $chunkSize
     * @param callable $batchProcessor
     * @param int $batchSize
     * @return void
     */
    public function batch(int $chunkSize, callable $batchProcessor, int $batchSize = 1000): void
    {
        if ($batchSize < 1) {
            throw new InvalidArgumentException('Batch size must be greater than zero.');
        }

        $batch = [];

        foreach ($this->pages($chunkSize) as $results) {
            foreach ($results as $model) {
                $batch[] = $model;

                if (count($batch) >= $batchSize) {
                    $batchProcessor(new Collection($this->modelClass, $batch));
                    $batch = [];
                }
            }

            unset($results);
        }

        if (!empty($batch)) {
            $batchProcessor(new Collection($this->modelClass, $batch));
        }
    }

    /**
     * Yield pages of results, honouring any limit/offset already set on the query.
     *
     * @param int $chunkSize
     * @return Generator<Collection>
     */
    private function pages(int $chunkSize): Generator
    {
        $this->assertChunkSize($chunkSize);

        $offset = $this->offset ?? 0;
        $remaining = $this->limit;

        $base = clone $this;

        // Without a deterministic order OFFSET pages can overlap or skip rows
        if (empty($base->orderBy) && empty($base->groupBy)) {
            $base->orderBy(app($this->modelClass)->getKeyName());
        }

        while ($remaining === null || $remaining > 0) {
            $size = $remaining === null ? $chunkSize : min($chunkSize, $remaining);

            $results = (clone $base)->limit($size)->offset($offset)->get();
            $count = $results->count();

            if ($count === 0) {
                return;
            }

            yield $results;

            // A short page means there is nothing left, so skip the empty query
            if ($count < $size) {
                return;
            }

            $offset += $count;

            if ($remaining !== null) {
                $remaining -= $count;
            }
        }
    }

    /**
     * Stream hydrated models from a PDO cursor.
     *
     * @param bool $unbuffered
     * @return Generator
     */
    private function openCursor(bool $unbuffered): Generator
    {
        $restore = null;
        $attribute = match (true) {
            defined('Pdo\Mysql::ATTR_USE_BUFFERED_QUERY') => constant('Pdo\Mysql::ATTR_USE_BUFFERED_QUERY'),
            defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY') => constant('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY'),
            default => null,
        };

        try {
            if (
                $unbuffered
                && $attribute !== null
                && $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ) {
                $restore = (bool) $this->pdo->getAttribute($attribute);
                $this->pdo->setAttribute($attribute, false);
            }

            yield from $this->fetchLazy();
        } catch (\PDOException $e) {
            throw new RuntimeException(
                "Database error during cursor operation: " . $e->getMessage(),
                0,
                $e
            );
        } finally {
            if ($restore !== null) {
                $this->pdo->setAttribute($attribute, $restore);
            }
        }
    }

    /**
     * Ensure the chunk size is usable.
     *
     * @param int $chunkSize
     * @return void
     */
    private function assertChunkSize(int $chunkSize): void
    {
        if ($chunkSize < 1) {
            throw new InvalidArgumentException('Chunk size must be greater than zero.');
        }
    }
}
