<?php

namespace Phaseolies\Database\Eloquent\Hooks;

use Phaseolies\Database\Eloquent\Model;

class HookHandler
{
    /**
     * @var array Registered hooks
     */
    public static $hooks = [];

    /**
     * Register hooks for a model
     *
     * @param string $modelClass
     * @param array $hooks
     */
    public static function register(string $modelClass, array $hooks): void
    {
        foreach ($hooks as $event => $handler) {
            $normalized = self::normalizeHandler($handler);
            if (!self::hookExists($modelClass, $event, $normalized)) {
                self::$hooks[$modelClass][$event][] = $normalized;
            }
        }
    }

    /**
     * Check hook exists or not
     *
     * @param string $modelClass
     * @param string $event
     * @param array $newHook
     * @return bool
     */
    protected static function hookExists(string $modelClass, string $event, array $newHook): bool
    {
        if (!isset(self::$hooks[$modelClass][$event])) {
            return false;
        }

        foreach (self::$hooks[$modelClass][$event] as $existingHook) {
            if (self::hooksEqual($existingHook, $newHook)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determines if two hook configurations are identical
     *
     * @param array $hook1
     * @param array $hook2
     * @return bool
     */
    protected static function hooksEqual(array $hook1, array $hook2): bool
    {
        if ($hook1['handler'] !== $hook2['handler']) {
            return false;
        }

        // Compare callable conditions
        if (is_callable($hook1['when']) && is_callable($hook2['when'])) {
            return $hook1['when'] == $hook2['when'];
        }

        return $hook1['when'] === $hook2['when'];
    }

    /**
     * Normalize handler configuration
     *
     * @param mixed $handler
     * @return array
     */
    protected static function normalizeHandler($handler): array
    {
        if (is_callable($handler)) {
            return [
                'handler' => $handler,
                'when' => true
            ];
        }

        if (is_string($handler)) {
            return [
                'handler' => $handler,
                'when' => true
            ];
        }

        if (is_array($handler)) {
            return [
                'handler' => $handler['handler'] ?? null,
                'when' => $handler['when'] ?? true
            ];
        }

        throw new \InvalidArgumentException('Invalid hook handler format');
    }

    /**
     * Execute hooks for a given event
     *
     * @param string $event
     * @param Model $model
     * @return void
     */
    public static function execute(string $event, Model $model): void
    {
        $modelClass = get_class($model);
        $hooks = self::$hooks[$modelClass][$event] ?? [];

        if (str_starts_with($event, 'before_')) {
            $model->setOriginalAttributes($model->getAttributes());
        }

        foreach ($hooks as $hook) {
            if (!self::shouldExecute($model, $hook['when'] ?? true)) {
                continue;
            }

            self::executeHandler($hook['handler'], $model);
        }
    }

    /**
     * Check if a hook should be executed
     *
     * @param Model $model
     * @param callable|bool $condition
     * @return bool
     */
    protected static function shouldExecute(Model $model, callable|bool $condition = true): bool
    {
        if (is_bool($condition)) {
            return $condition;
        }

        $result = $condition($model);

        if (!is_bool($result)) {
            throw new \RuntimeException(
                "Hook condition must return boolean, got " . gettype($result)
            );
        }

        return $result;
    }

    /**
     * Execute a single handler
     *
     * @param callable|string $handler
     * @param Model $model
     * @return void
     */
    protected static function executeHandler($handler, Model $model): void
    {
        if (is_callable($handler)) {
            $handler($model);
            return;
        }

        if (is_string($handler)) {
            $instance = app($handler);
            if (method_exists($instance, 'handle')) {
                $instance->handle($model);
                return;
            }
        }

        throw new \RuntimeException("Invalid hook handler for model " . get_class($model));
    }

    /**
     * Check if a hook should be executed
     *
     * @param Model $model
     * @param callable|bool $condition
     * @return bool
     */
    public static function shouldExecuteForUnitTest(Model $model, callable|bool $condition = true): bool
    {
        return self::shouldExecute($model, $condition);
    }
}
