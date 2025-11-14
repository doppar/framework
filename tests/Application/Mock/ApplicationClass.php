<?php

namespace Tests\Application\Mock;

use Tests\Application\Mock\Interfaces\ServiceInterface;
use Tests\Application\Mock\Interfaces\DependencyInterface;

class ApplicationClass
{
    public function __construct(
        public DependencyInterface $dependency,
        public ServiceInterface $service,
        public array $config
    ) {}
}