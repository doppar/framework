<?php

namespace Tests\Application\Mock;

class DeepNestedClass
{
    public function __construct(public ClassWithDependencyChain $chain) {}
}