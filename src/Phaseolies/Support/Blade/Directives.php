<?php

namespace Phaseolies\Support\Blade;

use Closure;
use InvalidArgumentException;

trait Directives
{
    /**
     * Extend this class (Add custom directives).
     *
     * @param Closure $compiler
     */
    public function extend(Closure $compiler): void
    {
        $this->extensions[] = $compiler;
    }

    public function compileAuth()
    {
        return "<?php if(\Phaseolies\Support\Facades\Auth::check()): ?>";
    }

    public function compileEndauth()
    {
        return "<?php endif; ?>";
    }

    public function compileGuest()
    {
        return "<?php if(!\Phaseolies\Support\Facades\Auth::check()): ?>";
    }

    public function compileEndguest()
    {
        return "<?php endif; ?>";
    }

    public function compileErrors()
    {
        return "<?php if(session()->has('errors')): ?>";
    }

    public function compileEnderrors()
    {
        return "<?php endif; ?>";
    }

    public function compileError($key): string
    {
        $key = trim($key, "()'\"");

        return "<?php if(\$message = session()->getPeek('$key')): ?>";
    }

    public function compileEnderror(): string
    {
        return "<?php endif; ?>";
    }

    /**
     * Another (simpler) way to add custom directives.
     *
     * @param string $name
     * @param string $callback
     */
    public function directive($name, Closure $callback): void
    {
        if (!preg_match('/^\w+(?:->\w+)?$/x', $name)) {
            throw new InvalidArgumentException(
                'The directive name [' . $name . '] is not valid. Directive names ' .
                    'must only contains alphanumeric characters and underscores.'
            );
        }

        self::$directives[$name] = $callback;
    }

    /**
     * Get all defined directives.
     *
     * @return array
     */
    public function getAllDirectives(): array
    {
        return self::$directives;
    }
}
