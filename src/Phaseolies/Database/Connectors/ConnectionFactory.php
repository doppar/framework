<?php

namespace Phaseolies\Database\Connectors;

use PDO;
use RuntimeException;

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
        $driver = $config['driver'] ?? null;

        if (method_exists(__CLASS__, $method = 'create' . ucfirst($driver) . 'Connection')) {
            return self::$method($config);
        }

        throw new RuntimeException("Unsupported database driver: {$driver}");
    }

    /**
     * Creates a MySQL PDO connection with all configured options
     *
     * @param array $config The MySQL configuration array
     * @return PDO The MySQL PDO connection instance
     */
    protected static function createMysqlConnection(array $config): PDO
    {
        // Build the DSN string
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset'] ?? 'utf8mb4'
        );

        // Base PDO options
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];

        // SSL Configuration
        if (!empty($config['options'][PDO::MYSQL_ATTR_SSL_CA])) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $config['options'][PDO::MYSQL_ATTR_SSL_CA];
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;

            // Add other SSL options if they exist
            $sslOptions = [
                PDO::MYSQL_ATTR_SSL_CERT,
                PDO::MYSQL_ATTR_SSL_KEY,
                PDO::MYSQL_ATTR_SSL_CAPATH,
                PDO::MYSQL_ATTR_SSL_CIPHER
            ];

            foreach ($sslOptions as $sslOption) {
                if (!empty($config['options'][$sslOption])) {
                    $options[$sslOption] = $config['options'][$sslOption];
                }
            }
        }

        // Timezone configuration
        if (!empty($config['options'][PDO::MYSQL_ATTR_INIT_COMMAND])) {
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] = $config['options'][PDO::MYSQL_ATTR_INIT_COMMAND];
        }

        // Create the PDO connection
        try {
            $pdo = new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                $options
            );

            // Set collation if configured
            if (!empty($config['collation'])) {
                $pdo->exec("SET NAMES '{$config['charset']}' COLLATE '{$config['collation']}'");
            }

            return $pdo;
        } catch (\PDOException $e) {
            throw new \PDOException(
                "Failed to connect to MySQL database: " . $e->getMessage(),
                (int) $e->getCode()
            );
        }
    }
}
