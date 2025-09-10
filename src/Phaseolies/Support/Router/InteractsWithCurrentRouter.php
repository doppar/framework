<?php

namespace Phaseolies\Support\Router;

trait InteractsWithCurrentRouter
{
    /**
     * Get the current matched route information
     *
     * @return array|null
     */
    public function getRouteNames(): ?array
    {
        return self::$namedRoutes;
    }

    /**
     * Get the current request middleware names
     *
     * @return array|null
     */
    public function getCurrentMiddlewareNames(): ?array
    {
        $url = request()->getPath();
        $method = request()->getMethod();

        if (isset(self::$routeMiddlewares[$method][$url])) {
            return self::$routeMiddlewares[$method][$url];
        }

        foreach (self::$routeMiddlewares[$method] as $route => $middlewares) {
            $routeRegex = $this->convertRouteToRegex($route);
            if (preg_match($routeRegex, $url)) {
                return $middlewares;
            }
        }

        return null;
    }

    /**
     * Check if a named route exists.
     *
     * @param string $name
     * @return bool
     */
    public function has(string $name): bool
    {
        return (bool) array_key_exists($name, self::$namedRoutes);
    }

    /**
     * Check if the current request matches a specific route name.
     *
     * @param string $name
     * @return bool
     */
    public function is(string $name): bool
    {
        if (!isset(self::$namedRoutes[$name])) {
            return false;
        }

        $currentPath = request()->getPath();

        $expectedPath = self::$namedRoutes[$name];

        if ($currentPath === $expectedPath) {
            return true;
        }

        $routeRegex = $this->convertRouteToRegex($expectedPath);

        return (bool) preg_match($routeRegex, $currentPath);
    }

    /**
     * Get the name of the current route.
     *
     * @return string|null
     */
    public function currentRouteName(): ?string
    {
        $currentPath = request()->getPath();
        $currentMethod = request()->getMethod();

        foreach (self::$namedRoutes as $name => $path) {
            if ($currentPath === $path) {
                if (isset(self::$routes[$currentMethod][$path])) {
                    return $name;
                }
            }

            $routeRegex = $this->convertRouteToRegex($path);
            if (preg_match($routeRegex, $currentPath) && isset(self::$routes[$currentMethod][$path])) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Get the current route action
     *
     * @return string|array|null
     */
    public function currentRouteAction(): string|array|null
    {
        $callback = $this->getCallback(request());

        if (is_array($callback)) {
            return $callback[0] . '@' . $callback[1];
        }

        if (is_string($callback)) {
            return $callback . '@__invoke';
        }

        if ($callback instanceof \Closure) {
            return 'Closure';
        }

        return null;
    }

    /**
     * Check if the current route uses a specific controller.
     *
     * @param string $controllerClass
     * @return bool
     */
    public function currentRouteUsesController(string $controllerClass): bool
    {
        $currentAction = $this->currentRouteAction();

        if (is_string($currentAction)) {
            [$currentController] = explode('@', $currentAction);
            return $currentController === $controllerClass;
        }

        return false;
    }
}
