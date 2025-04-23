<?php

namespace Phaseolies\Support\Facades;

use Phaseolies\Facade\BaseFacade;

class Session extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'session';
    }
}
