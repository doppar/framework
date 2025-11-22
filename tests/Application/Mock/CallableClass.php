<?php

namespace Tests\Application\Mock;

use Tests\Application\Mock\Interfaces\ServiceInterface;
use Tests\Application\Mock\Interfaces\DependencyInterface;

class CallableClass
{
    public function method(): string
    {
        return 'method result';
    }

    public function methodWithDependency(DependencyInterface $dep): string
    {
        return get_class($dep);
    }

    public function methodWithParams(string $name, int $value): string
    {
        return "$name:$value";
    }

    public function methodWithMixed(DependencyInterface $dep, string $value): string
    {
        return get_class($dep) . ':' . $value;
    }

    public function methodWithComplexParams(
        DependencyInterface $dep,
        ServiceInterface $service,
        string $name,
        int $count
    ): array {
        return [$dep, $service, $name, $count];
    }

    public static function staticMethod(): string
    {
        return 'static result';
    }
}
