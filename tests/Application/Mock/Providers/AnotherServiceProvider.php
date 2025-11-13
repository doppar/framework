<?php

namespace Tests\Application\Mock\Providers;

use Phaseolies\DI\Container;

class AnotherServiceProvider
{
    public function register(Container $container): void
    {
        $container->bind('another_service', fn() => 'another_value');
    }
}