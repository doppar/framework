<?php

namespace Tests\Application\Mock;

class ClassWithTypedVariadic
{
    public array $numbers = [];

    public function __construct(...$numbers)
    {
        $this->numbers = $numbers;
    }
}
