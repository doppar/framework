<?php

namespace Phaseolies\Database\Entity\Casts;

use Phaseolies\Database\Entity\Casts\Attributes\Transform;

trait InteractsWithCasting
{
    /**
     * Internal model state properties that must never be used as cast targets.
     *
     * @var array<int, string>
     */
    private const RESERVED_CAST_PROPERTIES = [
        'attributes',
        'originalAttributes',
        'relations',
        'lastRelationType',
        'lastRelatedModel',
        'lastForeignKey',
        'lastLocalKey',
        'lastRelatedKey',
        'lastPivotTable',
        'forceDeleting',
    ];

    /**
     * Per-class cache of #[Cast] attribute metadata scanned via reflection.
     *
     * @var array<string, array<string, string>>
     */
    private static array $castAttributeCache = [];

    /**
     * Per-class list of cast properties to unset on every instance
     *
     * @var array<string, array<class-string, array<int, string>>>
     */
    private static array $detachableCastPropertyCache = [];

    /**
     * Unset the cast properties declared `mixed` or without a type.
     *
     * @return void
     */
    protected function detachCastProperties(): void
    {
        $class = static::class;

        if (!array_key_exists($class, self::$detachableCastPropertyCache)) {
            self::$detachableCastPropertyCache[$class] = $this->scanDetachableCastProperties($class);
        }

        foreach (self::$detachableCastPropertyCache[$class] as $declaringClass => $properties) {
            \Closure::bind(function () use ($properties): void {
                foreach ($properties as $property) {
                    unset($this->{$property});
                }
            }, $this, $declaringClass)();
        }
    }

    /**
     * Find the cast properties that can be unset, grouped by declaring class.
     *
     * @param string $class
     * @return array<class-string, array<int, string>>
     */
    private function scanDetachableCastProperties(string $class): array
    {
        $this->ensureCastsCached();

        $detachable = [];
        $reflection = new \ReflectionClass($class);

        foreach (array_keys(self::$castAttributeCache[$class]) as $name) {
            $property = $reflection->getProperty($name);
            $type = $property->getType();

            if ($property->isStatic() || $property->isReadOnly()) {
                continue;
            }

            if ($this->isModelSetting($name) || ($property->hasDefaultValue() && $property->getDefaultValue() !== null)) {
                continue;
            }

            if ($type === null || ($type instanceof \ReflectionNamedType && $type->getName() === 'mixed')) {
                $detachable[$property->getDeclaringClass()->getName()][] = $name;
            }
        }

        return $detachable;
    }

    /**
     * Whether a property is one of the base model's own settings.
     *
     * @param string $name
     * @return bool
     */
    private function isModelSetting(string $name): bool
    {
        return property_exists(\Phaseolies\Database\Entity\Model::class, $name);
    }

    /**
     * Ensure cast metadata is scanned for the current class.
     *
     * @return void
     */
    private function ensureCastsCached(): void
    {
        $class = static::class;

        if (!array_key_exists($class, self::$castAttributeCache)) {
            self::$castAttributeCache[$class] = $this->scanCastAttributes($class);
        }
    }

    /**
     * Scan a class for properties decorated with #[Cast].
     *
     * @param string $class
     * @return array<string, string>
     */
    private function scanCastAttributes(string $class): array
    {
        $found      = [];
        $reflection = new \ReflectionClass($class);

        foreach ($reflection->getProperties() as $property) {
            $castAttributes = $property->getAttributes(Transform::class, \ReflectionAttribute::IS_INSTANCEOF);

            if (empty($castAttributes)) {
                continue;
            }

            if (in_array($property->getName(), self::RESERVED_CAST_PROPERTIES, true)) {
                throw new \LogicException(sprintf(
                    'Invalid cast declaration on %s::$%s. This is a reserved internal model property and cannot be used with cast attributes. Use a real model field name or the $casts array for your column instead.',
                    $class,
                    $property->getName()
                ));
            }

            $cast                        = $castAttributes[0]->newInstance();
            $found[$property->getName()] = $cast->type;
        }

        return $found;
    }

    /**
     * Apply the get cast — converts a raw DB value to its PHP representation.
     *
     * @param string $key
     * @param mixed  $value
     * @return mixed
     */
    protected function castForGet(string $key, mixed $value): mixed
    {
        if ($value === null || !$this->hasCast($key)) {
            return $value;
        }

        return CastManager::get(self::$castAttributeCache[static::class][$key], $value);
    }

    /**
     * Apply the set cast — converts a PHP value to a DB-compatible format
     *
     * @param string $key
     * @param mixed  $value
     * @return mixed
     */
    protected function castForSet(string $key, mixed $value): mixed
    {
        if ($value === null || !$this->hasCast($key)) {
            return $value;
        }

        $cast = self::$castAttributeCache[static::class][$key];

        if (!CastManager::requiresSetCast($cast)) {
            return $value;
        }

        return CastManager::set($cast, $value);
    }

    /**
     * Determine if an attribute has a #[Cast] definition.
     *
     * @param string $key
     * @return bool
     */
    public function hasCast(string $key): bool
    {
        $this->ensureCastsCached();

        return isset(self::$castAttributeCache[static::class][$key]);
    }

    /**
     * Get the cast type for a given attribute.
     *
     * @param string $key
     * @return string|null
     */
    public function getCastType(string $key): ?string
    {
        $this->ensureCastsCached();

        return self::$castAttributeCache[static::class][$key] ?? null;
    }

    /**
     * Get all cast definitions.
     *
     * @return array<string, string>
     */
    public function getCasts(): array
    {
        $this->ensureCastsCached();

        return self::$castAttributeCache[static::class] ?? [];
    }

    /**
     * Get all model attributes with casts applied
     *
     * @return array
     */
    public function getCastAttributes(): array
    {
        $this->ensureCastsCached();

        $result = [];

        foreach ($this->attributes as $key => $value) {
            $result[$key] = $this->hasCast($key)
                ? $this->castForGet($key, $value)
                : $value;
        }

        return $result;
    }

    /**
     * Reset the #[Cast] attribute scan cache.
     *
     * @param string|null $class
     * @return void
     */
    public static function resetCastCache(?string $class = null): void
    {
        if ($class !== null) {
            unset(self::$castAttributeCache[$class], self::$detachableCastPropertyCache[$class]);
        } else {
            self::$castAttributeCache = [];
            self::$detachableCastPropertyCache = [];
        }
    }
}
