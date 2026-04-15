<?php

namespace Tests\Unit\Builder;

use PDO;
use PHPUnit\Framework\TestCase;
use Phaseolies\Database\Database;
use Phaseolies\DI\Container;
use Tests\Support\MockContainer;
use Tests\Support\Model\MockUser;

class TimeframeOrConditionsModelQueryTest extends TestCase
{
    private ?PDO $pdo;

    protected function setUp(): void
    {
        Container::setInstance(new MockContainer());

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->createTable();
        $this->seedData();
        $this->setupDatabaseConnections();
    }

    protected function tearDown(): void
    {
        $this->pdo = null;
        $this->tearDownDatabaseConnections();
    }

    private function createTable(): void
    {
        $this->pdo->exec("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                status TEXT DEFAULT 'active',
                created_at TEXT
            )
        ");
    }

    private function seedData(): void
    {
        $this->pdo->exec("
            INSERT INTO users (name, status, created_at) VALUES
            ('Alice',   'active',   '2024-01-15 09:00:00'),
            ('Bob',     'inactive', '2024-03-20 14:00:00'),
            ('Charlie', 'active',   '2023-06-10 09:00:00'),
            ('Diana',   'banned',   '2024-06-01 09:00:00')
        ");
    }

    private function setupDatabaseConnections(): void
    {
        $this->setStaticProperty(Database::class, 'connections', []);
        $this->setStaticProperty(Database::class, 'transactions', []);
        $this->setStaticProperty(Database::class, 'connections', ['default' => $this->pdo]);
    }

    private function tearDownDatabaseConnections(): void
    {
        $this->setStaticProperty(Database::class, 'connections', []);
        $this->setStaticProperty(Database::class, 'transactions', []);
    }

    private function setStaticProperty(string $class, string $property, mixed $value): void
    {
        $ref = new \ReflectionClass($class);
        $prop = $ref->getProperty($property);
        $prop->setValue(null, $value);
    }

    public function testOrWhereDateMatchesAdditionalDate(): void
    {
        $users = MockUser::query()
            ->whereDate('created_at', '2024-01-15')
            ->orWhereDate('created_at', '2024-03-20')
            ->get();

        $this->assertCount(2, $users);
    }

    public function testOrWhereDateAloneReturnsMatchingRows(): void
    {
        $users = MockUser::query()
            ->orWhereDate('created_at', '2023-06-10')
            ->get();

        $this->assertCount(1, $users);
        $this->assertEquals('Charlie', $users->first()->name);
    }

    public function testOrWhereDateWithOperatorBroadensResults(): void
    {
        $users = MockUser::query()
            ->whereDate('created_at', '2024-01-15')
            ->orWhereDate('created_at', '>=', '2024-06-01')
            ->get();

        $this->assertCount(2, $users);
    }

    public function testOrWhereMonthCombinesWithAndCondition(): void
    {
        $users = MockUser::query()
            ->whereMonth('created_at', 1)
            ->orWhereMonth('created_at', 3)
            ->get();

        $this->assertCount(2, $users);
    }

    public function testOrWhereMonthAloneReturnsMatchingRows(): void
    {
        $users = MockUser::query()
            ->orWhereMonth('created_at', 6)
            ->get();

        $this->assertCount(2, $users);
    }

    public function testOrWhereMonthWithExplicitOperator(): void
    {
        $users = MockUser::query()
            ->whereStatus('active')
            ->orWhereMonth('created_at', '>=', 6)
            ->get();

        $this->assertCount(3, $users);
    }

    public function testOrWhereYearBroadensResults(): void
    {
        $users = MockUser::query()
            ->whereYear('created_at', 2024)
            ->orWhereYear('created_at', 2023)
            ->get();

        $this->assertCount(4, $users);
    }

    public function testOrWhereYearAloneReturnsMatchingRows(): void
    {
        $users = MockUser::query()
            ->orWhereYear('created_at', 2023)
            ->get();

        $this->assertCount(1, $users);
        $this->assertEquals('Charlie', $users->first()->name);
    }

    public function testOrWhereYearWithExplicitOperator(): void
    {
        $users = MockUser::query()
            ->whereStatus('banned')
            ->orWhereYear('created_at', '>', 2023)
            ->get();

        $this->assertCount(3, $users);
    }

    public function testOrWhereDayCombinesWithAndCondition(): void
    {
        $users = MockUser::query()
            ->whereDay('created_at', 15)
            ->orWhereDay('created_at', 20)
            ->get();

        $this->assertCount(2, $users);
    }

    public function testOrWhereDayAloneReturnsMatchingRows(): void
    {
        $users = MockUser::query()
            ->orWhereDay('created_at', 1)
            ->get();

        $this->assertCount(1, $users);
        $this->assertEquals('Diana', $users->first()->name);
    }

    public function testOrWhereDayWithExplicitOperator(): void
    {
        $users = MockUser::query()
            ->whereStatus('banned')
            ->orWhereDay('created_at', '>=', 15)
            ->get();

        $this->assertCount(3, $users);
    }

    public function testOrWhereTimeCombinesWithAndCondition(): void
    {
        $users = MockUser::query()
            ->whereTime('created_at', '=', '09:00:00')
            ->orWhereTime('created_at', '=', '14:00:00')
            ->get();

        $this->assertCount(4, $users);
    }

    public function testOrWhereTimeAloneReturnsMatchingRows(): void
    {
        $users = MockUser::query()
            ->orWhereTime('created_at', '=', '14:00:00')
            ->get();

        $this->assertCount(1, $users);
        $this->assertEquals('Bob', $users->first()->name);
    }

    public function testOrWhereTimeWithExplicitOperatorBroadensResults(): void
    {
        $users = MockUser::query()
            ->whereStatus('inactive')
            ->orWhereTime('created_at', '>=', '14:00:00')
            ->get();

        $this->assertCount(1, $users);
    }

    public function testOrWhereYesterdayReturnsNoRowsForOldData(): void
    {
        $users = MockUser::query()
            ->whereStatus('active')
            ->orWhereYesterday('created_at')
            ->get();

        $this->assertCount(2, $users);
    }

    public function testChainedOrTimeframeConditionsUnionDates(): void
    {
        $users = MockUser::query()
            ->whereYear('created_at', 2024)
            ->orWhereMonth('created_at', 6)
            ->orWhereDay('created_at', 10)
            ->get();

        $this->assertCount(4, $users);
    }

    public function testOrWhereDateDoesNotReturnRowsForUnmatchedDate(): void
    {
        $users = MockUser::query()
            ->orWhereDate('created_at', '1999-12-31')
            ->get();

        $this->assertCount(0, $users);
    }

    public function testOrWhereYearDoesNotReturnRowsForUnmatchedYear(): void
    {
        $users = MockUser::query()
            ->orWhereYear('created_at', 2000)
            ->get();

        $this->assertCount(0, $users);
    }
}
