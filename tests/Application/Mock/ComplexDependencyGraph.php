<?php

namespace Tests\Application\Mock;

use Tests\Application\Mock\Interfaces\ServiceInterface;
use Tests\Application\Mock\Interfaces\RepositoryInterface;
use Tests\Application\Mock\Interfaces\DependencyInterface;

class ComplexDependencyGraph
{
    public function __construct(
        public DependencyInterface $dependency,
        public ServiceInterface $service,
        public RepositoryInterface $repository,
        public array $config
    ) {}
}