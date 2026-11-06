<?php

namespace Tests\Unit\Session;

require_once __DIR__ . '/DatabaseStoreTest.php';

use PHPUnit\Framework\Attributes\Group;

/**
 * Runs the database store tests on MySQL/MariaDB.
 *
 * Configure through the environment:
 *   DOPPAR_TEST_MYSQL_HOST, DOPPAR_TEST_MYSQL_DATABASE (required),
 *   DOPPAR_TEST_MYSQL_PORT, DOPPAR_TEST_MYSQL_USERNAME, DOPPAR_TEST_MYSQL_PASSWORD
 */
#[Group('mysql')]
class DatabaseStoreMysqlTest extends DatabaseStoreTest
{
    protected function connectionConfig(): array
    {
        $host = getenv('DOPPAR_TEST_MYSQL_HOST') ?: '';
        $database = getenv('DOPPAR_TEST_MYSQL_DATABASE') ?: '';

        if ($host === '' || $database === '') {
            $this->markTestSkipped('Configure DOPPAR_TEST_MYSQL_HOST/DOPPAR_TEST_MYSQL_DATABASE to run this test.');
        }

        return [
            'driver' => 'mysql',
            'host' => $host,
            'port' => getenv('DOPPAR_TEST_MYSQL_PORT') ?: '3306',
            'database' => $database,
            'username' => getenv('DOPPAR_TEST_MYSQL_USERNAME') ?: 'root',
            'password' => getenv('DOPPAR_TEST_MYSQL_PASSWORD') ?: '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
        ];
    }
}
