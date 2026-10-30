<?php

namespace Phaseolies\Support\Facades;

use Phaseolies\Facade\BaseFacade;

/**
 * @method static \Phaseolies\Support\Storage\IFileSystem disk(string $name)
 * @method static ?string getDiskPath(string $disk)
 *
 * @see \Phaseolies\Support\Storage\Contracts\StorageInterface
 */
class Storage extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'storage';
    }
}
