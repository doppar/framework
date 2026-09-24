<?php

namespace Phaseolies\Support\Facades;

use Phaseolies\Facade\BaseFacade;

/**
 * @method static mixed get(string $key, $default = null)
 * @method static mixed pull(string $key, $default = null)
 * @method static mixed getPeek(string $key, $default = null)
 * @method static void putPeek(string $key, $value)
 * @method static void flushPeek()
 * @method static void put(string|array $key, $value = null)
 * @method static bool has(string $key)
 * @method static void forget(string $key)
 * @method static void flush()
 * @method static void regenerate(bool $deleteOldSession = true)
 * @method static array all()
 * @method static string getId()
 * @method static void setId(string $id)
 * @method static void destroy()
 * @method static ?string token()
 * @method static void flash(string $key, $value)
 * @method static void reflash($keys)
 * @method static void invalidate()
 * @method static void regenerateToken()
 *
 * @see \Phaseolies\Support\Session
 */
class Session extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'session';
    }
}
