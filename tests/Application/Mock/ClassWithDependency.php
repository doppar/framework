<?php

namespace Tests\Application\Mock;

use Tests\Application\Mock\Interfaces\DependencyInterface;

class ClassWithDependency
{
    public function __construct(public DependencyInterface $dependency) {}
}