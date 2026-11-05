<?php

namespace Tests\Unit\Builder;

require_once __DIR__ . '/MigratorTest.php';

use PHPUnit\Framework\Attributes\Group;
use Phaseolies\Database\Database;

/**
 * The Migrator test-suite against PostgreSQL, which (like SQLite) has
 * transactional DDL and uses an advisory lock. Opt-in: point it at a scratch
 * database, every table in it is dropped afterwards.
 *
 *   DOPPAR_TEST_PGSQL_HOST, DOPPAR_TEST_PGSQL_DATABASE (required),
 *   DOPPAR_TEST_PGSQL_PORT, DOPPAR_TEST_PGSQL_USERNAME, DOPPAR_TEST_PGSQL_PASSWORD
 */
#[Group('pgsql')]
class MigratorPgsqlTest extends MigratorTest
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
            'prefix' => '',
        ];
    }

    protected function wipeDatabase(): void
    {
        if ((getenv('DOPPAR_TEST_PGSQL_DATABASE') ?: '') === '') {
            return;
        }

        (new Database(self::CONNECTION))->dropAllTables();
    }
}
