<?php

namespace Phaseolies\Utilities\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class Middleware
{
    /**
     * @param string|array $middleware Single middleware class or array of middleware classes
     */
    public function __construct(
        public string|array $middleware
    ) {}

    /**
     * Get middleware classes as an array
     *
     * @return array
     */
    public function getMiddlewareClasses(): array
    {
        return (array) $this->middleware;
    }
}
