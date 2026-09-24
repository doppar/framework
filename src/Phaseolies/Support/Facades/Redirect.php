<?php

namespace Phaseolies\Support\Facades;

use Phaseolies\Facade\BaseFacade;

/**
 * @method static \Phaseolies\Http\Response\RedirectResponse to(string $url, int $statusCode = 302)
 * @method static \Phaseolies\Http\Response\RedirectResponse back()
 * @method static \Phaseolies\Http\Response\RedirectResponse withInput()
 * @method static \Phaseolies\Http\Response\RedirectResponse route(string $name, array $params = [])
 * @method static \Phaseolies\Http\Response\RedirectResponse withErrors(array $errors)
 * @method static \Phaseolies\Http\Response\RedirectResponse away(string $url, int $statusCode = 302)
 *
 * @see \Phaseolies\Http\Response\RedirectResponse
 */
class Redirect extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'redirect';
    }
}
