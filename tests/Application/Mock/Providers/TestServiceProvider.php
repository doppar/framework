<?php

namespace Tests\Application\Mock\Providers;

use Phaseolies\DI\Container;

class TestServiceProvider
{
    public function register(Container $container): void
    {
        $container->bind('from_provider', fn() => 'provided_value');
    }

    public function boot(): void {}
}