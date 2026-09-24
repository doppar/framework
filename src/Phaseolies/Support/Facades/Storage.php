<?php

namespace Phaseolies\Support\Facades;

use Phaseolies\Facade\BaseFacade;

/**
 * @method static mixed disk(?string $name = null)
 * @method static ?string getDiskPath(string $disk)
 *
 * @see \Phaseolies\Support\Storage\StorageFileService
 */
class Storage extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'storage';
    }
}
