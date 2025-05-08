<?php

namespace Phaseolies\Support\Facades;

/**
 * @method static \Phaseolies\Config\Config set(string $key, mixed $value): void
 * @method static \Phaseolies\Config\Config get(string $key, mixed $default = null): mixed
 * @method static \Phaseolies\Config\Config all(): array
 * @method static \Phaseolies\Config\Config clearCache(): void
 * @see \Phaseolies\Config\Config
 */
use Phaseolies\Facade\BaseFacade;

class Config extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'config';
    }
}
