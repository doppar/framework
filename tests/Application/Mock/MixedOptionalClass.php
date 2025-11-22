<?php

namespace Tests\Application\Mock;

use Tests\Application\Mock\Interfaces\ServiceInterface;
use Tests\Application\Mock\Interfaces\DependencyInterface;

class MixedOptionalClass
{
    public function __construct(
        public DependencyInterface $dep,
        public ServiceInterface $service,
        public string $name,
        public int $count = 0
    ) {}
}
