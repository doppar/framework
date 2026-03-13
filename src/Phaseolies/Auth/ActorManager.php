<?php

namespace Phaseolies\Auth;

use Phaseolies\Auth\Security\Authenticate;

class ActorManager
{
    /**
     * All resolved actor instances, keyed by actor name.
     *
     * @var array<string, Authenticate>
     */
    protected array $actors = [];

    /**
     * Get (or create) a named actor instance.
     *
     * @param string|null $name
     * @return Authenticate
     */
    public function actor(?string $name = null): Authenticate
    {
        $name ??= config('auth.default', 'web');

        if (!isset($this->actors[$name])) {
            $this->actors[$name] = $this->resolve($name);
        }

        return $this->actors[$name];
    }

    /**
     * Resolve a actor instance from its config entry.
     *
     * @param string $name
     * @return Authenticate
     * @throws \InvalidArgumentException
     */
    protected function resolve(string $name): Authenticate
    {
        $config = config("auth.actors.{$name}");

        if (!$config) {
            throw new \InvalidArgumentException(
                "Auth actor [{$name}] is not defined. Check your config/auth.php."
            );
        }

        return new Authenticate($name, $config);
    }

    /**
     * Flush all resolved actor instances
     *
     * @return void
     */
    public function forgetActors(): void
    {
        $this->actors = [];
    }

    /**
     * Allows auth()->check(), auth()->user(), etc. to keep working without explicitly calling auth()->actor('web')->...
     *
     * @param string $method
     * @param array  $args
     * @return mixed
     */
    public function __call(string $method, array $args): mixed
    {
        return $this->actor()->{$method}(...$args);
    }
}
