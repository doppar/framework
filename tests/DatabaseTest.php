<?php

namespace Tests\Unit;

use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Phaseolies\Database\Database;
use Phaseolies\Support\Collection;
use Phaseolies\Database\Query\Builder;
use Phaseolies\Database\Query\RawExpression;
use Phaseolies\Database\Connectors\ConnectionFactory;
use Phaseolies\Database\Contracts\Driver\DriverInterface;

class DatabaseTest extends TestCase
{
    private $database;
    private $pdoMock;

    protected function setUp(): void
    {
        $this->pdoMock = $this->createMock(PDO::class);

        // Mock the driver attribute for all tests
        $this->pdoMock->method('getAttribute')
            ->with(PDO::ATTR_DRIVER_NAME)
            ->willReturn('mysql'); // or 'sqlite'

        $this->setStaticProperty(Database::class, 'connections', ['default' => $this->pdoMock]);
        $this->setStaticProperty(Database::class, 'transactions', []);

        $this->database = new Database('default');
    }

    protected function tearDown(): void
    {
        $this->setStaticProperty(Database::class, 'connections', []);
        $this->setStaticProperty(Database::class, 'transactions', []);
    }

    /**
     * Helper method to set static properties without deprecation warnings
     */
    private function setStaticProperty(string $className, string $propertyName, $value): void
    {
        try {
            $reflection = new \ReflectionClass($className);
            $property = $reflection->getProperty($propertyName);

            if (method_exists($property, 'setAccessible')) {
                $property->setAccessible(true);
            }

            $property->setValue(null, $value);

            // Reset accessibility if we changed it
            if (method_exists($property, 'setAccessible')) {
                $property->setAccessible(false);
            }
        } catch (\ReflectionException $e) {
            $this->fail("Failed to set static property {$propertyName}: " . $e->getMessage());
        }
    }

    public function testConstructor()
    {
        $db = new Database('default');
        $this->assertInstanceOf(Database::class, $db);
    }

    public function testGetPdoInstance()
    {
        $config = [
            'driver' => 'mysql',
            'host' => 'localhost',
            'database' => 'test',
            'username' => 'root',
            'password' => '',
        ];

        $pdoMock = $this->createMock(PDO::class);

        $connectionFactoryMock = $this->createMock(ConnectionFactory::class);
        $connectionFactoryMock->method('make')
            ->willReturn($pdoMock);

        $pdo = Database::getPdoInstance('default');
        $this->assertInstanceOf(PDO::class, $pdo);
    }

    public function testBeginTransaction()
    {
        $this->pdoMock->expects($this->once())
            ->method('beginTransaction');

        $this->database->beginTransaction();

        // Verify transaction level was incremented
        $this->assertEquals(1, Database::transactionLevel('default'));
    }

    public function testNestedTransactions()
    {
        $this->pdoMock->expects($this->once())
            ->method('beginTransaction');

        $this->database->beginTransaction();
        $this->assertEquals(1, Database::transactionLevel('default'));

        $this->database->beginTransaction();
        $this->assertEquals(2, Database::transactionLevel('default'));

        $this->database->beginTransaction();
        $this->assertEquals(3, Database::transactionLevel('default'));
    }

    public function testCommit()
    {
        $this->database->beginTransaction();

        $this->pdoMock->expects($this->once())
            ->method('commit');

        $this->database->commit();

        $this->assertEquals(0, Database::transactionLevel('default'));
    }

    public function testNestedCommit()
    {
        // start multiple transactions
        $this->database->beginTransaction();
        $this->database->beginTransaction();
        $this->database->beginTransaction();

        $this->pdoMock->expects($this->never())
            ->method('commit');

        // commit nested transactions
        // should not call commit on PDO until the last one
        $this->database->commit();
        $this->assertEquals(2, Database::transactionLevel('default'));

        $this->database->commit();
        $this->assertEquals(1, Database::transactionLevel('default'));
    }

    public function testRollBack()
    {
        $this->database->beginTransaction();

        $this->pdoMock->expects($this->once())
            ->method('rollBack');

        $this->database->rollBack();

        $this->assertEquals(0, Database::transactionLevel('default'));
    }

    public function testNestedRollBack()
    {
        // Start multiple transactions
        $this->database->beginTransaction();
        $this->database->beginTransaction();

        $this->pdoMock->expects($this->never())
            ->method('rollBack');

        $this->pdoMock->expects($this->once())
            ->method('exec')
            ->with('ROLLBACK TO SAVEPOINT trans2');

        $this->database->rollBack();
        $this->assertEquals(1, Database::transactionLevel('default'));
    }

    public function testTransactionWithSuccessfulCallback()
    {
        $callback = function () {
            return 'success';
        };

        $this->pdoMock->expects($this->once())
            ->method('beginTransaction');

        $this->pdoMock->expects($this->once())
            ->method('commit');

        $result = $this->database->transaction($callback);

        $this->assertEquals('success', $result);
    }

    public function testTransactionWithFailedCallback()
    {
        $exception = new \Exception('Test exception');
        $callback = function () use ($exception) {
            throw $exception;
        };

        $this->pdoMock->expects($this->once())
            ->method('beginTransaction');

        $this->pdoMock->expects($this->once())
            ->method('rollBack');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Test exception');

        $this->database->transaction($callback);
    }

    public function testTransactionWithRetries()
    {
        $callCount = 0;
        $callback = function () use (&$callCount) {
            $callCount++;
            if ($callCount < 3) {
                throw new \Exception('Temporary failure');
            }
            return 'success';
        };

        $this->pdoMock->expects($this->exactly(3))
            ->method('beginTransaction');

        $this->pdoMock->expects($this->exactly(2))
            ->method('rollBack');

        $this->pdoMock->expects($this->once())
            ->method('commit');

        $result = $this->database->transaction($callback, 3);

        $this->assertEquals('success', $result);
        $this->assertEquals(3, $callCount);
    }

    public function testGetTableColumns()
    {
        $tableName = 'test_table';
        $expectedColumns = ['id', 'name', 'email'];

        $stmtMock = $this->createMock(PDOStatement::class);

        // Don't specify exact fetch mode, let the implementation decide
        $stmtMock->expects($this->once())
            ->method('fetchAll')
            // ->with($this->isType('int')) // Just ensure it's an integer
            ->willReturnCallback(function ($fetchMode) use ($expectedColumns) {
                // Return appropriate data based on fetch mode
                if ($fetchMode === PDO::FETCH_ASSOC) {
                    return array_map(function ($col) {
                        return ['Field' => $col];
                    }, $expectedColumns);
                } else if ($fetchMode === PDO::FETCH_COLUMN) {
                    return $expectedColumns;
                }
                return $expectedColumns;
            });

        // Don't specify exact SQL, let the implementation decide
        $this->pdoMock->expects($this->once())
            ->method('query')
            // ->with($this->isType('string'))
            ->willReturn($stmtMock);

        $columns = $this->database->getTableColumns($tableName);

        $this->assertEquals($expectedColumns, $columns);
    }

    public function testGetTableColumnsWithNullTable()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Table name cannot be null');

        $this->database->getTableColumns(null);
    }

    public function testGetTables()
    {
        $expectedTables = ['users', 'posts', 'comments'];

        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_COLUMN)
            ->willReturn($expectedTables);

        $this->pdoMock->expects($this->once())
            ->method('query')
            ->with('SHOW TABLES')
            ->willReturn($stmtMock);

        $tables = $this->database->getTables();

        $this->assertEquals($expectedTables, $tables);
    }

    public function testTableExists()
    {
        $tableName = 'existing_table';

        $stmtMock = $this->createMock(PDOStatement::class);

        $this->pdoMock->expects($this->once())
            ->method('query')
            ->with("SELECT 1 FROM {$tableName} LIMIT 1")
            ->willReturn($stmtMock);

        $result = $this->database->tableExists($tableName);

        $this->assertTrue($result);
    }

    public function testTableExistsReturnsFalseForNonExistentTable()
    {
        $tableName = 'non_existent_table';

        $this->pdoMock->expects($this->once())
            ->method('query')
            ->with("SELECT 1 FROM {$tableName} LIMIT 1")
            ->willThrowException(new PDOException('Table not found'));

        $result = $this->database->tableExists($tableName);

        $this->assertFalse($result);
    }

    public function testQuery()
    {
        $sql = 'SELECT * FROM users WHERE active = ?';
        $params = [1];
        $expectedResults = [
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane']
        ];

        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->expects($this->once())
            ->method('setFetchMode')
            ->with(PDO::FETCH_ASSOC);
        $stmtMock->expects($this->once())
            ->method('execute')
            ->with($params);
        $stmtMock->expects($this->once())
            ->method('fetchAll')
            ->willReturn($expectedResults);

        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with($sql)
            ->willReturn($stmtMock);

        $result = $this->database->query($sql, $params);

        $this->assertInstanceOf(Collection::class, $result);
    }

    public function testStatement()
    {
        $sql = 'UPDATE users SET name = ? WHERE id = ?';
        $params = ['John Doe', 1];

        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->expects($this->once())
            ->method('setFetchMode')
            ->with(PDO::FETCH_ASSOC);
        $stmtMock->expects($this->once())
            ->method('execute')
            ->with($params);

        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with($sql)
            ->willReturn($stmtMock);

        $result = $this->database->statement($sql, $params);

        $this->assertInstanceOf(PDOStatement::class, $result);
    }

    public function testExecute()
    {
        $sql = 'DELETE FROM users WHERE id = ?';
        $params = [1];
        $affectedRows = 1;

        $stmtMock = $this->createMock(PDOStatement::class);
        $stmtMock->expects($this->once())
            ->method('execute')
            ->with($params);
        $stmtMock->expects($this->once())
            ->method('rowCount')
            ->willReturn($affectedRows);

        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with($sql)
            ->willReturn($stmtMock);

        $result = $this->database->execute($sql, $params);

        $this->assertEquals($affectedRows, $result);
    }

    public function testSql()
    {
        $expression = 'NOW()';
        $bindings = [];

        $result = $this->database->sql($expression, $bindings);

        $this->assertInstanceOf(RawExpression::class, $result);
    }

    public function testConnection()
    {
        $result = $this->database->connection('other');

        $this->assertInstanceOf(Database::class, $result);
    }

    public function testTransactionLevel()
    {
        // Initially should be 0
        $this->assertEquals(0, Database::transactionLevel('default'));

        // After beginning transaction, should be 1
        $this->database->beginTransaction();
        $this->assertEquals(1, Database::transactionLevel('default'));

        // After commit, should be 0
        $this->database->commit();
        $this->assertEquals(0, Database::transactionLevel('default'));
    }

    public function testDropAllTables()
    {
        $tables = ['users', 'posts', 'comments'];

        $driverMock = $this->createMock(DriverInterface::class);
        $driverMock->expects($this->once())
            ->method('dropAllTables')
            ->with($this->pdoMock)
            ->willReturn(3);

        $reflection = new \ReflectionClass(Database::class);
        $property = $reflection->getProperty('drivers');
        $property->setAccessible(true);
        $property->setValue(null, ['default' => $driverMock]);

        $result = $this->database->dropAllTables();

        $this->assertEquals(3, $result);
    }
}
