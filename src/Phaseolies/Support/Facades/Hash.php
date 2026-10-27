<?php

namespace Phaseolies\Support\Facades;

use Phaseolies\Facade\BaseFacade;

/**
 * @method static string make(string $plainText)
 * @method static bool check(string $plainText, string $hashedText)
 * @method static bool needsRehash(string $password)
 *
 * @see \Phaseolies\Auth\Security\Contracts\HashingInterface
 */
class Hash extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'hash';
    }
}
