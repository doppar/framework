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
     * @return void
     */
    public function extend(Closure $compiler): void
    {
        $this->extensions[] = $compiler;
    }

    /**
     * Compile the @auth directive.
     *
     * @return string
     */
    public function compileAuth(): string
    {
        return "<?php if(\Phaseolies\Support\Facades\Auth::check()): ?>";
    }

    /**
     * Compile the @endauth directive.
     *
     * @return string
     */
    public function compileEndauth(): string
    {
        return "<?php endif; ?>";
    }

    /**
     * Compile the @guest directive.
     *
     * @return string
     */
    public function compileGuest(): string
    {
        return "<?php if(!\Phaseolies\Support\Facades\Auth::check()): ?>";
    }

    /**
     * Compile the @endguest directive.
     *
     * @return string
     */
    public function compileEndguest(): string
    {
        return "<?php endif; ?>";
    }

    /**
     * Compile the @errors directive.
     *
     * @return string
     */
    public function compileErrors(): string
    {
        return "<?php if(session()->has('errors')): ?>";
    }

    /**
     * Compile the @enderrors directive.
     *
     * @return string
     */
    public function compileEnderrors(): string
    {
        return "<?php endif; ?>";
    }

    /**
     * Compile the @error directive.
     *
     * @param string $key
     * @return string
     */
    public function compileError($key): string
    {
        $key = trim($key, "()'\"");

        return "<?php if(\$message = session()->getPeek('$key')): ?>";
    }

    /**
     * Compile the @enderror directive.
     *
     * @return string
     */
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
