<?php

namespace Tests\Application\Mock;

use Tests\Application\Mock\Interfaces\DependencyInterface;

class ClassWithNullableClass
{
    public function __construct(public ?DependencyInterface $dependency = null) {}
}
