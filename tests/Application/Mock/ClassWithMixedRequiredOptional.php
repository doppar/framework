<?php

namespace Tests\Application\Mock;

use Tests\Application\Mock\Interfaces\DependencyInterface;

class ClassWithMixedRequiredOptional
{
    public function __construct(
        public DependencyInterface $dependency,
        public string $name,
        public string $optional = 'optional'
    ) {}
}