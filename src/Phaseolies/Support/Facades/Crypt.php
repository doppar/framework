<?php

namespace Phaseolies\Support\Facades;

/**
 * @method static \Phaseolies\Support\Encryption encrypt(mixed $payload): string
 * @method static \Phaseolies\Support\Encryption decrypt(string $payload): string
 * @see \Phaseolies\Support\Encryption
 */
use Phaseolies\Facade\BaseFacade;

class Crypt extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'crypt';
    }
}
