<?php

namespace Tests\Application\Mock;

class ClassWithVariadic
{
    public array $items = [];

    public function __construct(...$items)
    {
        $this->items = $items;
    }
}