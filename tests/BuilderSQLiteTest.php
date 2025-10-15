<?php

namespace Tests\Unit;

use Phaseolies\Support\Collection;
use Phaseolies\Http\Request;
use Phaseolies\Database\Eloquent\Model;
use Phaseolies\Database\Eloquent\Builder;
use Phaseolies\DI\Container;
use PHPUnit\Framework\TestCase;
use PDOException;
use PDO;
use Phaseolies\Support\UrlGenerator;

class BuilderSQLiteTest extends TestCase
{
    private $pdo;
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

        // Roles table (many-to-many)
        $this->pdo->exec("
            CREATE TABLE roles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL
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

        // Insert roles
        $this->pdo->exec("
            INSERT INTO roles (name) VALUES 
            ('Admin'),
            ('Editor'),
            ('Viewer')
        ");

        $this->pdo->exec("
            INSERT INTO user_roles (user_id, role_id) VALUES 
            (1, 1),
            (1, 2),
            (2, 1),
            (3, 3)
        ");
    }

    public function testGetConnection()
    {
        $connection = $this->builder->getConnection();
        $this->assertInstanceOf(PDO::class, $connection);
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

    public function testWithoutEncryptionMethod()
    {
        $builder = $this->builder->withoutEncryption();
        $this->assertInstanceOf(Builder::class, $builder);
    }

    public function testResetMethod()
    {
        $builder = $this->builder
            ->where('name', 'John Doe')
            ->orderBy('name', 'DESC')
            ->limit(5)
            ->reset();

        // After reset, should return all records
        $users = $builder->get();
        $this->assertCount(4, $users);
    }

    public function testUseRawMethod()
    {
        $users = $this->builder->useRaw(
            "SELECT * FROM users WHERE name LIKE ?",
            ['%John%']
        );

        $this->assertCount(2, $users);
        $this->assertEquals('John Doe', $users[0]->name);
    }

    public function testWhereBetweenMethods()
    {
        // Test whereBetween
        $users = $this->builder->whereBetween('id', [1, 2])->get();
        $this->assertCount(2, $users);

        // Test orWhereBetween
        $users = $this->builder->where('id', 1)->orWhereBetween('id', [2, 3])->get();
        $this->assertCount(3, $users);

        // Test whereNotBetween
        $users = $this->builder->whereNotBetween('id', [2, 3])->get();
        $this->assertCount(1, $users);
        $this->assertEquals(1, $users[0]->id);
    }

    public function testDynamicWhereMethods()
    {
        // Test dynamic where methods (whereName, whereEmail, etc.)
        $users = $this->builder->whereName('John Doe')->get();
        $this->assertCount(1, $users);
        $this->assertEquals('John Doe', $users[0]->name);

        $users = $this->builder->whereEmail('john@example.com')->get();
        $this->assertCount(1, $users);
        $this->assertEquals('john@example.com', $users[0]->email);
    }

    public function testQueryUtilsMethods()
    {
        // Test toDictionary
        $dict = $this->builder->toDictionary('id', 'name');
        $this->assertEquals('John Doe', $dict[1]);
        $this->assertEquals('Jane Smith', $dict[2]);

        // Test toRatio
        $postsBuilder = new Builder($this->pdo, 'posts', PostModel::class, 15);
        $ratio = $postsBuilder->toRatio('id', 'user_id', 'post_ratio');
        $this->assertInstanceOf(Collection::class, $ratio);

        // Test random
        $randomUsers = $this->builder->random(2)->get();
        $this->assertCount(2, $randomUsers);
    }

    public function testBigDataProcessingMethods()
    {
        $processed = 0;

        // Test chunk - there are 4 users in the test database
        $this->builder->chunk(2, function ($chunk) use (&$processed) {
            $processed += $chunk->count();
        });

        $this->assertEquals(4, $processed); // Changed from 3 to 4

        // Test cursor
        $processed = 0;
        $this->builder->cursor(function ($model) use (&$processed) {
            $processed++;
        });

        $this->assertEquals(4, $processed); // Changed from 3 to 4
    }

    public function testConditionalIfMethod()
    {
        // First, verify we have 4 users total
        $allUsers = $this->builder->get();
        $this->assertCount(4, $allUsers, 'Should have 4 total users in database');

        // Test with true condition
        $users = $this->builder->reset()->if(true, function ($query) {
            $query->where('name', 'John Doe');
        })->get();

        $this->assertCount(1, $users);
        $this->assertEquals('John Doe', $users[0]->name);

        // Test with false condition - should return ALL users
        $users = $this->builder->reset()->if(false, function ($query) {
            $query->where('name', 'John Doe');
        })->get();

        $this->assertCount(4, $users, 'Should return all users when condition is false and no default callback');

        // Test with default callback
        $users = $this->builder->reset()->if(
            false,
            function ($query) {
                $query->where('name', 'John Doe');
            },
            function ($query) {
                $query->where('name', 'Jane Smith');
            }
        )->get();

        $this->assertCount(1, $users);
        $this->assertEquals('Jane Smith', $users[0]->name);
    }

    public function testPurgeMethod()
    {
        // Test purging multiple records
        $affected = $this->builder->purge(2, 3);
        $this->assertEquals(2, $affected);

        // Verify records are deleted
        $remainingUsers = $this->builder->get();
        $this->assertCount(2, $remainingUsers);
        $this->assertIsArray($remainingUsers->toArray());
    }

    public function testAggregationMethods()
    {
        // Test groupConcat
        $postsBuilder = new Builder($this->pdo, 'posts', PostModel::class, 15);
        $concatenated = $postsBuilder->groupConcat('title', '|');

        $this->assertIsString($concatenated);
        $this->assertStringContainsString('First Post', $concatenated);
        $this->assertStringContainsString('Second Post', $concatenated);
        $this->assertStringContainsString('Jane Post', $concatenated);
        $this->assertStringContainsString('Bob Post', $concatenated);

        // Test stdDev
        $stdDev = $postsBuilder->stdDev('id');
        $this->assertIsFloat($stdDev);

        // Test variance
        $variance = $postsBuilder->variance('id');
        $this->assertIsFloat($variance);
    }

    public function testOmitMethod()
    {
        $users = $this->builder->omit('email', 'age')->get();
        $this->assertCount(4, $users);

        // Check that omitted columns are not in the first result
        $firstUser = $users[0];
        $this->assertArrayHasKey('name', $firstUser);
        $this->assertArrayNotHasKey('email', $firstUser);
        $this->assertArrayNotHasKey('age', $firstUser);
    }

    public function testNewestMethod()
    {
        $users = $this->builder->newest('id')->get();
        $this->assertEquals(4, $users[0]->id); // Should be the newest (highest ID)
        $this->assertEquals(3, $users[1]->id);
    }

    public function testOldestMethod()
    {
        $users = $this->builder->oldest('id')->get();
        $this->assertEquals(1, $users[0]->id); // Should be the oldest (lowest ID)
        $this->assertEquals(2, $users[1]->id);
    }

    public function testGroupByRawMethod()
    {
        $postsBuilder = new Builder($this->pdo, 'posts', PostModel::class, 15);

        $results = $postsBuilder
            ->selectRaw('user_id, COUNT(*) as post_count')
            ->groupByRaw('user_id')
            ->get();

        $this->assertGreaterThan(0, $results->count());
        $this->assertArrayHasKey('post_count', $results[0]);
    }

    public function testOrWhereInMethod()
    {
        $users = $this->builder
            ->where('id', 1)
            ->orWhereIn('id', [2, 3])
            ->get();

        $this->assertCount(3, $users);
        $ids = $users->pluck('id')->toArray();
        $this->assertContains(1, $ids);
        $this->assertContains(2, $ids);
        $this->assertContains(3, $ids);
    }

    public function testWhereNotNullMethod()
    {
        // Add a user with null email
        $this->pdo->exec("INSERT INTO users (name, email, age) VALUES ('Null Email User', NULL, 40)");

        $users = $this->builder->whereNotNull('email')->get();

        // Should exclude the user with null email
        foreach ($users as $user) {
            $this->assertNotNull($user->email);
        }
    }

    public function testOrWhereNullMethod()
    {
        // Add a user with null email
        $this->pdo->exec("INSERT INTO users (name, email, age) VALUES ('Null Email User', NULL, 40)");

        $users = $this->builder
            ->where('id', 1)
            ->orWhereNull('email')
            ->get();

        $this->assertGreaterThanOrEqual(2, $users->count());

        $hasNullEmail = false;
        $hasUser1 = false;

        foreach ($users as $user) {
            if ($user->email === null) $hasNullEmail = true;
            if ($user->id === 1) $hasUser1 = true;
        }

        $this->assertTrue($hasNullEmail);
        $this->assertTrue($hasUser1);
    }

    public function testOrWhereNotNullMethod()
    {
        $users = $this->builder
            ->where('id', 1)
            ->orWhereNotNull('email')
            ->get();

        $this->assertCount(4, $users); // All users have email or id=1
    }


    public function testWhereNotBetweenMethod()
    {
        $users = $this->builder->whereNotBetween('age', [26, 34])->get();

        foreach ($users as $user) {
            $this->assertTrue($user->age < 26 || $user->age > 34);
        }
    }

    public function testOrWhereNotBetweenMethod()
    {
        $users = $this->builder
            ->where('id', 1)
            ->orWhereNotBetween('age', [30, 40])
            ->get();

        $this->assertGreaterThan(0, $users->count());
    }

    public function testFromMethod()
    {
        $builder = $this->builder->from('posts');
        $posts = $builder->get();

        $this->assertInstanceOf(Builder::class, $builder);
        $this->assertGreaterThan(0, $posts->count());
        $this->assertArrayHasKey('title', $posts[0]);
    }

    public function testToDiffMethod()
    {
        $postsBuilder = new Builder($this->pdo, 'posts', PostModel::class, 15);
        $diff = $postsBuilder->toDiff('views', 'id', 'view_diff');

        $this->assertInstanceOf(Collection::class, $diff);
        $this->assertGreaterThan(0, $diff->count());
    }

    public function testToRatioMethod()
    {
        $postsBuilder = new Builder($this->pdo, 'posts', PostModel::class, 15);
        $ratio = $postsBuilder->toRatio('views', 'id', 'view_ratio');

        $this->assertInstanceOf(Collection::class, $ratio);
        $this->assertGreaterThan(0, $ratio->count());
    }

    public function testILikeMethod()
    {
        $users = $this->builder->iLike('name', '%john%')->get();
        $this->assertCount(2, $users);
        $this->assertEquals('John Doe', $users[0]->name);
    }

    public function testWherePatternMethod()
    {
        $users = $this->builder->wherePattern('name', 'J%', 'LIKE')->get();

        foreach ($users as $user) {
            $this->assertStringStartsWith('J', $user->name);
        }
    }

    public function testTransformByMethod()
    {
        $users = $this->builder->transformBy(function ($query) {
            return "UPPER(name)";
        }, 'upper_name')->get();

        $this->assertCount(4, $users);
        $this->assertArrayHasKey('upper_name', $users[0]);
    }

    public function testFirstLastInWindowMethod()
    {
        $postsBuilder = new Builder($this->pdo, 'posts', PostModel::class, 15);

        $posts = $postsBuilder->firstLastInWindow(
            'title',
            'created_at',
            'user_id',
            true,
            'first_title'
        )->get();

        $this->assertGreaterThan(0, $posts->count());
        $this->assertArrayHasKey('first_title', $posts[0]);
    }

    public function testMovingDifferenceMethod()
    {
        $postsBuilder = new Builder($this->pdo, 'posts', PostModel::class, 15);

        $posts = $postsBuilder->movingDifference('views', 'created_at', 'views_diff')->get();

        $this->assertGreaterThan(0, $posts->count());
        $this->assertArrayHasKey('views_diff', $posts[0]);
    }

    public function testMovingAverageMethod()
    {
        $postsBuilder = new Builder($this->pdo, 'posts', PostModel::class, 15);

        $posts = $postsBuilder->movingAverage('views', 2, 'created_at', 'moving_avg')->get();

        $this->assertGreaterThan(0, $posts->count());
        $this->assertArrayHasKey('moving_avg', $posts[0]);
    }

    public function testStreamMethod()
    {
        $processed = 0;

        foreach ($this->builder->stream(2) as $user) {
            $processed++;
            $this->assertInstanceOf(Model::class, $user);
        }

        $this->assertEquals(4, $processed);
    }

    public function testBatchMethod()
    {
        $batchCount = 0;
        $totalProcessed = 0;

        $this->builder->batch(2, function ($batch) use (&$batchCount, &$totalProcessed) {
            $batchCount++;
            $totalProcessed += $batch->count();
        }, 3); // batch size 3

        $this->assertEquals(2, $batchCount); // 2 batches for 4 records with chunk size 2
        $this->assertEquals(4, $totalProcessed);
    }

    public function testStdDevMethod()
    {
        $postsBuilder = new Builder($this->pdo, 'posts', PostModel::class, 15);
        $stdDev = $postsBuilder->stdDev('views');

        $this->assertIsFloat($stdDev);
        $this->assertGreaterThanOrEqual(0, $stdDev);
    }

    public function testVarianceMethod()
    {
        $postsBuilder = new Builder($this->pdo, 'posts', PostModel::class, 15);
        $variance = $postsBuilder->variance('views');

        $this->assertIsFloat($variance);
        $this->assertGreaterThanOrEqual(0, $variance);
    }

    public function testGroupConcatMethod()
    {
        $postsBuilder = new Builder($this->pdo, 'posts', PostModel::class, 15);
        $concatenated = $postsBuilder->groupConcat('title', '|');

        $this->assertIsString($concatenated);
        $this->assertStringContainsString('First Post', $concatenated);
        $this->assertStringContainsString('Second Post', $concatenated);
    }

    public function testWhereDateTimeBetweenMethod()
    {
        $postsBuilder = new Builder($this->pdo, 'posts', PostModel::class, 15);

        $posts = $postsBuilder->whereDateTimeBetween(
            'created_at',
            date('Y-m-d H:i:s', strtotime('-1 day')),
            date('Y-m-d H:i:s', strtotime('+1 day'))
        )->get();

        $this->assertGreaterThan(0, $posts->count());
    }

    public function testWhereTodayMethod()
    {
        $users = $this->builder->whereToday('created_at')->get();
        $this->assertCount(4, $users); // All users created today
    }

    public function testWhereYesterdayMethod()
    {
        // This should return no results since all users were created today
        $users = $this->builder->whereYesterday('created_at')->get();
        $this->assertCount(0, $users);
    }

    public function testWhereThisMonthMethod()
    {
        $users = $this->builder->whereThisMonth('created_at')->get();
        $this->assertCount(4, $users); // All users created this month
    }

    public function testWhereThisYearMethod()
    {
        $users = $this->builder->whereThisYear('created_at')->get();
        $this->assertCount(4, $users); // All users created this year
    }

    public function testDynamicMethodCalls()
    {
        // Test dynamic where methods
        $users = $this->builder->whereName('John Doe')->get();
        $this->assertCount(1, $users);
        $this->assertEquals('John Doe', $users[0]->name);

        $users = $this->builder->whereAge(25)->get();
        $this->assertCount(1, $users);
        $this->assertEquals(25, $users[0]->age);
    }

    public function testMultipleInsertWithChunking()
    {
        $rows = [
            ['name' => 'Chunk User 1', 'email' => 'chunk1@example.com', 'age' => 20],
            ['name' => 'Chunk User 2', 'email' => 'chunk2@example.com', 'age' => 21],
            ['name' => 'Chunk User 3', 'email' => 'chunk3@example.com', 'age' => 22],
            ['name' => 'Chunk User 4', 'email' => 'chunk4@example.com', 'age' => 23],
            ['name' => 'Chunk User 5', 'email' => 'chunk5@example.com', 'age' => 24],
        ];

        $affected = $this->builder->insertMany($rows, 2); // Chunk size of 2
        $this->assertEquals(5, $affected);

        $count = $this->builder->count();
        $this->assertEquals(9, $count); // 4 original + 5 new
    }

    public function testWhereDateBetweenMethod()
    {
        // Add posts with specific dates
        $this->pdo->exec("INSERT INTO posts (user_id, title, content, created_at) VALUES (1, 'Old Post', 'Content', '2023-01-01')");
        $this->pdo->exec("INSERT INTO posts (user_id, title, content, created_at) VALUES (1, 'New Post', 'Content', '2024-01-01')");

        $postsBuilder = new Builder($this->pdo, 'posts', PostModel::class, 15);

        // Filter to only get posts from 2023
        $posts = $postsBuilder
            ->whereDateBetween('created_at', '2023-01-01', '2023-12-31')
            ->get();

        $this->assertCount(1, $posts);
        $this->assertEquals('Old Post', $posts[0]->title);
    }

    public function testOrderByRawMethod()
    {
        $users = $this->builder->orderByRaw('name DESC')->get();

        $this->assertEquals('John Doe', $users[0]->name);
        $this->assertEquals('Jane Smith', $users[1]->name);
        $this->assertEquals('Bob Johnson', $users[2]->name);
        $this->assertEquals('Alice Brown', $users[3]->name);
    }

    public function testWhereRawMethod()
    {
        $users = $this->builder->whereRaw('LENGTH(name) > ?', [10])->get();

        foreach ($users as $user) {
            $this->assertGreaterThan(10, strlen($user->name));
        }
    }

    public function testSelectRawWithBindings()
    {
        $users = $this->builder->selectRaw('name, age + ? as future_age', [10])->get();

        $this->assertCount(4, $users);
        $this->assertArrayHasKey('future_age', $users[0]);

        // For John Doe (age 25): 25 + 10 = 35
        // The future_age should be the calculated value from the database
        $this->assertEquals(35, $users[0]->future_age);
    }

    public function testToSqlWithConditions()
    {
        $sql = $this->builder
            ->where("name", "John")
            ->where("age", ">", 25)
            ->orderBy("created_at", "DESC")
            ->limit(10)
            ->toSql();

        $this->assertStringContainsString("WHERE", $sql);
        $this->assertStringContainsString("ORDER BY", $sql);
        $this->assertStringContainsString("LIMIT 10", $sql);
    }

    public function testSetRelationInfo()
    {
        $relationInfo = [
            "type" => "bindToMany",
            "foreignKey" => "user_id",
            "relatedKey" => "role_id",
            "pivotTable" => "user_roles",
        ];

        $builder = $this->builder->setRelationInfo($relationInfo);
        $this->assertInstanceOf(Builder::class, $builder);
    }

    public function testComplexQueryBuildingWithOffset()
    {
        $sql = $this->builder
            ->select("id", "name", "email")
            ->where("status", "active")
            ->whereIn("role", ["admin", "moderator"])
            ->whereNotNull("email_verified_at")
            ->whereBetween("created_at", ["2023-01-01", "2023-12-31"])
            ->orderBy("created_at", "DESC")
            ->groupBy("department")
            ->limit(10)
            ->offset(0)
            ->toSql();

        $this->assertStringContainsString("SELECT", $sql);
        $this->assertStringContainsString("WHERE", $sql);
        $this->assertStringContainsString("ORDER BY", $sql);
        $this->assertStringContainsString("GROUP BY", $sql);
        $this->assertStringContainsString("LIMIT 10", $sql);
    }

    public function testSaveMany()
    {
        $rows = [
            ['name' => 'Batch User 1', 'email' => 'batch1@example.com', 'age' => 20],
            ['name' => 'Batch User 2', 'email' => 'batch2@example.com', 'age' => 21],
            ['name' => 'Batch User 3', 'email' => 'batch3@example.com', 'age' => 22]
        ];

        $affected = $this->builder->insertMany($rows);
        $this->assertEquals(3, $affected);

        $batchUsers = $this->builder
            ->whereIn('email', ['batch1@example.com', 'batch2@example.com', 'batch3@example.com'])
            ->get();

        $this->assertCount(3, $batchUsers);
    }

    public function testSaveManyWithChunking()
    {
        $rows = [
            ['name' => 'Chunk User 1', 'email' => 'chunk1@example.com', 'age' => 30],
            ['name' => 'Chunk User 2', 'email' => 'chunk2@example.com', 'age' => 31],
            ['name' => 'Chunk User 3', 'email' => 'chunk3@example.com', 'age' => 32],
            ['name' => 'Chunk User 4', 'email' => 'chunk4@example.com', 'age' => 33],
            ['name' => 'Chunk User 5', 'email' => 'chunk5@example.com', 'age' => 34],
        ];

        // Test with chunk size of 2
        $affected = $this->builder->insertMany($rows, 2);
        $this->assertEquals(5, $affected);

        // Verify all records were created
        $chunkUsers =  $this->builder
            ->where('email', 'LIKE', 'chunk%@example.com')
            ->get();

        $this->assertCount(5, $chunkUsers);
    }

    public function testDirtyAttributes()
    {
        $user = $this->builder->where('id', 1)->first();
        $user->fill([
            'name' => 'Timestamp User',
            'email' => 'timestamp@example.com',
            'age' => 35
        ]);

        $dirty = $user->getDirtyAttributes();

        $this->assertEquals('Timestamp User', $dirty['name']);
        $this->assertEquals('timestamp@example.com', $dirty['email']);
        $this->assertEquals(35, $dirty['age']);
        $this->assertTrue($user->isDirtyAttr('name'));
        $this->assertTrue($user->isDirtyAttr('email'));
        $this->assertTrue($user->isDirtyAttr('age'));
    }

    public function testOriginalAttributes()
    {
        $user = $this->builder->where('id', 1)->first();
        $user->fill([
            'name' => 'Timestamp User',
            'email' => 'timestamp@example.com',
            'age' => 35
        ]);

        $original = $user->getOriginalAttributes();

        $this->assertEquals(1, $original['id']);
        $this->assertEquals('John Doe', $original['name']);
        $this->assertEquals('john@example.com', $original['email']);
        $this->assertEquals(25, $original['age']);
        $this->assertEquals('active', $original['status']);
    }

    public function testSetTableAndGetTable()
    {
        $user = new TestSqliteModel();
        $this->assertNotNull($user->getTable());
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
