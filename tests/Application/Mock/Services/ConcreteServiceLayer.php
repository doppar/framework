<?php

namespace Tests\Application\Mock\Services;

use Tests\Application\Mock\Interfaces\ServiceLayerInterface;
use Tests\Application\Mock\Interfaces\RepositoryInterface;

class ConcreteServiceLayer implements ServiceLayerInterface
{
    public function __construct(public RepositoryInterface $repository) {}
}