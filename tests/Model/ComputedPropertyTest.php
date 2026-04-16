<?php

namespace Tests\Unit\Model;

use PDO;
use PHPUnit\Framework\TestCase;
use Phaseolies\Database\Database;
use Phaseolies\Database\Entity\Model;
use Phaseolies\Database\Entity\Attributes\Computed;
use Phaseolies\DI\Container;
use Phaseolies\Support\StringService;
use Tests\Support\MockContainer;
use Tests\Support\Model\MockComputedUser;

class ComputedPropertyTest extends TestCase
{
    private ?PDO $pdo = null;

    protected function setUp(): void
    {
        $container = new MockContainer();
        $container->singleton('str', StringService::class);
        Container::setInstance($container);

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createTestTables();
        $this->setupDatabaseConnections();

        Model::resetComputedCache();
    }

    protected function tearDown(): void
    {
        Model::resetComputedCache();

        $this->pdo = null;
        $this->tearDownDatabaseConnections();
    }

    /** #[Computed] targets methods */
    public function testComputedAttributeTargetsMethod(): void
    {
        $ref  = new \ReflectionClass(MockComputedUser::class);
        $attr = $ref->getMethod('fullName')->getAttributes(Computed::class);

        $this->assertCount(1, $attr);
    }

    /** #[Computed] can be placed on multiple methods independently */
    public function testComputedAttributeCanDecorateMultipleMethods(): void
    {
        $ref = new \ReflectionClass(MockComputedUser::class);

        $this->assertNotEmpty($ref->getMethod('fullName')->getAttributes(Computed::class));
        $this->assertNotEmpty($ref->getMethod('initials')->getAttributes(Computed::class));
        $this->assertNotEmpty($ref->getMethod('emailDomain')->getAttributes(Computed::class));
    }

    /** A plain method without the attribute has no #[Computed] */
    public function testPlainMethodHasNoComputedAttribute(): void
    {
        $ref  = new \ReflectionClass(MockComputedUser::class);
        $attr = $ref->getMethod('notComputed')->getAttributes(Computed::class);

        $this->assertEmpty($attr);
    }

    /** isComputed() returns true for a computed method accessed by camelCase name */
    public function testIsComputedReturnsTrueForCamelCaseName(): void
    {
        $user = $this->makeUser();

        $this->assertTrue($user->isComputed('fullName'));
    }

    /** isComputed() returns true for a computed method accessed by snake_case key */
    public function testIsComputedReturnsTrueForSnakeCaseKey(): void
    {
        $user = $this->makeUser();

        $this->assertTrue($user->isComputed('full_name'));
    }

    /** isComputed() returns false for a plain method without the attribute */
    public function testIsComputedReturnsFalseForPlainMethod(): void
    {
        $user = $this->makeUser();

        $this->assertFalse($user->isComputed('notComputed'));
    }

    /** isComputed() returns false for a real attribute key */
    public function testIsComputedReturnsFalseForRealAttribute(): void
    {
        $user = $this->makeUser(['first_name' => 'John']);

        $this->assertFalse($user->isComputed('first_name'));
    }

    /** isComputed() returns false for an unknown name */
    public function testIsComputedReturnsFalseForUnknownName(): void
    {
        $user = $this->makeUser();

        $this->assertFalse($user->isComputed('doesNotExist'));
    }

    /** resolveComputed() resolves by camelCase method name */
    public function testResolveComputedByCamelCase(): void
    {
        $user = $this->makeUser(['first_name' => 'Jane', 'last_name' => 'Doe']);

        $this->assertSame('Jane Doe', $user->resolveComputed('fullName'));
    }

    /** resolveComputed() resolves by snake_case key */
    public function testResolveComputedBySnakeCase(): void
    {
        $user = $this->makeUser(['first_name' => 'Jane', 'last_name' => 'Doe']);

        $this->assertSame('Jane Doe', $user->resolveComputed('full_name'));
    }

    /** resolveComputed() returns null for an unknown name */
    public function testResolveComputedReturnsNullForUnknownName(): void
    {
        $user = $this->makeUser();

        $this->assertNull($user->resolveComputed('unknown'));
    }

    /** Accessing a computed property via camelCase calls the method */
    public function testGetViaCamelCaseCallsComputedMethod(): void
    {
        $user = $this->makeUser(['first_name' => 'John', 'last_name' => 'Smith']);

        $this->assertSame('John Smith', $user->fullName);
    }

    /** Accessing a computed property via snake_case calls the method */
    public function testGetViaSnakeCaseCallsComputedMethod(): void
    {
        $user = $this->makeUser(['first_name' => 'John', 'last_name' => 'Smith']);

        $this->assertSame('John Smith', $user->full_name);
    }

    /** Real attributes take priority over computed in __get() */
    public function testRealAttributeTakesPriorityOverComputed(): void
    {
        // If a DB column were named 'full_name' it should win
        $user = $this->makeUser(['full_name' => 'Override Value']);

        $this->assertSame('Override Value', $user->full_name);
    }

    /** A plain method is NOT accessible as a computed property */
    public function testPlainMethodIsNotAccessibleAsComputedProperty(): void
    {
        $user = $this->makeUser();

        // notComputed() has no #[Computed] — isComputed returns false,
        // falls through to method_exists path and returns the string directly
        $this->assertSame('plain method', $user->notComputed);
    }

    /** Multiple computed methods are all resolvable via __get() */
    public function testMultipleComputedMethodsAreAllAccessible(): void
    {
        $user = $this->makeUser([
            'first_name' => 'Ada',
            'last_name'  => 'Lovelace',
            'email'      => 'ada@example.com',
        ]);

        $this->assertSame('Ada Lovelace', $user->full_name);
        $this->assertSame('AL',           $user->initials);
        $this->assertSame('example.com',  $user->email_domain);
    }

    /** getComputedAttributes() returns snake_case keys */
    public function testGetComputedAttributesUsesSnakeCaseKeys(): void
    {
        $user = $this->makeUser(['first_name' => 'A', 'last_name' => 'B', 'email' => 'a@b.com']);

        $keys = array_keys($user->getComputedAttributes());

        $this->assertContains('full_name',    $keys);
        $this->assertContains('initials',     $keys);
        $this->assertContains('email_domain', $keys);
        $this->assertNotContains('fullName',    $keys);
        $this->assertNotContains('emailDomain', $keys);
    }

    /** getComputedAttributes() calls each method and returns its value */
    public function testGetComputedAttributesReturnsCorrectValues(): void
    {
        $user = $this->makeUser([
            'first_name' => 'Alan',
            'last_name'  => 'Turing',
            'email'      => 'alan@bletchley.org',
        ]);

        $computed = $user->getComputedAttributes();

        $this->assertSame('Alan Turing',   $computed['full_name']);
        $this->assertSame('AT',            $computed['initials']);
        $this->assertSame('bletchley.org', $computed['email_domain']);
    }

    /** getComputedAttributes() excludes keys listed in $unexposable (snake_case) */
    public function testGetComputedAttributesRespectsUnexposableBySnakeCase(): void
    {
        $user = new class(['first_name' => 'X', 'last_name' => 'Y']) extends Model {
            protected $table      = 'users';
            protected $connection = 'default';
            protected $timeStamps = false;
            protected $unexposable = ['full_name'];

            #[Computed]
            public function fullName(): string
            {
                return $this->first_name . ' ' . $this->last_name;
            }

            #[Computed]
            public function initials(): string
            {
                return 'XY';
            }
        };

        $computed = $user->getComputedAttributes();

        $this->assertArrayNotHasKey('full_name', $computed);
        $this->assertArrayHasKey('initials',     $computed);
    }

    /** getComputedAttributes() excludes keys listed in $unexposable (camelCase) */
    public function testGetComputedAttributesRespectsUnexposableByCamelCase(): void
    {
        $user = new class(['first_name' => 'X', 'last_name' => 'Y']) extends Model {
            protected $table      = 'users';
            protected $connection = 'default';
            protected $timeStamps = false;
            protected $unexposable = ['fullName'];

            #[Computed]
            public function fullName(): string
            {
                return $this->first_name . ' ' . $this->last_name;
            }

            #[Computed]
            public function initials(): string
            {
                return 'XY';
            }
        };

        $computed = $user->getComputedAttributes();

        $this->assertArrayNotHasKey('full_name', $computed);
        $this->assertArrayHasKey('initials',     $computed);
    }

    /** getComputedAttributes() returns empty array when no #[Computed] methods exist */
    public function testGetComputedAttributesIsEmptyWithNoComputedMethods(): void
    {
        $model = new class extends Model {
            protected $table      = 'users';
            protected $connection = 'default';
            protected $timeStamps = false;

            public function notComputed(): string
            {
                return 'nope';
            }
        };

        $this->assertSame([], $model->getComputedAttributes());
    }

    /** toArray() includes computed properties with snake_case keys */
    public function testToArrayIncludesComputedProperties(): void
    {
        $user = $this->makeUser([
            'first_name' => 'Grace',
            'last_name'  => 'Hopper',
            'email'      => 'grace@navy.mil',
        ]);

        $array = $user->toArray();

        $this->assertArrayHasKey('full_name',    $array);
        $this->assertArrayHasKey('initials',     $array);
        $this->assertArrayHasKey('email_domain', $array);
    }

    /** toArray() computed values are correct */
    public function testToArrayComputedValuesAreCorrect(): void
    {
        $user = $this->makeUser([
            'first_name' => 'Grace',
            'last_name'  => 'Hopper',
            'email'      => 'grace@navy.mil',
        ]);

        $array = $user->toArray();

        $this->assertSame('Grace Hopper', $array['full_name']);
        $this->assertSame('GH',           $array['initials']);
        $this->assertSame('navy.mil',      $array['email_domain']);
    }

    /** toArray() does NOT include the camelCase method name as a key */
    public function testToArrayDoesNotIncludeCamelCaseKey(): void
    {
        $user  = $this->makeUser(['first_name' => 'A', 'last_name' => 'B', 'email' => 'a@b.com']);
        $array = $user->toArray();

        $this->assertArrayNotHasKey('fullName',    $array);
        $this->assertArrayNotHasKey('emailDomain', $array);
    }

    /** json_encode() includes computed properties */
    public function testJsonEncodeIncludesComputedProperties(): void
    {
        $user = $this->makeUser([
            'first_name' => 'Linus',
            'last_name'  => 'Torvalds',
            'email'      => 'linus@linux.org',
        ]);

        $json = json_decode(json_encode($user), true);

        $this->assertSame('Linus Torvalds', $json['full_name']);
        $this->assertSame('LT',             $json['initials']);
    }

    /** __toString() includes computed properties */
    public function testToStringIncludesComputedProperties(): void
    {
        $user = $this->makeUser([
            'first_name' => 'Tim',
            'last_name'  => 'Cook',
            'email'      => 'tim@apple.com',
        ]);

        $data = json_decode((string) $user, true);

        $this->assertArrayHasKey('full_name', $data);
        $this->assertSame('Tim Cook', $data['full_name']);
    }

    /** makeHidden() with snake_case key excludes the computed property from toArray() */
    public function testMakeHiddenBySnakeCaseExcludesComputedFromToArray(): void
    {
        $user = $this->makeUser(['first_name' => 'A', 'last_name' => 'B', 'email' => 'a@b.com']);

        $array = $user->makeHidden(['full_name'])->toArray();

        $this->assertArrayNotHasKey('full_name', $array);
        $this->assertArrayHasKey('initials',     $array);
    }

    /** makeHidden() with camelCase method name also excludes the computed property */
    public function testMakeHiddenByCamelCaseExcludesComputedFromToArray(): void
    {
        $user = $this->makeUser(['first_name' => 'A', 'last_name' => 'B', 'email' => 'a@b.com']);

        $array = $user->makeHidden(['fullName'])->toArray();

        $this->assertArrayNotHasKey('full_name', $array);
        $this->assertArrayHasKey('initials',     $array);
    }

    /** makeHidden() on multiple computed properties excludes all of them */
    public function testMakeHiddenExcludesMultipleComputedProperties(): void
    {
        $user = $this->makeUser(['first_name' => 'A', 'last_name' => 'B', 'email' => 'a@b.com']);

        $array = $user->makeHidden(['full_name', 'initials'])->toArray();

        $this->assertArrayNotHasKey('full_name', $array);
        $this->assertArrayNotHasKey('initials',  $array);
        $this->assertArrayHasKey('email_domain', $array);
    }

    /** save() does not attempt to persist computed properties */
    public function testSaveDoesNotPersistComputedProperties(): void
    {
        $id = $this->insertUser('John', 'Doe', 'john@example.com');

        $user            = MockComputedUser::find($id);
        $user->last_name = 'Updated';
        $user->save();

        $row = $this->pdo
            ->query("SELECT * FROM users WHERE id = {$id}")
            ->fetch(PDO::FETCH_ASSOC);

        $this->assertArrayNotHasKey('full_name',    $row);
        $this->assertArrayNotHasKey('initials',     $row);
        $this->assertArrayNotHasKey('email_domain', $row);
        $this->assertSame('Updated', $row['last_name']);
    }

    /** Computed properties are not present in getDirtyAttributes() */
    public function testComputedPropertiesAreNotDirty(): void
    {
        $user = $this->makeUser(['first_name' => 'X', 'last_name' => 'Y', 'email' => 'x@y.com']);

        $dirty = $user->getDirtyAttributes();

        $this->assertArrayNotHasKey('full_name',    $dirty);
        $this->assertArrayNotHasKey('fullName',     $dirty);
        $this->assertArrayNotHasKey('initials',     $dirty);
        $this->assertArrayNotHasKey('email_domain', $dirty);
    }

    /** resetComputedCache() for a specific class clears only that class */
    public function testResetComputedCacheClearsSpecificClass(): void
    {
        $user = $this->makeUser(['first_name' => 'A', 'last_name' => 'B', 'email' => 'a@b.com']);

        // Warm the cache
        $user->isComputed('full_name');

        // Clear and re-resolve — should not throw or return wrong result
        MockComputedUser::resetComputedCache(MockComputedUser::class);

        $this->assertTrue($user->isComputed('full_name'));
    }

    /** resetComputedCache() with null clears all classes */
    public function testResetComputedCacheClearsAll(): void
    {
        $this->expectNotToPerformAssertions();

        Model::resetComputedCache();
        Model::resetComputedCache(null);
    }

    /** Reflection runs once — second call to isComputed() uses the cache */
    public function testReflectionRunsOncePerClass(): void
    {
        $userA = $this->makeUser(['first_name' => 'A', 'last_name' => 'B', 'email' => 'a@b.com']);
        $userB = $this->makeUser(['first_name' => 'C', 'last_name' => 'D', 'email' => 'c@d.com']);

        // Both calls on different instances of the same class use the same cache
        $this->assertTrue($userA->isComputed('full_name'));
        $this->assertTrue($userB->isComputed('full_name'));
    }

    /** A model with no computed methods has an empty computed attributes map */
    public function testModelWithNoComputedMethodsHasEmptyMap(): void
    {
        $model = new class extends Model {
            protected $table      = 'users';
            protected $connection = 'default';
            protected $timeStamps = false;
        };

        $this->assertSame([], $model->getComputedAttributes());
    }

    /** A single-word method name stays the same after snake_case conversion */
    public function testSingleWordMethodNameIsUnchangedBySnakeCase(): void
    {
        $model = new class extends Model {
            protected $table      = 'users';
            protected $connection = 'default';
            protected $timeStamps = false;

            #[Computed]
            public function initials(): string
            {
                return 'AB';
            }
        };

        $this->assertTrue($model->isComputed('initials'));
        $this->assertArrayHasKey('initials', $model->getComputedAttributes());
    }

    /** Computed property returning null is still included in toArray() */
    public function testComputedPropertyReturningNullIsIncludedInToArray(): void
    {
        $model = new class extends Model {
            protected $table      = 'users';
            protected $connection = 'default';
            protected $timeStamps = false;

            #[Computed]
            public function missingValue(): ?string
            {
                return null;
            }
        };

        $array = $model->toArray();

        $this->assertArrayHasKey('missing_value', $array);
        $this->assertNull($array['missing_value']);
    }

    /** Computed property can return any type — array, int, bool */
    public function testComputedPropertyCanReturnNonStringTypes(): void
    {
        $model = new class(['score' => '42']) extends Model {
            protected $table      = 'users';
            protected $connection = 'default';
            protected $timeStamps = false;

            #[Computed]
            public function doubleScore(): int
            {
                return (int) $this->score * 2;
            }

            #[Computed]
            public function isHighScore(): bool
            {
                return (int) $this->score > 40;
            }

            #[Computed]
            public function scoreBreakdown(): array
            {
                return ['raw' => $this->score];
            }
        };

        $array = $model->toArray();

        $this->assertSame(84,    $array['double_score']);
        $this->assertTrue($array['is_high_score']);
        $this->assertSame(['raw' => '42'], $array['score_breakdown']);
    }

    private function makeUser(array $attributes = []): MockComputedUser
    {
        return new MockComputedUser($attributes);
    }

    private function insertUser(string $firstName, string $lastName, string $email): int
    {
        $this->pdo->exec(
            "INSERT INTO users (first_name, last_name, email) VALUES ('{$firstName}', '{$lastName}', '{$email}')"
        );

        return (int) $this->pdo->lastInsertId();
    }

    private function createTestTables(): void
    {
        $this->pdo->exec("
            CREATE TABLE users (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                first_name TEXT NOT NULL,
                last_name  TEXT NOT NULL,
                email      TEXT NOT NULL,
                password   TEXT
            )
        ");
    }

    private function setupDatabaseConnections(): void
    {
        $this->setStaticProperty(Database::class, 'connections', [
            'default' => $this->pdo,
            'sqlite'  => $this->pdo,
        ]);
        $this->setStaticProperty(Database::class, 'transactions', []);
    }

    private function tearDownDatabaseConnections(): void
    {
        $this->setStaticProperty(Database::class, 'connections', []);
        $this->setStaticProperty(Database::class, 'transactions', []);
    }

    private function setStaticProperty(string $class, string $property, mixed $value): void
    {
        $ref  = new \ReflectionClass($class);
        $prop = $ref->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue(null, $value);
        $prop->setAccessible(false);
    }
}
