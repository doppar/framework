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
        $target = $this->trailingSlashRedirect(request()->server->get('REQUEST_URI', '/'));

        if ($target !== null) {
            header('Location: ' . $target, true, 301);
            exit;
        }
    }

    /**
     * Where a request URI with a trailing slash on its path should redirect, if anywhere.
     *
     * The URI comes from the client. Browsers treat a Location that starts with
     * "//" or "/\" as another host, so it must start with exactly one slash.
     * They also drop tabs and newlines, so "/<tab>/host" would become "//host":
     * a URI with control characters is never redirected.
     *
     * @param string $uri
     * @return string|null
     */
    protected function trailingSlashRedirect(string $uri): ?string
    {
        if (preg_match('/[\x00-\x1F\x7F]/', $uri) === 1) {
            return null;
        }

        [$path, $query] = array_pad(explode('?', $uri, 2), 2, null);

        $target = '/' . ltrim(rtrim($path, '/'), '/\\');

        if (!str_ends_with($path, '/') || $target === '/') {
            return null;
        }

        return $query === null ? $target : $target . '?' . $query;
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
