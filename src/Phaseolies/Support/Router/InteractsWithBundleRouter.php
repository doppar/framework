<?php

namespace Phaseolies\Support\Router;

trait InteractsWithBundleRouter
{
    /**
     * Register a bundle route to the controller.
     *
     * @param string $uri
     * @param string $controller
     * @param array $options
     * @return void
     */
    public function bundle(string $uri, string $controller, array $options = []): void
    {
        $baseUri = trim($uri, '/');
        $name = $options['as'] ?? str_replace('/', '.', $baseUri);
        $only = $options['only'] ?? null;
        $except = $options['except'] ?? null;
        $names = $options['names'] ?? [];
        $methods = $options['methods'] ?? [];

        $routes = [
            'index' => [$methods['index'] ?? 'GET', "/{$baseUri}", 'index'],
            'create' => [$methods['create'] ?? 'GET', "/{$baseUri}/create", 'create'],
            'store' => [$methods['store'] ?? 'POST', "/{$baseUri}", 'store'],
            'show' => [$methods['show'] ?? 'GET', "/{$baseUri}/{{$this->getBundleRouteKey($controller)}}/show", 'show'],
            'edit' => [$methods['edit'] ?? 'GET', "/{$baseUri}/{{$this->getBundleRouteKey($controller)}}/edit", 'edit'],
            'update' => [$methods['update'] ?? 'PUT', "/{$baseUri}/{{$this->getBundleRouteKey($controller)}}/update", 'update'],
            'delete' => [$methods['delete'] ?? 'DELETE', "/{$baseUri}/{{$this->getBundleRouteKey($controller)}}/delete", 'delete'],
        ];

        foreach ($routes as $methodName => $route) {
            if ($this->shouldRegisterRoute($methodName, $only, $except)) {
                [$httpMethod, $uri, $action] = $route;

                if (!$this->isValidHttpMethod($httpMethod)) {
                    throw new \InvalidArgumentException("Invalid HTTP method '{$httpMethod}' for route '{$methodName}'");
                }

                $routeName = $names[$methodName] ?? "{$name}.{$methodName}";

                $this->{$httpMethod}($uri, [$controller, $action])->name($routeName);
            }
        }
    }

    /**
     * Check is the method is valid HTTP method
     *
     * @param string $method
     * @return bool
     */
    protected function isValidHttpMethod(string $method): bool
    {
        return in_array(strtoupper($method), [
            'GET',
            'HEAD',
            'POST',
            'PUT',
            'PATCH',
            'DELETE',
            'OPTIONS',
            'ANY'
        ], true);
    }

    /**
     * Get the route key for the bundle
     *
     * @param string $controller
     * @return string
     */
    protected function getBundleRouteKey(string $controller): string
    {
        $baseName = str_replace('Controller', '', class_basename($controller));
        $normalized = preg_replace('/(?<!^)[A-Z]/', ' $0', $baseName);
        $normalized = str_replace('_', ' ', $normalized);
        $modelName = str_replace(' ', '', ucwords(strtolower($normalized)));

        $modelClass = "App\\Models\\{$modelName}";

        if (class_exists($modelClass)) {
            return app($modelClass)->getRouteKeyName();
        }

        return 'id';
    }

    /**
     * Determine if a route should be registered based on only/except options
     *
     * @param string $method
     * @param array|null $only
     * @param array|null $except
     * @return bool
     */
    protected function shouldRegisterRoute(string $method, ?array $only, ?array $except): bool
    {
        if ($only !== null) {
            return in_array($method, $only);
        }

        if ($except !== null) {
            return !in_array($method, $except);
        }

        return true;
    }

    /**
     * Register an API bundle route (excludes create/edit routes)
     *
     * @param string $baseUri
     * @param string $controller
     * @param array $options
     * @return void
     */
    public function apiBundle(string $baseUri, string $controller, array $options = []): void
    {
        $options['except'] = ['create', 'edit'];

        $this->bundle($baseUri, $controller, $options);
    }

    /**
     * Nested bunde route
     *
     * @param string $parent
     * @param string $child
     * @param string $controller
     * @param array $options
     * @return void
     */
    public function nestedBundle(string $parent, string $child, string $controller, array $options = []): void
    {
        $baseUri = trim("{$parent}/{{$parent}}/{$child}", '/');

        $this->bundle($baseUri, $controller, $options);
    }
}
