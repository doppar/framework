<?php

namespace Phaseolies\Database\Entity\Watches;

use Phaseolies\Database\Entity\Model;

class WatchesHandler
{
    /**
     * Registry of all property watches organized by model class and property name.
     *
     * @var array<string, array<string, list<array{watcher: string, when: string|null}>>>
     */
    public static array $watches = [];

    /**
     * Register a watch entry for a specific model property.
     *
     * @param string $modelClass
     * @param string $property
     * @param string $watcher 
     * @param string|null $when
     * @return void
     */
    public static function register(
        string $modelClass,
        string $property,
        string $watcher,
        ?string $when = null
    ): void {
        $entry = ['watcher' => $watcher, 'when' => $when];

        if (!self::watchExists($modelClass, $property, $entry)) {
            self::$watches[$modelClass][$property][] = $entry;
        }
    }

    /**
     * Fire all registered watches for every dirty attribute on the given model
     *
     * @param Model $model
     * @param array $dirty
     * @return void
     */
    public static function fireForDirty(Model $model, array $dirty): void
    {
        $modelClass = get_class($model);

        if (empty(self::$watches[$modelClass])) {
            return;
        }

        $originals = $model->getOriginalAttributes();

        foreach ($dirty as $property => $newValue) {
            $entries = self::$watches[$modelClass][$property] ?? [];

            if (empty($entries)) {
                continue;
            }

            $oldValue = $originals[$property] ?? null;

            foreach ($entries as $entry) {
                if (!self::conditionPasses($entry['when'], $oldValue, $newValue, $model)) {
                    continue;
                }

                self::callListener($entry['watcher'], $oldValue, $newValue, $model);
            }
        }
    }

    /**
     * Clear the registry for one or all model classes (useful in tests).
     *
     * @param string|null $modelClass
     * @return void
     */
    public static function flush(?string $modelClass = null): void
    {
        if ($modelClass !== null) {
            unset(self::$watches[$modelClass]);
        } else {
            self::$watches = [];
        }
    }

    /**
     * Evaluate the optional condition attached to a watch entry.
     *
     * @param string|null $when
     * @param mixed $old
     * @param mixed $new
     * @param Model $model
     * @return bool
     */
    protected static function conditionPasses(?string $when, mixed $old, mixed $new, Model $model): bool
    {
        if ($when === null) {
            return true;
        }

        // Class-based condition (implements WatchConditionInterface)
        if (class_exists($when)) {
            $condition = app($when);

            if (!($condition instanceof WatchConditionInterface)) {
                throw new \RuntimeException(
                    "Watch condition class '{$when}' must implement "
                        . WatchConditionInterface::class
                );
            }

            $result = $condition->evaluate($old, $new, $model);

            if (!is_bool($result)) {
                throw new \RuntimeException(
                    "WatchConditionInterface::evaluate() must return bool, got " . gettype($result)
                );
            }

            return $result;
        }

        // Method-based condition (method on the model itself)
        if (!method_exists($model, $when)) {
            throw new \RuntimeException(
                "Watch condition method '{$when}' does not exist on " . get_class($model)
            );
        }

        $result = $model->$when($old, $new);

        if (!is_bool($result)) {
            throw new \RuntimeException(
                "Watch condition method '{$when}' must return bool, got " . gettype($result)
            );
        }

        return $result;
    }

    /**
     * Resolve and invoke the watcher's handle() method.
     *
     * @param string $watcherClass
     * @param mixed $old
     * @param mixed $new
     * @param Model $model
     * @return void
     */
    protected static function callListener(
        string $watcherClass,
        mixed $old,
        mixed $new,
        Model $model
    ): void {
        $watcher = app($watcherClass);

        if (!method_exists($watcher, 'handle')) {
            throw new \RuntimeException(
                "Watch watcher '{$watcherClass}' must expose a handle(mixed \$old, mixed \$new, Model \$model): void method."
            );
        }

        $watcher->handle($old, $new, $model);
    }

    /**
     * Check whether an identical watch entry is already registered.
     *
     * @param string $modelClass
     * @param string $property
     * @param array  $entry
     * @return bool
     */
    protected static function watchExists(string $modelClass, string $property, array $entry): bool
    {
        foreach (self::$watches[$modelClass][$property] ?? [] as $existing) {
            if ($existing['watcher'] === $entry['watcher'] && $existing['when'] === $entry['when']) {
                return true;
            }
        }

        return false;
    }
}
