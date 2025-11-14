<?php

namespace Tests\Application\Mock\Repository;

use Tests\Application\Mock\Interfaces\RepositoryInterface;
use Tests\Application\Mock\Interfaces\ConnectionInterface;

class ConcreteRepository implements RepositoryInterface
{
    public function __construct(public ?ConnectionInterface $connection = null) {}
}