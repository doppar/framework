<?php

namespace Tests\Unit\Builder;

use PDO;
use PHPUnit\Framework\TestCase;
use Phaseolies\Config\Config;
use Phaseolies\Database\Database;

class DatabaseConnectionPoolTest extends TestCase
{
    private Database $database;

    /**
     * @var array<string, mixed>
     */
    private array $originalConfigState = [];

    private string $primaryPath;
    private string $analyticsPath;

    protected function setUp(): void
    {
        $this->originalConfigState = $this->captureStaticProperties(Config::class, [
            'config',
            'cacheFile',
            'loadedFromCache',
            'configModified',
            'fileHashes',
            'configFiles',
        ]);

        $this->resetDatabaseStatics();
        $this->primeDatabaseConfig();

        $this->database = new Database('primary');
    }

    protected function tearDown(): void
    {
        $this->resetDatabaseStatics();
        $this->restoreStaticProperties(Config::class, $this->originalConfigState);
        $this->cleanupTempFile($this->primaryPath ?? null);
        $this->cleanupTempFile($this->analyticsPath ?? null);
    }

    public function testGetPdoInstanceCachesConnectionsPerName(): void
    {
        $primary = Database::getPdoInstance('primary');
        $primaryAgain = Database::getPdoInstance('primary');
        $analytics = Database::getPdoInstance('analytics');

        $this->assertSame($primary, $primaryAgain);
        $this->assertNotSame($primary, $analytics);
        $this->assertSame('sqlite', $primary->getAttribute(PDO::ATTR_DRIVER_NAME));
        $this->assertTrue($this->database->isConnected('primary'));
        $this->assertTrue($this->database->isConnected('analytics'));
    }

    public function testTransactionLevelsAreTrackedPerConnection(): void
    {
        $primary = new Database('primary');
        $analytics = new Database('analytics');

        $primary->beginTransaction();
        $this->assertSame(1, Database::transactionLevel('primary'));
        $this->assertSame(0, Database::transactionLevel('analytics'));

        $analytics->beginTransaction();
        $this->assertSame(1, Database::transactionLevel('primary'));
        $this->assertSame(1, Database::transactionLevel('analytics'));

        $primary->beginTransaction();
        $this->assertSame(2, Database::transactionLevel('primary'));
        $this->assertSame(1, Database::transactionLevel('analytics'));

        $primary->rollBack();
        $this->assertSame(1, Database::transactionLevel('primary'));

        $analytics->rollBack();
        $this->assertSame(0, Database::transactionLevel('analytics'));

        $primary->rollBack();
        $this->assertSame(0, Database::transactionLevel('primary'));
    }

    public function testDisconnectRemovesOnlyRequestedConnection(): void
    {
        Database::getPdoInstance('primary');
        Database::getPdoInstance('analytics');

        $analytics = new Database('analytics');
        $analytics->beginTransaction();

        $this->assertTrue($this->database->disconnect('analytics'));
        $this->assertTrue($this->database->isConnected('primary'));
        $this->assertFalse($this->database->isConnected('analytics'));
        $this->assertSame(0, Database::transactionLevel('analytics'));

        $connections = $this->getStaticProperty(Database::class, 'connections');
        $drivers = $this->getStaticProperty(Database::class, 'drivers');

        $this->assertArrayHasKey('primary', $connections);
        $this->assertArrayNotHasKey('analytics', $connections);
        $this->assertArrayHasKey('primary', $drivers);
        $this->assertArrayNotHasKey('analytics', $drivers);
    }

    public function testReconnectReturnsFreshConnectionAndReplacesPooledInstance(): void
    {
        $original = Database::getPdoInstance('primary');

        $reconnected = $this->database->reconnect('primary');
        $pooled = Database::getPdoInstance('primary');

        $this->assertInstanceOf(PDO::class, $reconnected);
        $this->assertNotSame($original, $reconnected);
        $this->assertSame($reconnected, $pooled);
        $this->assertTrue($this->database->isConnected('primary'));
    }

    public function testGetFreshConnectionBypassesThePool(): void
    {
        $pooled = Database::getPdoInstance('primary');
        $fresh = $this->database->getFreshConnection('primary');
        $pooledAgain = Database::getPdoInstance('primary');

        $this->assertInstanceOf(PDO::class, $fresh);
        $this->assertNotSame($pooled, $fresh);
        $this->assertSame($pooled, $pooledAgain);
    }

    public function testCleanupAllConnectionsClearsPoolsDriversAndTransactions(): void
    {
        Database::getPdoInstance('primary');
        Database::getPdoInstance('analytics');

        $primary = new Database('primary');
        $analytics = new Database('analytics');

        $primary->beginTransaction();
        $analytics->beginTransaction();

        $this->database->cleanupAllConnections();

        $this->assertFalse($this->database->isConnected('primary'));
        $this->assertFalse($this->database->isConnected('analytics'));
        $this->assertSame(0, Database::transactionLevel('primary'));
        $this->assertSame(0, Database::transactionLevel('analytics'));
        $this->assertSame([], $this->getStaticProperty(Database::class, 'connections'));
        $this->assertSame([], $this->getStaticProperty(Database::class, 'drivers'));
        $this->assertSame([], $this->getStaticProperty(Database::class, 'transactions'));
    }

    public function testGetPdoInstanceThrowsForUnknownConnectionConfiguration(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database connection [missing] not configured.');

        Database::getPdoInstance('missing');
    }

    public function testGetPdoInstanceWrapsUnsupportedDriverErrors(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Database connection error [unsupported]: Unsupported database driver: sqlsrv'
        );

        Database::getPdoInstance('unsupported');
    }

    private function primeDatabaseConfig(): void
    {
        $this->primaryPath = (string) tempnam(sys_get_temp_dir(), 'doppar-primary-');
        $this->analyticsPath = (string) tempnam(sys_get_temp_dir(), 'doppar-analytics-');

        $this->restoreStaticProperties(Config::class, [
            'config' => [
                'database' => [
                    'default' => 'primary',
                    'connections' => [
                        'primary' => [
                            'driver' => 'sqlite',
                            'database' => $this->primaryPath,
                        ],
                        'analytics' => [
                            'driver' => 'sqlite',
                            'database' => $this->analyticsPath,
                        ],
                        'unsupported' => [
                            'driver' => 'sqlsrv',
                            'database' => 'irrelevant',
                        ],
                    ],
                ],
            ],
            'cacheFile' => sys_get_temp_dir() . '/doppar-config-test-cache.php',
            'loadedFromCache' => true,
            'configModified' => false,
            'fileHashes' => [],
            'configFiles' => [],
        ]);
    }

    private function cleanupTempFile(?string $path): void
    {
        if ($path !== null && $path !== '' && file_exists($path)) {
            @unlink($path);
        }
    }

    private function resetDatabaseStatics(): void
    {
        $this->restoreStaticProperties(Database::class, [
            'connections' => [],
            'transactions' => [],
            'drivers' => [],
        ]);
    }

    /**
     * @param array<int, string> $properties
     * @return array<string, mixed>
     */
    private function captureStaticProperties(string $className, array $properties): array
    {
        $reflection = new \ReflectionClass($className);
        $values = [];

        foreach ($properties as $propertyName) {
            $values[$propertyName] = $reflection->getProperty($propertyName)->getValue();
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function restoreStaticProperties(string $className, array $values): void
    {
        $reflection = new \ReflectionClass($className);

        foreach ($values as $propertyName => $value) {
            $reflection->getProperty($propertyName)->setValue(null, $value);
        }
    }

    private function getStaticProperty(string $className, string $propertyName): mixed
    {
        $reflection = new \ReflectionClass($className);

        return $reflection->getProperty($propertyName)->getValue();
    }
}
