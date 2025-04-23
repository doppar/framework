<?php

namespace Phaseolies\Support\Facades;

use Phaseolies\Facade\BaseFacade;

class Response extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'response';
    }
}
