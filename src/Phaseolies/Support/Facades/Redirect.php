<?php

namespace Phaseolies\Support\Facades;

/**
 * @method static \Phaseolies\Http\RedirectResponse to(string $url, int $statusCode = 302)
 * @method static \Phaseolies\Http\RedirectResponse back()
 * @method static \Phaseolies\Http\RedirectResponse withInput()
 * @method static \Phaseolies\Http\RedirectResponse route(string $name, array $params = [])
 * @method static \Phaseolies\Http\RedirectResponse withErrors(array $errors)
 * @method static \Phaseolies\Http\RedirectResponse away(string $url, int $statusCode = 302)
 * @see \Phaseolies\Http\RedirectResponse
 */

use Phaseolies\Facade\BaseFacade;

class Redirect extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'redirect';
    }
}
