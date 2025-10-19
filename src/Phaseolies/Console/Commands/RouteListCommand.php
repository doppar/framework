<?php

namespace Phaseolies\Console\Commands;

use RuntimeException;
use Phaseolies\Console\Schedule\Command;
use Phaseolies\Support\Facades\Pool;

class RouteListCommand extends Command
{
    /**
     * The name of the console command.
     *
     * @var string
     */
    protected $name = 'route:list';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Display all registered routes';

    /**
     * Execute the console command.
     *
     * @return int
     */
    protected function handle(): int
    {
        return $this->executeWithTiming(function () {

            $cachePath = storage_path('framework/cache/routes.php');

            if (!file_exists($cachePath)) {
                Pool::call('route:cache');
            }

            $routesData = require $cachePath;

            if (!isset($routesData['routes'])) {
                throw new RuntimeException("Invalid route cache format.");
            }

            $table = $this->createTable();
            $table->setHeaders(['Method', 'URI', 'Controller', 'Action', 'Middleware']);

            foreach ($routesData['routes'] as $method => $routes) {
                foreach ($routes as $uri => $handler) {
                    if (is_array($handler)) {
                        [$controller, $action] = $handler;
                    } else {
                        $controller = $handler;
                        $action = '__invoke';
                    }

                    $middlewares = $routesData['routeMiddlewares'][$method][$uri] ?? [];
                    $middlewares = implode(', ', $middlewares);

                    $table->addRow([
                        $method,
                        $uri,
                        $controller,
                        $action,
                        $middlewares,
                    ]);
                }
            }

            $table->render();

            return 0;
        });
    }
}
