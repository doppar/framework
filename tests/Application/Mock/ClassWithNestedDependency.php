<?php

namespace Tests\Application\Mock;

class ClassWithNestedDependency
{
    public function __construct(public ClassWithDependency $nested) {}
}
