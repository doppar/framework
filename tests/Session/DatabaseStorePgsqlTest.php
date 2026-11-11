<?php

namespace Tests\Unit\Session;

require_once __DIR__ . '/DatabaseStoreTest.php';

use PHPUnit\Framework\Attributes\Group;

/**
 * Runs the database store tests on PostgreSQL.
 *
 * Configure through the environment:
 *   DOPPAR_TEST_PGSQL_HOST, DOPPAR_TEST_PGSQL_DATABASE (required),
 *   DOPPAR_TEST_PGSQL_PORT, DOPPAR_TEST_PGSQL_USERNAME, DOPPAR_TEST_PGSQL_PASSWORD
 */
#[Group('pgsql')]
class DatabaseStorePgsqlTest extends DatabaseStoreTest
{
    protected function connectionConfig(): array
    {
        $host = getenv('DOPPAR_TEST_PGSQL_HOST') ?: '';
        $database = getenv('DOPPAR_TEST_PGSQL_DATABASE') ?: '';

        if ($host === '' || $database === '') {
            $this->markTestSkipped('Configure DOPPAR_TEST_PGSQL_HOST/DOPPAR_TEST_PGSQL_DATABASE to run this test.');
        }

        return [
            'driver' => 'pgsql',
            'host' => $host,
            'port' => getenv('DOPPAR_TEST_PGSQL_PORT') ?: '5432',
            'database' => $database,
            'username' => getenv('DOPPAR_TEST_PGSQL_USERNAME') ?: 'postgres',
            'password' => getenv('DOPPAR_TEST_PGSQL_PASSWORD') ?: '',
            'charset' => 'utf8',
            'prefix' => '',
        ];
    }
}
