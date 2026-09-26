<?php

namespace Phaseolies\Launchers;

use Phaseolies\Support\Facades\Route;

class RouteLauncher extends ServiceLauncher
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $path = request()->getPath();

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
    public function launch()
    {
        if (
            $this->app->router->shouldCacheRoutes() &&
            $this->app->router->loadCachedRoutes()
        ) {
            return;
        }

        $this->app->router->loadAttributeBasedRoutes();

        Route::group(['prefix' => 'api'], fn() => require base_path('runtime/routes/api.php'));

        if ($this->app->router->shouldCacheRoutes()) {
            $this->app->router->cacheRoutes();
        }
    }
}
