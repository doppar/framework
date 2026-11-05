<?php

namespace Phaseolies\Support\Facades;

use Phaseolies\Facade\BaseFacade;

/**
 * @method static \Phaseolies\Support\Validation\Sanitizer request(array $data, array $rules)
 * @method static bool validate()
 * @method static bool fails()
 * @method static array errors()
 * @method static array passed()
 * @method static array errors()
 *
 * @see \Phaseolies\Support\Validation\Sanitizer
 */
class Sanitize extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'sanitize';
    }
}
