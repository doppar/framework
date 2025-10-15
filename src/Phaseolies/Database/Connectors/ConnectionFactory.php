<?php

namespace Phaseolies\Database\Connectors;

use PDO;
use RuntimeException;
use Phaseolies\Database\Drivers\MySQLDriver;
use Phaseolies\Database\Drivers\SQLiteDriver;
use Phaseolies\Database\Contracts\Driver\DriverInterface;

class ConnectionFactory
{
    /**
     * Creates a PDO connection based on the provided configuration.
     *
     * @param array $config The database configuration array
     * @return PDO The PDO database connection instance
     * @throws RuntimeException If the specified driver is not supported
     */
    public static function make(array $config): PDO
    {
        $driver = self::createDriver($config);
        return $driver->connect($config);
    }

    /**
     * Resolve a database driver instance from configuration.
     *
     * @param array $config
     * @return DriverInterface
     */
    public static function createDriver(array $config): DriverInterface
    {
        $driver = $config['driver'] ?? 'mysql';

        switch (strtolower($driver)) {
            case 'mysql':
                return new MySQLDriver();
            case 'sqlite':
                return new SQLiteDriver();
            default:
                throw new RuntimeException("Unsupported database driver: {$driver}");
        }
    }
}
