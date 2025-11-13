<?php

namespace Tests\Application\Mock\Providers;

use Tests\Application\Mock\Interfaces\DependencyInterface;
use Phaseolies\DI\Container;

class BootableProviderWithDependencies
{
    public ?DependencyInterface $bootedDependency = null;

    public function register(Container $container): void
    {
        $container->bind('service', fn() => 'value');
    }

    public function boot(DependencyInterface $dependency): void
    {
        $this->bootedDependency = $dependency;
    }
}