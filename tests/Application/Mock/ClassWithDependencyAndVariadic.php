<?php

namespace Tests\Application\Mock;

use Tests\Application\Mock\Interfaces\DependencyInterface;

class ClassWithDependencyAndVariadic
{
    public function __construct(
        public DependencyInterface $dependency,
        public array $items = []
    ) {}
}
