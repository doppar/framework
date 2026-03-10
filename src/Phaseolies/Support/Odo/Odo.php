<?php

namespace Phaseolies\Support\Odo;

use Phaseolies\Http\Controllers\Controller;

class Odo
{
    /**
     * Register a custom odo stamp
     *
     * @param string $name
     * @param \Closure $callback
     * @return void
     */
    public static function stamp(string $name, \Closure $callback): void
    {
        if (!preg_match('/^\w+(?:->\w+)?$/x', $name)) {
            throw new \InvalidArgumentException(
                "The directive name [{$name}] is not valid. Directive names " .
                    "must only contain alphanumeric characters and underscores."
            );
        }

        Controller::$directives[$name] = $callback;
    }

    /**
     * Get all registered directives
     *
     * @return array
     */
    public static function getDirectives(): array
    {
        return Controller::$directives;
    }

    /**
     * Check if a directive exists
     *
     * @param string $name
     * @return bool
     */
    public static function hasDirective(string $name): bool
    {
        return isset(Controller::$directives[$name]);
    }

    /**
     * Remove a directive
     *
     * @param string $name
     * @return void
     */
    public static function forgetDirective(string $name): void
    {
        unset(Controller::$directives[$name]);
    }
}
