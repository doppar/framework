<?php

namespace Tests\Application\Mock;

use Tests\Application\Mock\Interfaces\DependencyInterface;

class ClassWithOptionalDependency
{
    public function __construct(
        public DependencyInterface $required,
        public string $optional = 'default'
    ) {}
}