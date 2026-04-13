<?php

namespace Phaseolies\Database\Entity\Casts;

use Phaseolies\Database\Entity\Casts\Contracts\CastableInterface;
use Phaseolies\Database\Entity\Casts\Handlers\ArrayCast;
use Phaseolies\Database\Entity\Casts\Handlers\BooleanCast;
use Phaseolies\Database\Entity\Casts\Handlers\CollectionCast;
use Phaseolies\Database\Entity\Casts\Handlers\DateCast;
use Phaseolies\Database\Entity\Casts\Handlers\DateTimeCast;
use Phaseolies\Database\Entity\Casts\Handlers\DecimalCast;
use Phaseolies\Database\Entity\Casts\Handlers\EnumCast;
use Phaseolies\Database\Entity\Casts\Handlers\FloatCast;
use Phaseolies\Database\Entity\Casts\Handlers\IntegerCast;
use Phaseolies\Database\Entity\Casts\Handlers\ObjectCast;
use Phaseolies\Database\Entity\Casts\Handlers\StringCast;
use Phaseolies\Database\Entity\Casts\Handlers\TimestampCast;

class CastManager
{
    /**
     * Map of primitive cast type strings to handler classes.
     *
     * @var array<string, class-string<CastableInterface>>
     */
    private static array $primitives = [
        'int'        => IntegerCast::class,
        'integer'    => IntegerCast::class,
        'float'      => FloatCast::class,
        'double'     => FloatCast::class,
        'real'       => FloatCast::class,
        'bool'       => BooleanCast::class,
        'boolean'    => BooleanCast::class,
        'string'     => StringCast::class,
        'array'      => ArrayCast::class,
        'json'       => ArrayCast::class,
        'object'     => ObjectCast::class,
        'collection' => CollectionCast::class,
        'datetime'   => DateTimeCast::class,
        'date'       => DateCast::class,
        'timestamp'  => TimestampCast::class,
    ];

    /**
     * Cache of resolved cast handler instances keyed by cast string.
     *
     * @var array<string, CastableInterface>
     */
    private static array $resolved = [];

    /**
     * Cast a raw database value to its PHP type on get.
     *
     * @param string $cast
     * @param mixed  $value
     * @return mixed
     */
    public static function get(string $cast, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return self::resolve($cast)->get($value);
    }

    /**
     * Cast a PHP value to a database-compatible format on set.
     *
     * @param string $cast
     * @param mixed  $value
     * @return mixed
     */
    public static function set(string $cast, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return self::resolve($cast)->set($value);
    }

    /**
     * Resolve the cast handler instance for a given cast string
     *
     * @param string $cast
     * @return CastableInterface
     * @throws \InvalidArgumentException
     */
    public static function resolve(string $cast): CastableInterface
    {
        if (isset(self::$resolved[$cast])) {
            return self::$resolved[$cast];
        }

        // Primitive cast — 'integer', 'float', 'boolean', etc.
        if (isset(self::$primitives[$cast])) {
            return self::$resolved[$cast] = new (self::$primitives[$cast])();
        }

        // Parameterised decimal cast — 'decimal:2', 'decimal:4'
        if (str_starts_with($cast, 'decimal:')) {
            $precision = (int) explode(':', $cast, 2)[1];
            return self::$resolved[$cast] = new DecimalCast($precision);
        }

        // Parameterised datetime cast — 'datetime:d/m/Y'
        if (str_starts_with($cast, 'datetime:')) {
            $format = explode(':', $cast, 2)[1];
            return self::$resolved[$cast] = new DateTimeCast($format);
        }

        // Enum cast — any PHP backed or unit enum
        if (enum_exists($cast)) {
            return self::$resolved[$cast] = new EnumCast($cast);
        }

        // Custom cast class — must implement CastableInterface
        if (class_exists($cast) && is_subclass_of($cast, CastableInterface::class)) {
            return self::$resolved[$cast] = new $cast();
        }

        throw new \InvalidArgumentException(
            "Unsupported cast type [{$cast}]. Use a built-in type, a backed enum, or a class implementing CastableInterface."
        );
    }

    /**
     * Flush the resolved cast handler cache.
     *
     * @return void
     */
    public static function flush(): void
    {
        self::$resolved = [];
    }

    /**
     * Determine if a cast string is a complex type that requires
     * set-casting before writing to the database.
     *
     * @param string $cast
     * @return bool
     */
    public static function requiresSetCast(string $cast): bool
    {
        $complex = ['array', 'json', 'object', 'collection', 'datetime', 'date', 'timestamp'];

        if (in_array($cast, $complex, true)) {
            return true;
        }

        if (str_starts_with($cast, 'decimal:') || str_starts_with($cast, 'datetime:')) {
            return true;
        }

        if (enum_exists($cast)) {
            return true;
        }

        if (class_exists($cast) && is_subclass_of($cast, CastableInterface::class)) {
            return true;
        }

        return false;
    }
}
