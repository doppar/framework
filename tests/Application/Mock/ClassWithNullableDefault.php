<?php

namespace Tests\Application\Mock;

class ClassWithNullableDefault
{
    public function __construct(public ?string $value = 'default') {}
}