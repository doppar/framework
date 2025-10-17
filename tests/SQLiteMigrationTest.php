<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Phaseolies\Database\Migration\Blueprint;
use Phaseolies\Database\Migration\Grammars\SQLiteGrammar;
use Phaseolies\DI\Container;
use PDO;

/**
 * Test SQLite Migration Grammar
 * 
 * Specifically tests that UNIQUE constraints are properly added to column definitions
 * in SQLite, since SQLite doesn't support ALTER TABLE ADD CONSTRAINT UNIQUE.
 */
class SQLiteMigrationTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        // Create SQLite in-memory database
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Setup container with DB connection
        $container = new Container();
        $container->bind('db', fn() => new class($this->pdo) {
            private $pdo;
            public function __construct($pdo) {
                $this->pdo = $pdo;
            }
            public function getConnection() {
                return $this->pdo;
            }
        });
        
        Container::setInstance($container);
    }
    
    protected function tearDown(): void
    {
        // Clear container instance
        $reflection = new \ReflectionClass(Container::class);
        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }

    /**
     * Test that UNIQUE constraint is added to column definition in CREATE TABLE
     */
    public function testUniqueConstraintInColumnDefinition()
    {
        // Create a blueprint
        $blueprint = new Blueprint('test_table', 'sqlite');
        
        // Add columns
        $blueprint->id();
        $blueprint->string('email')->unique();
        $blueprint->string('name');
        $blueprint->timestamps();
        
        // Generate SQL
        $sql = $blueprint->toSql();
        
        // Assert that UNIQUE is in the column definition (without backticks in column name)
        $this->assertStringContainsString('email TEXT UNIQUE NOT NULL', $sql);
        $this->assertStringNotContainsString('name TEXT UNIQUE', $sql);
        
        // Execute the SQL to ensure it's valid
        $this->pdo->exec($sql);
        
        // Verify table was created
        $stmt = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='test_table'");
        $this->assertNotFalse($stmt->fetch());
    }

    /**
     * Test that upsert works with UNIQUE constraint
     */
    public function testUpsertWithUniqueConstraint()
    {
        // Create table with UNIQUE constraint
        $this->pdo->exec("
            CREATE TABLE books (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT UNIQUE NOT NULL,
                price REAL NOT NULL,
                description TEXT
            )
        ");
        
        // Insert first record
        $stmt = $this->pdo->prepare("
            INSERT INTO books (title, price, description) 
            VALUES (?, ?, ?)
            ON CONFLICT(title) DO UPDATE SET 
                price = EXCLUDED.price,
                description = EXCLUDED.description
        ");
        
        $stmt->execute(['Database Design', 45.00, 'First edition']);
        
        // Verify insert
        $result = $this->pdo->query("SELECT COUNT(*) FROM books")->fetchColumn();
        $this->assertEquals(1, $result);
        
        // Upsert same title (should update, not insert)
        $stmt->execute(['Database Design', 50.00, 'Second edition']);
        
        // Verify still only 1 record
        $result = $this->pdo->query("SELECT COUNT(*) FROM books")->fetchColumn();
        $this->assertEquals(1, $result);
        
        // Verify price was updated
        $stmt = $this->pdo->query("SELECT price, description FROM books WHERE title = 'Database Design'");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals(50.00, $row['price']);
        $this->assertEquals('Second edition', $row['description']);
    }

    /**
     * Test that upsert FAILS without UNIQUE constraint
     */
    public function testUpsertFailsWithoutUniqueConstraint()
    {
        // Create table WITHOUT UNIQUE constraint
        $this->pdo->exec("
            CREATE TABLE products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                price REAL NOT NULL
            )
        ");
        
        // Attempt upsert - should fail
        $this->expectException(\PDOException::class);
        $this->expectExceptionMessageMatches('/ON CONFLICT|UNIQUE/i');
        
        $stmt = $this->pdo->prepare("
            INSERT INTO products (name, price) 
            VALUES (?, ?)
            ON CONFLICT(name) DO UPDATE SET price = EXCLUDED.price
        ");
        
        $stmt->execute(['Product A', 100.00]);
    }

    /**
     * Test Blueprint with multiple UNIQUE columns
     */
    public function testMultipleUniqueColumns()
    {
        $blueprint = new Blueprint('users', 'sqlite');
        
        $blueprint->id();
        $blueprint->string('email')->unique();
        $blueprint->string('username')->unique();
        $blueprint->string('phone')->unique();
        $blueprint->string('name');
        
        $sql = $blueprint->toSql();
        
        // All three should have UNIQUE
        $this->assertStringContainsString('email TEXT UNIQUE NOT NULL', $sql);
        $this->assertStringContainsString('username TEXT UNIQUE NOT NULL', $sql);
        $this->assertStringContainsString('phone TEXT UNIQUE NOT NULL', $sql);
        
        // name should NOT have UNIQUE (use word boundary to avoid matching 'username')
        $this->assertMatchesRegularExpression('/,\s*name TEXT NOT NULL\s*\)/', $sql); // name without UNIQUE
        
        // Execute to verify it's valid SQL
        $this->pdo->exec($sql);
    }

    /**
     * Test that UNIQUE works with nullable columns
     */
    public function testUniqueWithNullable()
    {
        $blueprint = new Blueprint('profiles', 'sqlite');
        
        $blueprint->id();
        $blueprint->string('website')->unique()->nullable();
        $blueprint->string('bio')->nullable();
        
        $sql = $blueprint->toSql();
        
        // website should have UNIQUE and NULL
        $this->assertStringContainsString('website TEXT UNIQUE NULL', $sql);
        
        // bio should have NULL but not UNIQUE
        $this->assertStringContainsString('bio TEXT NULL', $sql);
        $this->assertStringNotContainsString('bio TEXT UNIQUE', $sql);
        
        $this->pdo->exec($sql);
    }

    /**
     * Test UNIQUE with different column types
     */
    public function testUniqueWithDifferentTypes()
    {
        $blueprint = new Blueprint('mixed_types', 'sqlite');
        
        $blueprint->id();
        $blueprint->string('code')->unique();
        $blueprint->integer('serial_number')->unique();
        $blueprint->decimal('sku', 10, 2)->unique();
        
        $sql = $blueprint->toSql();
        
        $this->assertStringContainsString('code TEXT UNIQUE NOT NULL', $sql);
        $this->assertStringContainsString('serial_number INTEGER UNIQUE NOT NULL', $sql);
        $this->assertStringContainsString('sku REAL UNIQUE NOT NULL', $sql);
        
        $this->pdo->exec($sql);
    }

    /**
     * Test that the fix doesn't break existing functionality
     */
    public function testBackwardCompatibility()
    {
        // Test table without any UNIQUE constraints
        $blueprint = new Blueprint('simple_table', 'sqlite');
        
        $blueprint->id();
        $blueprint->string('name');
        $blueprint->integer('age');
        $blueprint->timestamps();
        
        $sql = $blueprint->toSql();
        
        // Should not contain UNIQUE anywhere except in id's PRIMARY KEY
        $this->assertStringNotContainsString('name TEXT UNIQUE', $sql);
        $this->assertStringNotContainsString('age INTEGER UNIQUE', $sql);
        
        $this->pdo->exec($sql);
        
        // Insert duplicate names should work
        $this->pdo->exec("INSERT INTO simple_table (name, age) VALUES ('John', 25)");
        $this->pdo->exec("INSERT INTO simple_table (name, age) VALUES ('John', 30)");
        
        $count = $this->pdo->query("SELECT COUNT(*) FROM simple_table WHERE name = 'John'")->fetchColumn();
        $this->assertEquals(2, $count);
    }

    /**
     * Test real-world scenario: books table with upsert
     */
    public function testRealWorldBooksScenario()
    {
        // Simulate the books migration
        $blueprint = new Blueprint('books', 'sqlite');
        
        $blueprint->id();
        $blueprint->string('title')->unique();
        $blueprint->decimal('price', 10, 2);
        $blueprint->text('description')->nullable();
        $blueprint->timestamps();
        
        $sql = $blueprint->toSql();
        $this->pdo->exec($sql);
        
        // Verify UNIQUE constraint exists
        $tableInfo = $this->pdo->query("PRAGMA table_info(books)")->fetchAll(PDO::FETCH_ASSOC);
        $titleColumn = array_filter($tableInfo, fn($col) => $col['name'] === 'title');
        $this->assertNotEmpty($titleColumn);
        
        // Test upsert operations
        $stmt = $this->pdo->prepare("
            INSERT INTO books (title, price, description) 
            VALUES (?, ?, ?)
            ON CONFLICT(title) DO UPDATE SET 
                price = EXCLUDED.price,
                description = EXCLUDED.description
        ");
        
        // First insert
        $stmt->execute(['Database Design 2025', 45.00, 'Modern database patterns']);
        $count = $this->pdo->query("SELECT COUNT(*) FROM books")->fetchColumn();
        $this->assertEquals(1, $count);
        
        // Second insert with same title (should update)
        $stmt->execute(['Database Design 2025', 50.00, 'Updated edition']);
        $count = $this->pdo->query("SELECT COUNT(*) FROM books")->fetchColumn();
        $this->assertEquals(1, $count, 'Should not create duplicate');
        
        // Verify update
        $row = $this->pdo->query("SELECT * FROM books WHERE title = 'Database Design 2025'")->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals(50.00, $row['price']);
        $this->assertEquals('Updated edition', $row['description']);
        
        // Third insert with different title (should insert)
        $stmt->execute(['Advanced SQL', 60.00, 'SQL mastery']);
        $count = $this->pdo->query("SELECT COUNT(*) FROM books")->fetchColumn();
        $this->assertEquals(2, $count);
    }

    /**
     * Test that SQLiteGrammar properly handles UNIQUE in compileCreateTable
     */
    public function testSQLiteGrammarCompileCreateTable()
    {
        $blueprint = new Blueprint('test_grammar', 'sqlite');
        
        $blueprint->id();
        $blueprint->string('unique_col')->unique();
        $blueprint->string('normal_col');
        
        $sql = $blueprint->toSql();
        
        // Verify the SQL structure
        $this->assertStringStartsWith('CREATE TABLE `test_grammar`', $sql);
        $this->assertStringContainsString('id INTEGER PRIMARY KEY', $sql);
        $this->assertStringContainsString('unique_col TEXT UNIQUE NOT NULL', $sql);
        $this->assertStringContainsString('normal_col TEXT NOT NULL', $sql);
        
        // Verify it's valid SQL
        $this->pdo->exec($sql);
        
        // Get the actual table schema
        $schema = $this->pdo->query("SELECT sql FROM sqlite_master WHERE name='test_grammar'")->fetchColumn();
        $this->assertStringContainsString('UNIQUE', $schema);
    }

    /**
     * Test edge case: UNIQUE with default value
     */
    public function testUniqueWithDefault()
    {
        $blueprint = new Blueprint('defaults_table', 'sqlite');
        
        $blueprint->id();
        $blueprint->string('status')->unique()->default('active');
        
        $sql = $blueprint->toSql();
        
        // Should have UNIQUE, NOT NULL, and DEFAULT
        $this->assertStringContainsString("status TEXT UNIQUE NOT NULL DEFAULT 'active'", $sql);
        
        $this->pdo->exec($sql);
    }

    /**
     * Test that index() and unique() are different
     */
    public function testIndexVsUnique()
    {
        $blueprint = new Blueprint('index_test', 'sqlite');
        
        $blueprint->id();
        $blueprint->string('indexed_col')->index();
        $blueprint->string('unique_col')->unique();
        
        $sql = $blueprint->toSql();
        
        // unique_col should have UNIQUE in column definition
        $this->assertStringContainsString('unique_col TEXT UNIQUE NOT NULL', $sql);
        
        // indexed_col should NOT have UNIQUE (index is created separately)
        $this->assertStringNotContainsString('indexed_col TEXT UNIQUE', $sql);
        
        $this->pdo->exec($sql);
        
        // Verify index was created
        $indexes = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='index' AND tbl_name='index_test'")->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains('idx_index_test_indexed_col', $indexes);
    }
}
