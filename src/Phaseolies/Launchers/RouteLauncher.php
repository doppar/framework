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
        $path = urldecode(
            parse_url(request()->server->get("REQUEST_URI", "/"), PHP_URL_PATH)
        );

        if ($path !== '/' && str_ends_with(request()->server->get('REQUEST_URI'), '/')) {
            header('Location: ' . $this->withoutTrailingSlash(request()->server->get('REQUEST_URI')), true, 301);
            exit;
        }
    }

    /**
     * Remove the trailing slash from a request URI, keeping the result on this site.
     *
     * The URI comes from the client. Browsers treat a Location that starts with
     * "//" or "/\" as another host, so it must start with exactly one slash.
     *
     * @param string $uri
     * @return string
     */
    protected function withoutTrailingSlash(string $uri): string
    {
        return '/' . ltrim(rtrim($uri, '/'), '/\\');
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
