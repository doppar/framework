<?php

namespace Tests\Unit\Model;

require_once __DIR__ . '/../Support/Model/MockPrimaryConnectionRecord.php';
require_once __DIR__ . '/../Support/Model/MockAnalyticsConnectionRecord.php';
require_once __DIR__ . '/../Support/Model/MockFlexibleConnectionRecord.php';

use PDO;
use PHPUnit\Framework\TestCase;
use Phaseolies\Database\Database;
use Tests\Support\Model\MockAnalyticsConnectionRecord;
use Tests\Support\Model\MockFlexibleConnectionRecord;
use Tests\Support\Model\MockPrimaryConnectionRecord;

class MultiConnectionModelTest extends TestCase
{
    private PDO $primaryPdo;
    private PDO $analyticsPdo;

    protected function setUp(): void
    {
        $this->primaryPdo = $this->makeConnection();
        $this->analyticsPdo = $this->makeConnection();

        $this->createSchema($this->primaryPdo);
        $this->createSchema($this->analyticsPdo);

        $this->seedPrimaryDatabase();
        $this->seedAnalyticsDatabase();

        $this->setStaticProperty(Database::class, 'connections', [
            'primary' => $this->primaryPdo,
            'analytics' => $this->analyticsPdo,
        ]);
        $this->setStaticProperty(Database::class, 'drivers', []);
        $this->setStaticProperty(Database::class, 'transactions', []);
    }

    protected function tearDown(): void
    {
        $this->setStaticProperty(Database::class, 'connections', []);
        $this->setStaticProperty(Database::class, 'drivers', []);
        $this->setStaticProperty(Database::class, 'transactions', []);
    }

    public function testDedicatedModelsFetchFromTheirAssignedConnections(): void
    {
        $primary = MockPrimaryConnectionRecord::find(1);
        $analytics = MockAnalyticsConnectionRecord::find(1);

        $this->assertSame('Primary Seed', $primary->name);
        $this->assertSame('Analytics Seed', $analytics->name);
        $this->assertSame('primary', $primary->getConnectionName());
        $this->assertSame('analytics', $analytics->getConnectionName());
    }

    public function testCreatePersistsIntoConfiguredConnectionOnly(): void
    {
        $primary = MockPrimaryConnectionRecord::create([
            'name' => 'Primary Create',
            'status' => 'draft',
            'amount' => 175,
        ]);

        $analytics = MockAnalyticsConnectionRecord::create([
            'name' => 'Analytics Create',
            'status' => 'published',
            'amount' => 480,
        ]);

        $this->assertNotNull($primary->id);
        $this->assertNotNull($analytics->id);
        $this->assertSame(3, $this->countRows($this->primaryPdo));
        $this->assertSame(3, $this->countRows($this->analyticsPdo));
        $this->assertSame('Primary Create', $this->findRow($this->primaryPdo, $primary->id)['name']);
        $this->assertNull($this->findRowByName($this->analyticsPdo, 'Primary Create'));
        $this->assertSame('Analytics Create', $this->findRow($this->analyticsPdo, $analytics->id)['name']);
        $this->assertNull($this->findRowByName($this->primaryPdo, 'Analytics Create'));
    }

    public function testFetchedModelUpdateOnlyTouchesItsOwnConnection(): void
    {
        $primary = MockPrimaryConnectionRecord::find(1);
        $analytics = MockAnalyticsConnectionRecord::find(1);

        $primary->status = 'archived';
        $analytics->amount = 999;

        $this->assertTrue($primary->save());
        $this->assertTrue($analytics->save());

        $this->assertSame('archived', $this->findRow($this->primaryPdo, 1)['status']);
        $this->assertSame('pending', $this->findRow($this->analyticsPdo, 1)['status']);
        $this->assertSame(999, (int) $this->findRow($this->analyticsPdo, 1)['amount']);
        $this->assertSame(900, (int) $this->findRow($this->primaryPdo, 1)['amount']);
    }

    public function testFetchedModelDeleteOnlyRemovesRowFromItsOwnConnection(): void
    {
        $primary = MockPrimaryConnectionRecord::find(2);
        $analytics = MockAnalyticsConnectionRecord::find(2);

        $this->assertTrue($primary->delete());
        $this->assertTrue($analytics->delete());

        $this->assertNull($this->findRow($this->primaryPdo, 2));
        $this->assertNull($this->findRow($this->analyticsPdo, 2));
        $this->assertSame(1, $this->countRows($this->primaryPdo));
        $this->assertSame(1, $this->countRows($this->analyticsPdo));
    }

    public function testExplicitQueryConnectionFetchesFromRequestedDatabase(): void
    {
        $primary = MockFlexibleConnectionRecord::query('primary')
            ->where('name', 'Primary Seed')
            ->first();

        $analytics = MockFlexibleConnectionRecord::query('analytics')
            ->where('name', 'Analytics Seed')
            ->first();

        $this->assertSame('primary', $primary->getConnectionName());
        $this->assertSame('analytics', $analytics->getConnectionName());
        $this->assertSame(900, (int) $primary->amount);
        $this->assertSame(450, (int) $analytics->amount);
    }

    public function testExplicitConnectionBuilderCreateUsesRequestedDatabase(): void
    {
        $created = MockFlexibleConnectionRecord::connection('analytics')->create([
            'name' => 'Builder Insert',
            'status' => 'queued',
            'amount' => 320,
        ]);

        $this->assertSame('analytics', $created->getConnectionName());
        $this->assertSame(2, $this->countRows($this->primaryPdo));
        $this->assertSame(3, $this->countRows($this->analyticsPdo));
        $this->assertNull($this->findRowByName($this->primaryPdo, 'Builder Insert'));
        $this->assertSame('Builder Insert', $this->findRowByName($this->analyticsPdo, 'Builder Insert')['name']);
    }

    public function testHydratedModelFromExplicitConnectionCanBeUpdatedAndDeletedSafely(): void
    {
        $analytics = MockFlexibleConnectionRecord::query('analytics')
            ->where('id', 1)
            ->first();

        $analytics->status = 'processed';
        $this->assertTrue($analytics->save());

        $this->assertSame('processed', $this->findRow($this->analyticsPdo, 1)['status']);
        $this->assertSame('active', $this->findRow($this->primaryPdo, 1)['status']);

        $this->assertTrue($analytics->delete());
        $this->assertNull($this->findRow($this->analyticsPdo, 1));
        $this->assertNotNull($this->findRow($this->primaryPdo, 1));
    }

    public function testConnectionScopedCountsStayIsolatedAcrossDatabases(): void
    {
        $this->assertSame(2, MockPrimaryConnectionRecord::count());
        $this->assertSame(2, MockAnalyticsConnectionRecord::count());
        $this->assertSame(
            1,
            MockFlexibleConnectionRecord::query('analytics')->where('status', 'pending')->count()
        );
        $this->assertSame(
            1,
            MockFlexibleConnectionRecord::query('primary')->where('status', 'active')->count()
        );
    }

    public function testEmbedLoadsRelatedModelFromItsOwnConnection(): void
    {
        $record = MockAnalyticsConnectionRecord::embed('primaryRecord')->find(1);

        $this->assertSame('Analytics Seed', $record->name);
        $this->assertNotNull($record->primaryRecord);
        $this->assertSame('Primary Seed', $record->primaryRecord->name);
        $this->assertSame('primary', $record->primaryRecord->getConnectionName());
    }

    public function testSaveRemovesUnknownAttributesThatDoNotExistInTheTable(): void
    {
        $record = MockAnalyticsConnectionRecord::find(1);

        $record->description = 'transient';
        $this->assertTrue($record->save());

        $this->assertArrayNotHasKey('description', $record->toArray());
        $this->assertArrayNotHasKey('description', $record->getAttributes());
        $this->assertSame('Analytics Seed', $this->findRow($this->analyticsPdo, 1)['name']);
    }

    public function testCreateDropsUnknownAttributesThatDoNotExistInTheTable(): void
    {
        $record = new MockAnalyticsConnectionRecord([
            'name' => 'Unknown Field Test',
            'status' => 'queued',
            'amount' => 700,
            'description' => 'should disappear',
        ]);

        $this->assertTrue($record->save());
        $this->assertArrayNotHasKey('description', $record->toArray());
        $this->assertSame('Unknown Field Test', $this->findRow($this->analyticsPdo, $record->id)['name']);
        $this->assertSame(3, $this->countRows($this->analyticsPdo));
    }

    private function makeConnection(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $pdo;
    }

    private function createSchema(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE connection_records (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                status TEXT NOT NULL,
                amount INTEGER NOT NULL
            )'
        );
    }

    private function seedPrimaryDatabase(): void
    {
        $statement = $this->primaryPdo->prepare(
            'INSERT INTO connection_records (name, status, amount) VALUES (?, ?, ?)'
        );

        $statement->execute(['Primary Seed', 'active', 900]);
        $statement->execute(['Primary Secondary', 'draft', 125]);
    }

    private function seedAnalyticsDatabase(): void
    {
        $statement = $this->analyticsPdo->prepare(
            'INSERT INTO connection_records (name, status, amount) VALUES (?, ?, ?)'
        );

        $statement->execute(['Analytics Seed', 'pending', 450]);
        $statement->execute(['Analytics Secondary', 'archived', 250]);
    }

    private function countRows(PDO $pdo): int
    {
        return (int) $pdo->query('SELECT COUNT(*) FROM connection_records')->fetchColumn();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findRow(PDO $pdo, int $id): ?array
    {
        $statement = $pdo->prepare('SELECT * FROM connection_records WHERE id = ?');
        $statement->execute([$id]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findRowByName(PDO $pdo, string $name): ?array
    {
        $statement = $pdo->prepare('SELECT * FROM connection_records WHERE name = ?');
        $statement->execute([$name]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    private function setStaticProperty(string $className, string $propertyName, mixed $value): void
    {
        try {
            $reflection = new \ReflectionClass($className);
            $reflection->getProperty($propertyName)->setValue(null, $value);
        } catch (\ReflectionException $e) {
            $this->fail("Failed to set static property {$propertyName}: " . $e->getMessage());
        }
    }
}
