<?php

namespace Tests\Application\Mock;

class ClassWithAllDefaults
{
    public function __construct(
        public string $name = 'default',
        public int $count = 0,
        public bool $active = false,
        public array $items = []
    ) {}
}