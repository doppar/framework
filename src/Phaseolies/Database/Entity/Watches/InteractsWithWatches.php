<?php

namespace Phaseolies\Database\Entity\Watches;

use Phaseolies\Database\Entity\Attributes\Watches;

trait InteractsWithWatches
{
    /**
     * Per-class cache of scanned #[Watches] metadata so reflection only runs once.
     *
     * @var array<string, list<array{property: string, watcher: string, when: string|null}>>
     */
    private static array $watchesAttributeCache = [];

    /**
     * Scan #[Watches] attributes on the model's properties and register them with WatchesHandler.
     *
     * @return void
     */
    protected function registerWatchesAttributes(): void
    {
        $class = static::class;

        if (!array_key_exists($class, self::$watchesAttributeCache)) {
            self::$watchesAttributeCache[$class] = self::scanWatchesAttributes($class);
        }

        foreach (self::$watchesAttributeCache[$class] as $entry) {
            WatchesHandler::register(
                $class,
                $entry['property'],
                $entry['watcher'],
                $entry['when']
            );
        }
    }

    /**
     * Fire all registered property watches for the given set of dirty attributes.
     *
     * @param array $dirty
     * @return void
     */
    public function firePropertyWatches(array $dirty): void
    {
        if (empty($dirty)) {
            return;
        }

        WatchesHandler::fireForDirty($this, $dirty);
    }

    /**
     * Reset the #[Watches] reflection cache for one or all model classes
     *
     * @param string|null $class
     * @return void
     */
    public static function resetWatchesCache(?string $class = null): void
    {
        if ($class !== null) {
            unset(self::$watchesAttributeCache[$class]);
        } else {
            self::$watchesAttributeCache = [];
        }
    }

    /**
     * Use reflection to collect all #[Watches] metadata from the model's properties
     *
     * @param  string $class
     * @return list<array{property: string, watcher: string, when: string|null}>
     */
    private static function scanWatchesAttributes(string $class): array
    {
        $found      = [];
        $reflection = new \ReflectionClass($class);

        foreach ($reflection->getProperties() as $property) {
            $watchAttrs = $property->getAttributes(Watches::class);

            if (empty($watchAttrs)) {
                continue;
            }

            foreach ($watchAttrs as $watchAttr) {
                /** @var Watches $watches */
                $watches = $watchAttr->newInstance();

                $found[] = [
                    'property' => $property->getName(),
                    'watcher' => $watches->watcher,
                    'when'     => $watches->when,
                ];
            }
        }

        return $found;
    }
}
