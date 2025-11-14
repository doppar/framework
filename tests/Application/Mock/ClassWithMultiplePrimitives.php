<?php

namespace Tests\Application\Mock;

class ClassWithMultiplePrimitives
{
    public function __construct(
        public string $name,
        public int $age,
        public bool $active
    ) {}
}