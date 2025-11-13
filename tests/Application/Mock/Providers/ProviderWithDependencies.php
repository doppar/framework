<?php

namespace Tests\Application\Mock\Providers;

use Tests\Application\Mock\Interfaces\DependencyInterface;
use Phaseolies\DI\Container;

class ProviderWithDependencies
{
    public function __construct(public DependencyInterface $dependency) {}

    public function register(Container $container): void
    {
        $container->bind('provider_service', fn() => 'value');
    }
}