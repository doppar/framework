<?php

namespace Phaseolies\Signals;

use Phaseolies\Database\Database;
use Phaseolies\Signals\Attributes\DeliverOnSettle;
use Phaseolies\Signals\Contracts\SignalBusInterface;

class SignalBus implements SignalBusInterface
{
    /**
     * Registered listeners, keyed by signal class or interface name
     *
     * @var array<string, array<int, array{priority: int, order: int, listener: mixed}>>
     */
    protected array $listeners = [];

    /**
     * Ordered listener lists already resolved for a concrete signal class
     *
     * @var array<string, array<int, mixed>>
     */
    protected array $resolved = [];

    /**
     * Cached #[DeliverOnSettle] lookups, false when a class does not opt in
     *
     * @var array<string, DeliverOnSettle|false>
     */
    protected array $settleRules = [];

    /**
     * Registration counter that keeps equal priorities in registration order
     *
     * @var int
     */
    protected int $order = 0;

    /**
     * Custom reporter for listener failures that happen after a commit
     *
     * @var callable|null
     */
    protected $failureReporter = null;

    /**
     * Register a listener for a signal class or interface.
     *
     * @param string $signal
     * @param callable|array|string $listener
     * @param int $priority
     * @return self
     * @throws \InvalidArgumentException
     */
    public function on(string $signal, callable|array|string $listener, int $priority = 0): self
    {
        $this->assertListener($listener);

        $this->listeners[ltrim($signal, '\\')][] = [
            'priority' => $priority,
            'order' => $this->order++,
            'listener' => $listener,
        ];

        $this->resolved = [];

        return $this;
    }

    /**
     * Remove every listener of a signal, or all listeners when none is given.
     *
     * @param string|null $signal
     * @return self
     */
    public function forget(?string $signal = null): self
    {
        if ($signal === null) {
            $this->listeners = [];
        } else {
            unset($this->listeners[ltrim($signal, '\\')]);
        }

        $this->resolved = [];

        return $this;
    }

    /**
     * Determine if any listener would receive the given signal.
     *
     * @param object|string $signal
     * @return bool
     */
    public function hasListeners(object|string $signal): bool
    {
        return $this->listenersFor(is_object($signal) ? $signal::class : ltrim($signal, '\\')) !== [];
    }

    /**
     * Emit a signal.
     *
     * @param object $signal
     * @return void
     */
    public function emit(object $signal): void
    {
        $rule = $this->settleRule($signal::class);

        if ($rule !== false && Database::stageSignal($this, $signal, $rule->connection)) {
            return;
        }

        $this->deliver($signal);
    }

    /**
     * Emit a signal right away, ignoring #[DeliverOnSettle] and any open transaction.
     *
     * @param object $signal
     * @return void
     */
    public function emitNow(object $signal): void
    {
        $this->deliver($signal);
    }

    /**
     * Emit a signal once the open transaction on a connection settles.
     *
     * @param object $signal
     * @param string|null $connection
     * @return bool
     */
    public function emitOnSettle(object $signal, ?string $connection = null): bool
    {
        if (Database::stageSignal($this, $signal, $connection)) {
            return true;
        }

        $this->deliver($signal);

        return false;
    }

    /**
     * Deliver a signal whose transaction has just committed.
     *
     * @param object $signal
     * @return void
     * @internal Called by the database layer
     */
    public function settle(object $signal): void
    {
        foreach ($this->listenersFor($signal::class) as $listener) {
            try {
                $this->invoke($listener, $signal);
            } catch (\Throwable $failure) {
                $this->reportFailure($failure, $signal);
            }
        }
    }

    /**
     * Set a custom reporter for listener failures that happen after a commit.
     *
     * @param callable(\Throwable, object): void|null $reporter
     * @return self
     */
    public function reportFailuresUsing(?callable $reporter): self
    {
        $this->failureReporter = $reporter;

        return $this;
    }

    /**
     * Run every listener for a signal, letting the first exception propagate.
     *
     * @param object $signal
     * @return void
     */
    protected function deliver(object $signal): void
    {
        foreach ($this->listenersFor($signal::class) as $listener) {
            $this->invoke($listener, $signal);
        }
    }

    /**
     * Get the ordered listeners for a concrete signal class.
     *
     * @param string $class
     * @return array<int, mixed>
     */
    protected function listenersFor(string $class): array
    {
        if (isset($this->resolved[$class])) {
            return $this->resolved[$class];
        }

        $entries = [];

        foreach ([$class, ...array_values(class_parents($class) ?: []), ...array_values(class_implements($class) ?: [])] as $name) {
            foreach ($this->listeners[$name] ?? [] as $entry) {
                $entries[] = $entry;
            }
        }

        usort($entries, static fn(array $a, array $b): int => [$b['priority'], $a['order']] <=> [$a['priority'], $b['order']]);

        return $this->resolved[$class] = array_column($entries, 'listener');
    }

    /**
     * Read the #[DeliverOnSettle] rule of a signal class once.
     *
     * @param string $class
     * @return DeliverOnSettle|false
     */
    protected function settleRule(string $class): DeliverOnSettle|false
    {
        if (!isset($this->settleRules[$class])) {
            $attributes = (new \ReflectionClass($class))->getAttributes(DeliverOnSettle::class);

            $this->settleRules[$class] = $attributes ? $attributes[0]->newInstance() : false;
        }

        return $this->settleRules[$class];
    }

    /**
     * Call a single listener with the signal.
     *
     * @param mixed $listener
     * @param object $signal
     * @return void
     */
    protected function invoke(mixed $listener, object $signal): void
    {
        if (is_string($listener) && !is_callable($listener)) {
            $listener = app()->make($listener);
        } elseif (is_array($listener) && is_string($listener[0])) {
            $listener = [app()->make($listener[0]), $listener[1]];
        }

        $listener($signal);
    }

    /**
     * Make sure a listener can be called later.
     *
     * @param mixed $listener
     * @return void
     * @throws \InvalidArgumentException
     */
    protected function assertListener(mixed $listener): void
    {
        $valid = match (true) {
            is_string($listener) => is_callable($listener) || (class_exists($listener) && method_exists($listener, '__invoke')),
            is_array($listener) => count($listener) === 2
                && (is_object($listener[0]) || (is_string($listener[0]) && class_exists($listener[0])))
                && is_string($listener[1])
                && method_exists($listener[0], $listener[1]),
            default => is_callable($listener),
        };

        if (!$valid) {
            throw new \InvalidArgumentException(
                'A signal listener must be a callable, a [class, method] pair or an invokable class name.'
            );
        }
    }

    /**
     * Report a listener failure without ever throwing.
     *
     * @param \Throwable $failure
     * @param object $signal
     * @return void
     */
    protected function reportFailure(\Throwable $failure, object $signal): void
    {
        $reported = false;

        try {
            if ($this->failureReporter !== null) {
                ($this->failureReporter)($failure, $signal);
                $reported = true;
            } elseif (app()->has('log')) {
                app('log')->error(sprintf('Signal listener failed for %s: %s', $signal::class, $failure->getMessage()), [
                    'exception' => $failure,
                    'signal' => $signal::class,
                ]);
                $reported = true;
            }
        } catch (\Throwable) {
            // Reporting must never break the remaining listeners.
        }

        if (!$reported) {
            try {
                error_log(sprintf(
                    'Signal listener failed for %s: %s: %s in %s:%d',
                    $signal::class,
                    get_class($failure),
                    $failure->getMessage(),
                    $failure->getFile(),
                    $failure->getLine()
                ));
            } catch (\Throwable) {
                // Nothing left to try.
            }
        }
    }
}
