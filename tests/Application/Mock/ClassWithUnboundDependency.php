<?php

namespace Tests\Application\Mock;

use Tests\Application\Mock\Interfaces\UnboundInterface;

class ClassWithUnboundDependency
{
    public function __construct(public UnboundInterface $dependency) {}
}