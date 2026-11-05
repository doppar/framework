<?php

namespace Phaseolies\Database\Entity\SoftDeletes;

use Phaseolies\Database\Entity\Attributes\SoftDeletes;

class SoftDeleteManager
{
    /**
     * Resolved soft delete column per model class, null when the model
     * does not carry #[SoftDeletes].
     *
     * @var array<class-string, string|null>
     */
    private static array $columns = [];

    /**
     * Determine whether the given model class is soft-deletable.
     *
     * @param string $class
     * @return bool
     */
    public static function isSoftDeletable(string $class): bool
    {
        return self::column($class) !== null;
    }

    /**
     * Get the soft delete column for the given model class.
     *
     * The attribute is read once per class and cached; parent classes
     * are honoured so a soft-deletable base model passes it down.
     *
     * @param string $class
     * @return string|null
     */
    public static function column(string $class): ?string
    {
        if (array_key_exists($class, self::$columns)) {
            return self::$columns[$class];
        }

        $column = null;

        if (!class_exists($class)) {
            return self::$columns[$class] = null;
        }

        $reflection = new \ReflectionClass($class);

        do {
            $attributes = $reflection->getAttributes(SoftDeletes::class);

            if (!empty($attributes)) {
                $column = $attributes[0]->newInstance()->column;
                break;
            }
        } while ($reflection = $reflection->getParentClass());

        return self::$columns[$class] = $column;
    }

    /**
     * Reset the internal attribute cache. Useful in tests.
     *
     * @param string|null $class
     * @return void
     */
    public static function resetCache(?string $class = null): void
    {
        if ($class !== null) {
            unset(self::$columns[$class]);
        } else {
            self::$columns = [];
        }
    }
}
