<?php

namespace Phaseolies\Support\Facades;

use Phaseolies\Facade\BaseFacade;

/**
 * @method static string encrypt(mixed $payload)
 * @method static mixed decrypt(string $payload)
 *
 * @see \Phaseolies\Support\Contracts\EncryptionInterface
 */
class Crypt extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'crypt';
    }
}
