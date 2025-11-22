<?php

namespace Tests\Application\Mock;

class ClassWithDefaults
{
    public function __construct(
        public string $name = 'default',
        public int $count = 0
    ) {}
}