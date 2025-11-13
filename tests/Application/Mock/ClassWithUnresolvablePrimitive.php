<?php 

namespace Tests\Application\Mock;

class ClassWithUnresolvablePrimitive
{
    public function __construct(public string $required) {}
}