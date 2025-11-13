<?php

namespace Tests\Application\Mock;

use Tests\Application\Mock\Interfaces\DependencyInterface;

class StaticCallableClass
{
    public static function staticWithDependency(DependencyInterface $dep): string
    {
        return get_class($dep);
    }
}