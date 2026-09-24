<?php

namespace Phaseolies\Support\Facades;

use Phaseolies\Facade\BaseFacade;

/**
 * @method static string encrypt(mixed $payload)
 * @method static mixed decrypt(string $payload)
 *
 * @see \Phaseolies\Support\Encryption
 */
class Crypt extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'crypt';
    }
}
