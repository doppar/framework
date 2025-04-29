<?php

namespace Phaseolies\Providers;

use Phaseolies\Support\Router;
use Phaseolies\Support\Facades\Route;

/**
 * RouteServiceProvider is responsible for registering and bootstrapping
 * the application's routing functionality.
 *
 * This class binds the Router instance into the service container and
 * loads the application's route definitions.
 */
class RouteServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * This method is called when the service provider is registered.
     * It binds the Router instance as a singleton into the service container,
     * ensuring that the same Router instance is reused throughout the application.
     *
     * @return void
     */
    public function register()
    {
        // Bind the 'route' key in the container to a singleton instance of Router.
        // This ensures that the same Router instance is returned whenever 'route' is resolved.
        $this->app->singleton('route', Router::class);

        $path = urldecode(
            parse_url(request()->server->get("REQUEST_URI", "/"), PHP_URL_PATH)
        );

        if ($path !== '/' && str_ends_with(request()->server->get('REQUEST_URI'), '/')) {
            header('Location: ' . rtrim(request()->server->get('REQUEST_URI'), '/'), true, 301);
            exit;
        }
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if (
            $this->app->router->shouldCacheRoutes() &&
            $this->app->router->loadCachedRoutes()
        ) {
            return;
        }

        // Load the web routes file.
        // This file typically contains the route definitions for the application.
        require base_path('routes/web.php');

        // Load the api routes file.
        // This file typically contains the api route definitions for the application.
        Route::group([
            'prefix' => 'api'
        ], function () {
            require base_path('routes/api.php');
        });

        if ($this->app->router->shouldCacheRoutes()) {
            $this->app->router->cacheRoutes();
        }
    }
}
