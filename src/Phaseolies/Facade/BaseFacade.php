<?php

namespace Phaseolies\Facade;

use Phaseolies\DI\Container;

abstract class BaseFacade
{
    /**
     * The application instance being facaded.
     *
     * @var \Phaseolies\Application
     */
    protected static $app;

    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    abstract protected static function getFacadeAccessor();

    /**
     * Set the application instance.
     *
     * @param  \Phaseolies\Application  $app
     * @return void
     */
    public static function setFacadeApplication($app)
    {
        static::$app = $app;
    }

    /**
     * Resolve the instance from the container.
     *
     * @return mixed
     */
    protected static function resolveInstance()
    {
        if (static::$app) {
            return static::$app->get(static::getFacadeAccessor());
        }

        return Container::getInstance()->get(static::getFacadeAccessor());
    }

    /**
     * Handle static method calls.
     *
     * @param string $method
     * @param array $args
     * @return mixed
     */
    public static function __callStatic($method, $args)
    {
        $instance = static::resolveInstance();

        if (!$instance) {
            throw new \RuntimeException('A facade root has not been set.');
        }

        return $instance->$method(...$args);
    }
}
