<?php

namespace Phaseolies\Database\Entity\Computed;

use Phaseolies\Database\Entity\Attributes\Computed;

trait InteractsWithComputedProperties
{
    /**
     * Per-class cache of method names decorated with #[Computed].
     *
     * @var array<string, list<string>>
     */
    private static array $computedAttributeCache = [];

    /**
     * Ensure computed method names are scanned and cached for this class.
     *
     * @return void
     */
    private function ensureComputedCached(): void
    {
        $class = static::class;

        if (!array_key_exists($class, self::$computedAttributeCache)) {
            self::$computedAttributeCache[$class] = self::scanComputedAttributes($class);
        }
    }

    /**
     * Scan the class for public methods decorated with #[Computed]
     *
     * @param string $class
     * @return list<array{method: string, key: string}>
     */
    private static function scanComputedAttributes(string $class): array
    {
        $found      = [];
        $reflection = new \ReflectionClass($class);

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if (!empty($method->getAttributes(Computed::class))) {
                $methodName = $method->getName();
                $found[]    = [
                    'method' => $methodName,
                    'key'    => str()->snake($methodName),
                ];
            }
        }

        return $found;
    }

    /**
     * Determine whether the given name maps to a #[Computed] method
     *
     * @param string $name
     * @return bool
     */
    public function isComputed(string $name): bool
    {
        $this->ensureComputedCached();

        foreach (self::$computedAttributeCache[static::class] as $entry) {
            if ($entry['key'] === $name || $entry['method'] === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * Call the computed method that corresponds to the given name
     *
     * @param string $name
     * @return mixed
     */
    public function resolveComputed(string $name): mixed
    {
        $this->ensureComputedCached();

        foreach (self::$computedAttributeCache[static::class] as $entry) {
            if ($entry['key'] === $name || $entry['method'] === $name) {
                return $this->{$entry['method']}();
            }
        }

        return null;
    }

    /**
     * Resolve all computed properties to a snake_case key => value map.
     *
     * @return array<string, mixed>
     */
    public function getComputedAttributes(): array
    {
        $this->ensureComputedCached();

        $result = [];

        foreach (self::$computedAttributeCache[static::class] as $entry) {
            if (!in_array($entry['key'], $this->unexposable, true) &&
                !in_array($entry['method'], $this->unexposable, true)) {
                $result[$entry['key']] = $this->{$entry['method']}();
            }
        }

        return $result;
    }

    /**
     * Reset the #[Computed] scan cache for one or all model classes.
     *
     * @param string|null $class
     * @return void
     */
    public static function resetComputedCache(?string $class = null): void
    {
        if ($class !== null) {
            unset(self::$computedAttributeCache[$class]);
        } else {
            self::$computedAttributeCache = [];
        }
    }
}
