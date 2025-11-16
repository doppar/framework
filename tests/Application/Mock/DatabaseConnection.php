<?php

namespace Tests\Application\Mock;

use Tests\Application\Mock\Interfaces\ConnectionInterface;

class DatabaseConnection implements ConnectionInterface
{
    public function __construct(public bool $active = true) {}
}
