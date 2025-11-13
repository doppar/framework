<?php

namespace Tests\Application\Mock;

use Tests\Application\Mock\Interfaces\DependencyInterface;

class ComplexConstructorClass
{
    public function __construct(
        public DependencyInterface $dependency,
        public string $name,
        public int $count,
        public bool $active
    ) {}
}
