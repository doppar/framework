<?php

namespace Phaseolies\Support\Facades;

/**
 * @method static \Phaseolies\Auth\Security\PasswordHashing make(string $plainText)
 * @method static \Phaseolies\Auth\Security\PasswordHashing check(string $plainText, string $hashedText)
 * @method static \Phaseolies\Auth\Security\PasswordHashing needsRehash(string $password)
 * @see \Phaseolies\Auth\Security\PasswordHashing
 */

use Phaseolies\Facade\BaseFacade;

class Hash extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'hash';
    }
}
