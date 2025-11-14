<?php

namespace Tests\Application\Mock;

use Tests\Application\Mock\Interfaces\DependencyInterface;

class ConcreteDependency implements DependencyInterface
{
    public $id;
    public $extended = false;
}

