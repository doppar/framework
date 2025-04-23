<?php

namespace Phaseolies\Support\Facades;

/**
 * @method static \Phaseolies\Support\Storage\StorageFileService disk(?string $name = null)
 * @method static \Phaseolies\Support\Storage\StorageFileService getDiskPath(string $disk)
 * @see \Phaseolies\Support\Storage\StorageFileService
 */

use Phaseolies\Facade\BaseFacade;

class Storage extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'storage';
    }
}
