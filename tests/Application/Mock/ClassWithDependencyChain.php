<?php

namespace Tests\Application\Mock;

class ClassWithDependencyChain
{
    public function __construct(public ClassWithMultipleDependencies $multi) {}
}
