<?php

namespace Tests\Application\Mock;

class ClassWithObject
{
    public function __construct(public object $obj) {}
}
