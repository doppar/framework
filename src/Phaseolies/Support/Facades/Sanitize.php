<?php

namespace Phaseolies\Support\Facades;

/**
 * @method static \Phaseolies\Support\Validation\Sanitizer request(array $data, array $rules)
 * @method static \Phaseolies\Support\Validation\Sanitizer validate()
 * @method static \Phaseolies\Support\Validation\Sanitizer fails()
 * @method static \Phaseolies\Support\Validation\Sanitizer errors()
 * @method static \Phaseolies\Support\Validation\Sanitizer passed()
 * @method static \Phaseolies\Support\Validation\Sanitizer errors()
 * @see \Phaseolies\Support\Validation\Sanitizer
 */

use Phaseolies\Facade\BaseFacade;

class Sanitize extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'sanitize';
    }
}
