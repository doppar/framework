<?php

namespace Tests\Application\Mock\Controllers;

use Tests\Application\Mock\Interfaces\RepositoryInterface;

class ControllerClass
{
    public function __construct(public RepositoryInterface $repository) {}
}