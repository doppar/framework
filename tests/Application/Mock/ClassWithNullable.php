<?php

namespace Tests\Application\Mock;

class ClassWithNullable
{
    public function __construct(public ?string $value = null) {}
}