<?php

namespace Tests\Unit;

use Phaseolies\Support\Collection;
use Phaseolies\Http\Request;
use Phaseolies\Database\Eloquent\Model;
use Phaseolies\Database\Eloquent\Builder;
use Phaseolies\DI\Container;
use PHPUnit\Framework\TestCase;
use PDOStatement;
use PDOException;
use PDO;
use Phaseolies\Application;
use Phaseolies\Support\UrlGenerator;

class BuilderTest extends TestCase
{
    private $pdo;
    private $pdoStatement;
    private $builder;
    private $modelClass;

    protected function setUp(): void
    {
        $container = new Container();
        $container->bind('request', fn() => new Request());
        $container->bind('url', fn() => new UrlGenerator());

        // Create SQLite in-memory database
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create test tables
        $this->createTestTables();

        $this->modelClass = TestSqliteModel::class;
        $this->builder = new Builder(
            $this->pdo,
            "users",
            $this->modelClass,
            15,
        );
    }

    private function createTestTables(): void
    {
        // Create users table
        $this->pdo->exec("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT UNIQUE,
                age INTEGER,
                status TEXT DEFAULT 'active',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                deleted_at DATETIME NULL
            )
        ");

        // Create posts table
        $this->pdo->exec("
            CREATE TABLE posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                title TEXT NOT NULL,
                content TEXT,
                views INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ");

        // Create profiles table
        $this->pdo->exec("
            CREATE TABLE profiles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER UNIQUE,
                bio TEXT,
                website TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ");

        // Create user_roles pivot table
        $this->pdo->exec("
            CREATE TABLE user_roles (
                user_id INTEGER,
                role_id INTEGER,
                assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id, role_id),
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ");

        // Insert test data
        $this->pdo->exec("
            INSERT INTO users (name, email, age, status) VALUES 
            ('John Doe', 'john@example.com', 25, 'active'),
            ('Jane Smith', 'jane@example.com', 30, 'active'),
            ('Bob Johnson', 'bob@example.com', 35, 'inactive'),
            ('Alice Brown', 'alice@example.com', 28, 'active')
        ");

        $this->pdo->exec("
            INSERT INTO posts (user_id, title, content, views) VALUES 
            (1, 'First Post', 'Content 1', 100),
            (1, 'Second Post', 'Content 2', 50),
            (2, 'Jane Post', 'Content 3', 75),
            (3, 'Bob Post', 'Content 4', 25)
        ");

        $this->pdo->exec("
            INSERT INTO profiles (user_id, bio, website) VALUES 
            (1, 'John Bio', 'https://john.com'),
            (2, 'Jane Bio', 'https://jane.com')
        ");

        $this->pdo->exec("
            INSERT INTO user_roles (user_id, role_id) VALUES 
            (1, 1),
            (1, 2),
            (2, 1),
            (3, 3)
        ");
    }

    public function testConstructor()
    {
        $this->assertInstanceOf(Builder::class, $this->builder);
    }

    public function testBasicSelect()
    {
        $users = $this->builder->get();
        $this->assertInstanceOf(Collection::class, $users);
        $this->assertCount(4, $users);
    }

    public function testSelectSpecificColumns()
    {
        $users = $this->builder->select('id', 'name', 'email')->get();
        $this->assertCount(4, $users);
        $this->assertArrayHasKey('name', $users[0]);
        $this->assertArrayHasKey('email', $users[0]);
    }

    public function testWhereClause()
    {
        $user = $this->builder->where('name', 'John Doe')->first();
        $this->assertInstanceOf(Model::class, $user);
        $this->assertEquals('John Doe', $user->name);
    }

    public function testWhereWithOperator()
    {
        $users = $this->builder->where('age', '>', 25)->get();
        $this->assertCount(3, $users);

        foreach ($users as $user) {
            $this->assertGreaterThan(25, $user->age);
        }
    }

    public function testOrWhere()
    {
        $users = $this->builder
            ->where('name', 'John Doe')
            ->orWhere('name', 'Jane Smith')
            ->get();

        $this->assertCount(2, $users);
        $names = $users->pluck('name')->toArray();
        $this->assertContains('John Doe', $names);
        $this->assertContains('Jane Smith', $names);
    }

    public function testWhereIn()
    {
        $users = $this->builder->whereIn('id', [1, 2, 3])->get();
        $this->assertCount(3, $users);

        $ids = $users->pluck('id')->toArray();
        $this->assertContains(1, $ids);
        $this->assertContains(2, $ids);
        $this->assertContains(3, $ids);
    }

    public function testWhereNull()
    {
        $users = $this->builder->whereNull('deleted_at')->get();
        $this->assertCount(4, $users);
    }

    public function testWhereNotNull()
    {
        // Add a user with deleted_at set
        $this->pdo->exec("INSERT INTO users (name, email, age, deleted_at) VALUES ('Deleted User', 'deleted@example.com', 40, '2023-01-01')");

        $users = $this->builder->whereNotNull('deleted_at')->get();
        $this->assertCount(1, $users);
        $this->assertEquals('Deleted User', $users[0]->name);
    }

    public function testOrderBy()
    {
        $users = $this->builder->orderBy('name', 'ASC')->get();
        $this->assertEquals('Alice Brown', $users[0]->name);
        $this->assertEquals('Bob Johnson', $users[1]->name);
    }

    public function testLimitAndOffset()
    {
        $users = $this->builder->orderBy('id', 'ASC')->limit(2)->offset(1)->get();
        $this->assertCount(2, $users);
        $this->assertEquals(2, $users[0]->id);
        $this->assertEquals(3, $users[1]->id);
    }

    public function testCount()
    {
        $count = $this->builder->count();
        $this->assertEquals(4, $count);

        $activeCount = $this->builder->where('status', 'active')->count();
        $this->assertEquals(3, $activeCount);
    }

    public function testExists()
    {
        $exists = $this->builder->where('name', 'John Doe')->exists();
        $this->assertTrue($exists);

        $notExists = $this->builder->where('name', 'Non Existent')->exists();
        $this->assertFalse($notExists);
    }

    public function testFirst()
    {
        $user = $this->builder->where('email', 'john@example.com')->first();
        $this->assertInstanceOf(Model::class, $user);
        $this->assertEquals('John Doe', $user->name);

        $nonExistent = $this->builder->where('email', 'nonexistent@example.com')->first();
        $this->assertNull($nonExistent);
    }

    public function testInsert()
    {
        $id = $this->builder->insert([
            'name' => 'New User',
            'email' => 'new@example.com',
            'age' => 22
        ]);

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);

        $user = $this->builder->where('id', $id)->first();
        $this->assertEquals('New User', $user->name);
    }

    public function testInsertMany()
    {
        $rows = [
            ['name' => 'User 1', 'email' => 'user1@example.com', 'age' => 20],
            ['name' => 'User 2', 'email' => 'user2@example.com', 'age' => 21],
            ['name' => 'User 3', 'email' => 'user3@example.com', 'age' => 22]
        ];

        $affected = $this->builder->insertMany($rows);
        $this->assertEquals(3, $affected);

        $count = $this->builder->count();
        $this->assertEquals(7, $count); // 4 original + 3 new
    }

    public function testUpdate()
    {
        $result = $this->builder->where('id', 1)->update(['name' => 'Updated Name']);
        $this->assertTrue($result);

        $user = $this->builder->where('id', 1)->first();
        $this->assertEquals('Updated Name', $user->name);
    }

    public function testDelete()
    {
        // Get initial count with fresh builder
        $initialCount = $this->createFreshBuilder()->count();

        // Create a new user to delete
        $userId = $this->createFreshBuilder()->insert([
            'name' => 'User to Delete',
            'email' => 'delete@example.com',
            'age' => 40
        ]);

        // Verify count after insert
        $this->assertEquals($initialCount + 1, $this->createFreshBuilder()->count());

        // Delete the user
        $result = $this->createFreshBuilder()
            ->where('id', $userId)
            ->delete();
        $this->assertTrue($result);

        // Verify count after delete
        $this->assertEquals($initialCount, $this->createFreshBuilder()->count());

        // Verify the user is gone
        $deletedUser = $this->createFreshBuilder()
            ->where('id', $userId)
            ->first();
        $this->assertNull($deletedUser);
    }

    private function createFreshBuilder(): Builder
    {
        return new Builder($this->pdo, "users", $this->modelClass, 15);
    }

    public function testSum()
    {
        $sum = $this->builder->sum('age');
        $this->assertEquals(118, $sum); // 25 + 30 + 35 + 28
    }

    public function testAvg()
    {
        $avg = $this->builder->avg('age');
        $this->assertEquals(29.5, $avg);
    }

    public function testMin()
    {
        $min = $this->builder->min('age');
        $this->assertEquals(25, $min);
    }

    public function testMax()
    {
        $max = $this->builder->max('age');
        $this->assertEquals(35, $max);
    }

    public function testIncrement()
    {
        $initialViews = $this->pdo->query("SELECT views FROM posts WHERE id = 1")->fetchColumn();

        $affected = (new Builder($this->pdo, 'posts', PostModel::class, 15))
            ->where('id', 1)
            ->increment('views', 10);

        $this->assertEquals(1, $affected);

        $newViews = $this->pdo->query("SELECT views FROM posts WHERE id = 1")->fetchColumn();
        $this->assertEquals($initialViews + 10, $newViews);
    }

    public function testDecrement()
    {
        $initialViews = $this->pdo->query("SELECT views FROM posts WHERE id = 1")->fetchColumn();

        $affected = (new Builder($this->pdo, 'posts', PostModel::class, 15))
            ->where('id', 1)
            ->decrement('views', 5);

        $this->assertEquals(1, $affected);

        $newViews = $this->pdo->query("SELECT views FROM posts WHERE id = 1")->fetchColumn();
        $this->assertEquals($initialViews - 5, $newViews);
    }

    public function testJoin()
    {
        $usersWithPosts = $this->builder
            ->select('users.name', 'posts.title')
            ->join('posts', 'users.id', '=', 'posts.user_id')
            ->get()
            ->toArray();

        $this->assertGreaterThan(0, count($usersWithPosts));

        $this->assertArrayHasKey('title', $usersWithPosts[0]);
        $this->assertArrayHasKey('name', $usersWithPosts[0]);
    }

    public function testGroupBy()
    {
        $postsBuilder = new Builder($this->pdo, 'posts', PostModel::class, 15);

        $results = $postsBuilder
            ->select('user_id')
            ->selectRaw('COUNT(*) as post_count')
            ->groupBy('user_id')
            ->get();

        $this->assertGreaterThan(0, $results->count());
        $this->assertArrayHasKey('post_count', $results[0]);
    }

    public function testWhereBetween()
    {
        $users = $this->builder->whereBetween('age', [25, 30])->get();
        $this->assertCount(3, $users); // 25, 28, 30

        foreach ($users as $user) {
            $this->assertGreaterThanOrEqual(25, $user->age);
            $this->assertLessThanOrEqual(30, $user->age);
        }
    }

    public function testWhereDate()
    {
        // Add a user with specific date
        $this->pdo->exec("INSERT INTO users (name, email, age, created_at) VALUES ('Date User', 'date@example.com', 40, '2023-10-15 10:30:00')");

        $users = $this->builder->whereDate('created_at', '2023-10-15')->get();
        $this->assertCount(1, $users);
        $this->assertEquals('Date User', $users[0]->name);
    }

    public function testToSql()
    {
        $sql = $this->builder
            ->where('status', 'active')
            ->orderBy('name', 'ASC')
            ->limit(10)
            ->toSql();

        $this->assertStringContainsString('SELECT', $sql);
        $this->assertStringContainsString('FROM users', $sql);
        $this->assertStringContainsString('WHERE', $sql);
        $this->assertStringContainsString('ORDER BY', $sql);
        $this->assertStringContainsString('LIMIT 10', $sql);
    }

    public function testPaginate()
    {
        $result = $this->builder->paginate(2);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('per_page', $result);
        $this->assertArrayHasKey('current_page', $result);
        $this->assertArrayHasKey('last_page', $result);

        $this->assertCount(2, $result['data']);
        $this->assertEquals(4, $result['total']);
        $this->assertEquals(2, $result['per_page']);
    }

    public function testDistinct()
    {
        // Add some duplicate statuses to test distinct
        $this->pdo->exec("INSERT INTO users (name, email, age, status) VALUES ('Extra User', 'extra@example.com', 40, 'active')");

        $statuses = $this->builder->distinct('status');
        $this->assertInstanceOf(Collection::class, $statuses);
        $this->assertContains('active', $statuses->toArray());
        $this->assertContains('inactive', $statuses->toArray());
    }

    // public function testUpsert()
    // {
    //     // Test insert new record
    //     $affected = $this->builder->upsert(
    //         [['name' => 'Upsert User', 'email' => 'upsert@example.com', 'age' => 45]],
    //         'email'
    //     );

    //     $this->assertEquals(1, $affected);

    //     // Test update existing record
    //     $affected = $this->builder->upsert(
    //         [['name' => 'Updated Upsert User', 'email' => 'upsert@example.com', 'age' => 46]],
    //         'email'
    //     );

    //     $this->assertEquals(2, $affected); // 1 inserted + 1 updated

    //     $user = $this->builder->where('email', 'upsert@example.com')->first();
    //     $this->assertEquals('Updated Upsert User', $user->name);
    //     $this->assertEquals(46, $user->age);
    // }

    public function testComplexQueryBuilding()
    {
        $sql = $this->builder
            ->select('id', 'name', 'email')
            ->where('status', 'active')
            ->whereIn('age', [25, 28, 30])
            ->whereNotNull('email')
            ->orderBy('name', 'DESC')
            ->limit(5)
            ->toSql();

        $this->assertStringContainsString('SELECT id, name, email', $sql);
        $this->assertStringContainsString('WHERE', $sql);
        $this->assertStringContainsString('ORDER BY name DESC', $sql);
        $this->assertStringContainsString('LIMIT 5', $sql);
    }

    public function testRawMethods()
    {
        // Test selectRaw
        $users = $this->builder
            ->selectRaw('COUNT(*) as total_users')
            ->first();

        $this->assertEquals(4, $users->total_users);

        // Test whereRaw
        $users = $this->builder
            ->whereRaw('LENGTH(name) > ?', [8])
            ->get();

        $this->assertGreaterThan(0, $users->count(), 'Should find users with names longer than 8 characters');

        // Debug: Check what's happening with orderByRaw
        $builder = $this->createFreshBuilder();

        // First, let's see the SQL being generated
        $sql = $builder->orderByRaw('RANDOM()')->toSql();
        // echo "Generated SQL: " . $sql . "\n";

        // Test without orderByRaw first to ensure we get all records
        $allUsers = $builder->reset()->get();
        // echo "Total users without ordering: " . $allUsers->count() . "\n";

        // Now test with orderByRaw
        $randomUsers = $builder->reset()->orderByRaw('RANDOM()')->get();
        // echo "Total users with RANDOM(): " . $randomUsers->count() . "\n";

        // Debug the actual data
        // foreach ($randomUsers as $index => $user) {
        //     echo "User $index: ID={$user->id}, Name={$user->name}\n";
        // }

        $this->assertCount(4, $randomUsers, 'Should return all 4 users regardless of ordering');
    }

    public function testConditionalIf()
    {
        $users1 = $this->createFreshBuilder()
            ->if(true, function ($query) {
                $query->where('status', 'active');
            })
            ->get();

        $this->assertCount(3, $users1, 'Should return only active users when condition is true');

        // Test 2: Condition is false - should NOT apply the where clause
        $users2 = $this->createFreshBuilder()
            ->if(false, function ($query) {
                $query->where('status', 'active');
            })
            ->get();

        $this->assertCount(4, $users2, 'Should return all users when condition is false');

        $allUsers = $this->createFreshBuilder()
            ->if(false, function ($query) {
                $query->where('status', 'active');
            })
            ->get();

        $statuses = $allUsers->pluck('status')->toArray();
        $this->assertContains('active', $statuses);
        $this->assertContains('inactive', $statuses);
        $this->assertCount(4, $allUsers);
    }

    public function testRelationships()
    {
        // Test one-to-one relationship manually
        $user = $this->createFreshBuilder()->where('id', 1)->first();

        // Manually load the profile using the relationship query
        $profile = (new Builder($this->pdo, 'profiles', ProfileModel::class, 15))
            ->where('user_id', $user->id)
            ->first();

        // echo "Manual profile: " . ($profile ? $profile->bio : 'NULL') . "\n";
        $this->assertNotNull($profile);
        $this->assertEquals('John Bio', $profile->bio);

        // Test one-to-many relationship manually
        $posts = (new Builder($this->pdo, 'posts', PostModel::class, 15))
            ->where('user_id', $user->id)
            ->get();

        // echo "Manual posts count: " . $posts->count() . "\n";
        $this->assertGreaterThan(0, $posts->count());
        $this->assertInstanceOf(PostModel::class, $posts[0]);
    }

    public function testChunkProcessing()
    {
        $chunkCount = 0;
        $totalProcessed = 0;

        $this->builder->chunk(2, function ($chunk) use (&$chunkCount, &$totalProcessed) {
            $chunkCount++;
            $totalProcessed += $chunk->count();
        });

        $this->assertEquals(2, $chunkCount); // 4 records in 2 chunks of 2
        $this->assertEquals(4, $totalProcessed);
    }

    public function testToDictionary()
    {
        $dictionary = $this->builder->toDictionary('id', 'name');

        $this->assertInstanceOf(Collection::class, $dictionary);
        $this->assertEquals('John Doe', $dictionary[1]);
        $this->assertEquals('Jane Smith', $dictionary[2]);
    }

    public function testToTree()
    {
        // Create hierarchical data
        $this->pdo->exec("
            CREATE TABLE categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                parent_id INTEGER NULL,
                FOREIGN KEY (parent_id) REFERENCES categories(id)
            )
        ");

        $this->pdo->exec("
            INSERT INTO categories (name, parent_id) VALUES 
            ('Electronics', NULL),
            ('Computers', 1),
            ('Laptops', 2),
            ('Desktops', 2),
            ('Phones', 1),
            ('Smartphones', 5)
        ");

        $categoryBuilder = new Builder($this->pdo, 'categories', CategoryModel::class, 15);
        $tree = $categoryBuilder->toTree('id', 'parent_id');

        $this->assertInstanceOf(Collection::class, $tree);
        $this->assertCount(1, $tree); // Root level
        $this->assertEquals('Electronics', $tree[0]->name);
        $this->assertTrue($tree[0]->relationLoaded('children'));
    }

    // public function testCheckSqliteVersion()
    // {
    //     $version = $this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
    //     echo "SQLite Version: " . $version . "\n";
    //     echo "Version comparison: " . version_compare($version, '3.38.0') . "\n";

    //     // -1 means older, 0 means equal, 1 means newer
    //     if (version_compare($version, '3.38.0') < 0) {
    //         echo "Your SQLite version ({$version}) is older than 3.38.0\n";
    //     } else {
    //         echo "Your SQLite version ({$version}) supports JSON\n";
    //     }
    // }

    public function testJsonMethods()
    {
        // Check if JSON1 extension is available
        try {
            $result = $this->pdo->query("SELECT json('{\"test\": \"value\"}')")->fetchColumn();
            $hasJson1 = $result !== false;
        } catch (PDOException $e) {
            $hasJson1 = false;
        }

        if (!$hasJson1) {
            $version = $this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
            $this->markTestSkipped("SQLite version {$version} doesn't have JSON1 extension");
            return;
        }

        // Test with available JSON functions
        $this->pdo->exec("
        CREATE TABLE products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            attributes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

        $this->pdo->exec("
        INSERT INTO products (name, attributes) VALUES 
        ('Product 1', '{\"color\": \"red\", \"size\": \"large\"}'),
        ('Product 2', '{\"color\": \"blue\", \"size\": \"medium\"}')
    ");

        $productBuilder = new Builder($this->pdo, 'products', ProductModel::class, 15);

        // Test with available JSON functions
        $redProducts = $productBuilder->whereRaw("json_extract(attributes, '$.color') = ?", ['red'])->get();
        $this->assertCount(1, $redProducts);
        $this->assertEquals('Product 1', $redProducts[0]->name);
    }

    public function testTimeframeMethods()
    {
        // Test date-based queries
        $todayUsers = $this->builder->whereToday('created_at')->get();
        $this->assertIsIterable($todayUsers);

        $thisYearUsers = $this->builder->whereThisYear('created_at')->get();
        $this->assertIsIterable($thisYearUsers);
    }

    public function testMovingAverageAndDifference()
    {
        $postsBuilder = new Builder($this->pdo, 'posts', PostModel::class, 15);

        // Test moving average (SQLite supports window functions)
        $postsWithMovingAvg = $postsBuilder
            ->movingAverage('views', 3, 'created_at', 'moving_avg')
            ->get();

        $this->assertGreaterThan(0, $postsWithMovingAvg->count());

        // Test moving difference
        $postsWithDiff = $postsBuilder
            ->movingDifference('views', 'created_at', 'views_diff')
            ->get();

        $this->assertGreaterThan(0, $postsWithDiff->count());
    }

    public function testTransactionSupport()
    {
        try {
            $this->pdo->beginTransaction();

            $id = $this->builder->insert([
                'name' => 'Transaction User',
                'email' => 'transaction@example.com',
                'age' => 50
            ]);

            $this->pdo->commit();

            $user = $this->builder->where('id', $id)->first();
            $this->assertEquals('Transaction User', $user->name);
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function testErrorHandling()
    {
        $this->expectException(PDOException::class);

        // Try to insert duplicate email
        $this->builder->insert([
            'name' => 'Duplicate',
            'email' => 'john@example.com', // Already exists
            'age' => 20
        ]);
    }

    public function testPerformanceWithLargeDataset()
    {
        // Insert larger dataset for performance testing
        $rows = [];
        for ($i = 0; $i < 100; $i++) {
            $rows[] = [
                'name' => "User $i",
                'email' => "user$i@example.com",
                'age' => rand(20, 60),
                'status' => $i % 2 == 0 ? 'active' : 'inactive'
            ];
        }

        $startTime = microtime(true);
        $affected = $this->builder->insertMany($rows);
        $insertTime = microtime(true) - $startTime;

        $this->assertEquals(100, $affected);
        $this->assertLessThan(1.0, $insertTime); // Should complete within 1 second

        // Test query performance
        $startTime = microtime(true);
        $users = $this->builder->where('status', 'active')->get();
        $queryTime = microtime(true) - $startTime;

        $this->assertLessThan(0.5, $queryTime); // Should complete within 0.5 seconds
    }

    public function testModelMethods()
    {
        $user = $this->builder->where('id', 1)->first();

        // Test toArray
        $array = $user->toArray();
        $this->assertIsArray($array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('email', $array);

        // Test toJson
        $json = $user->toJson();
        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertEquals($user->name, $decoded['name']);

        // Test attribute access
        $this->assertEquals($user->name, $user['name']);
        $this->assertTrue(isset($user['email']));
    }

    public function testCollectionMethods()
    {
        $users = $this->builder->get();

        // Test pluck
        $names = $users->pluck('name');
        $this->assertInstanceOf(Collection::class, $names);
        $this->assertContains('John Doe', $names->toArray());

        // Test filter
        $activeUsers = $users->filter(function ($user) {
            return $user->status === 'active';
        });
        $this->assertCount(3, $activeUsers);

        // Test map
        $userNames = $users->map(function ($user) {
            return strtoupper($user->name);
        });
        $this->assertContains('JOHN DOE', $userNames->toArray());
    }

    protected function tearDown(): void
    {
        // Clean up
        $this->pdo = null;
        $this->builder = null;
    }
}

// Test models for SQLite tests
class TestSqliteModel extends Model
{
    protected $table = "users";
    protected $primaryKey = 'id';
    protected $creatable = ['name', 'email', 'age', 'status'];

    public function posts()
    {
        return $this->linkMany(PostModel::class, "user_id", "id");
    }

    public function profile()
    {
        return $this->linkOne(ProfileModel::class, "user_id", "id");
    }

    public function roles()
    {
        return $this->bindToMany(RoleModel::class, "user_id", "role_id", "user_roles");
    }
}

class PostModel extends Model
{
    protected $table = "posts";
    protected $creatable = ['user_id', 'title', 'content', 'views'];
}

class ProfileModel extends Model
{
    protected $table = "profiles";
    protected $creatable = ['user_id', 'bio', 'website'];
}

class RoleModel extends Model
{
    protected $table = "roles";
}

class CategoryModel extends Model
{
    protected $table = "categories";
    protected $creatable = ['name', 'parent_id'];
}

class ProductModel extends Model
{
    protected $table = "products";
    protected $creatable = ['name', 'attributes'];
}
