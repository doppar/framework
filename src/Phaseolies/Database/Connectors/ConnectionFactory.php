<?php

namespace Phaseolies\Database\Connectors;

use PDO;
use RuntimeException;
use Phaseolies\Database\Drivers\MySQLDriver;
use Phaseolies\Database\Drivers\SQLiteDriver;
use Phaseolies\Database\Contracts\Driver\DriverInterface;
use Phaseolies\Database\Drivers\PostgreSQLDriver;

class ConnectionFactory
{
    /**
     * Creates a PDO connection based on the provided configuration.
     *
     * @param array $config
     * @return PDO
     * @throws RuntimeException
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

        return match (strtolower($driver)) {
            'mysql'  => new MySQLDriver(),
            'sqlite' => new SQLiteDriver(),
            'pgsql'  => new PostgreSQLDriver(),
            default  => throw new RuntimeException("Unsupported database driver: {$driver}"),
        };
    }
}
