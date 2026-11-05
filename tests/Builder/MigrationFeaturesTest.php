<?php

namespace Tests\Unit\Builder;

use PDO;
use PHPUnit\Framework\TestCase;
use Phaseolies\Database\Migration\Blueprint;
use Phaseolies\DI\Container;

class MigrationFeaturesTest extends TestCase
{
    private PDO $pdo;

    private string $driver = 'sqlite';

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->bind('sqlite');
    }

    protected function tearDown(): void
    {
        $property = (new \ReflectionClass(Container::class))->getProperty('instance');
        $property->setValue(null, null);
    }

    private function bind(string $driver): void
    {
        $this->driver = $driver;
        $pdo = $this->pdo;

        $container = new Container();
        $container->bind('db', fn() => new class($pdo, $driver) {
            public function __construct(private PDO $pdo, private string $driver)
            {
            }

            public function getConnection()
            {
                return $this->driver === 'sqlite' ? $this->pdo : new class($this->driver) {
                    public function __construct(private string $driver)
                    {
                    }

                    public function getAttribute($attribute)
                    {
                        return $this->driver;
                    }
                };
            }
        });

        Container::setInstance($container);
    }

    private function blueprint(string $table, callable $callback, bool $creating = true): Blueprint
    {
        $blueprint = new Blueprint($table, $this->driver, $creating);
        $callback($blueprint);

        return $blueprint;
    }

    /** Run a blueprint against the SQLite database, statement by statement. */
    private function migrate(string $table, callable $callback, bool $creating = true): void
    {
        foreach ($this->blueprint($table, $callback, $creating)->toStatements() as $statement) {
            $this->pdo->exec($statement);
        }
    }

    private function sql(string $driver, callable $callback, bool $creating = true, string $table = 'posts'): string
    {
        $this->bind($driver);

        return $this->blueprint($table, $callback, $creating)->toSql();
    }

    private function columns(string $table): array
    {
        return $this->pdo->query("SELECT name FROM pragma_table_info('{$table}')")->fetchAll(PDO::FETCH_COLUMN);
    }

    private function indexes(string $table): array
    {
        return $this->pdo->query("SELECT name FROM pragma_index_list('{$table}')")->fetchAll(PDO::FETCH_COLUMN);
    }

    // ------------------------------------------------------------ indexes

    public function testColumnIndexIsActuallyCreatedOnSqlite(): void
    {
        $this->migrate('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title')->index();
            $table->string('slug')->unique();
        });

        $this->assertContains('idx_posts_title', $this->indexes('posts'));
    }

    public function testColumnIndexAcceptsCustomName(): void
    {
        $this->migrate('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title')->index('my_title_idx');
        });

        $this->assertContains('my_title_idx', $this->indexes('posts'));
    }

    public function testCompositeIndexAndUniqueOnSqlite(): void
    {
        $this->migrate('posts', function (Blueprint $table) {
            $table->id();
            $table->string('a');
            $table->string('b');
            $table->index(['a', 'b']);
            $table->unique(['a', 'b'], 'posts_ab_uq');
        });

        $this->assertContains('idx_posts_a_b', $this->indexes('posts'));
        $this->assertContains('posts_ab_uq', $this->indexes('posts'));

        $this->pdo->exec("INSERT INTO posts (a, b) VALUES ('x', 'y')");
        $this->expectException(\PDOException::class);
        $this->pdo->exec("INSERT INTO posts (a, b) VALUES ('x', 'y')");
    }

    public function testUniqueOnExistingSqliteTableUsesUniqueIndex(): void
    {
        $this->migrate('posts', fn(Blueprint $t) => [$t->id(), $t->string('email')]);
        $this->migrate('posts', fn(Blueprint $t) => $t->unique('email'), false);

        $this->assertContains('posts_email_unique', $this->indexes('posts'));
    }

    public function testDropIndexByColumnsAndByName(): void
    {
        $this->migrate('posts', function (Blueprint $table) {
            $table->id();
            $table->string('a');
            $table->string('b');
            $table->index(['a', 'b']);
            $table->unique('b');
        });

        $this->migrate('posts', function (Blueprint $table) {
            $table->dropIndex(['a', 'b']);
            $table->dropUnique('posts_b_unique');
        }, false);

        $this->assertSame([], $this->indexes('posts'));
    }

    public function testRenameAndDropColumnsOnSqlite(): void
    {
        $this->migrate('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('legacy')->nullable();
            $table->timestamps();
        });

        $this->migrate('posts', function (Blueprint $table) {
            $table->renameColumn('title', 'headline');
            $table->dropColumn('legacy');
            $table->dropTimestamps();
        }, false);

        $this->assertSame(['id', 'headline'], $this->columns('posts'));
    }

    public function testChangeColumnThrowsOnSqlite(): void
    {
        $this->migrate('posts', fn(Blueprint $t) => [$t->id(), $t->string('title')]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SQLite cannot modify column');

        $this->blueprint('posts', fn(Blueprint $t) => $t->string('title', 50)->change(), false)->toSql();
    }

    public function testDropOnlyBlueprintIsAllowedWhenAltering(): void
    {
        $sql = $this->sql('mysql', fn(Blueprint $t) => $t->dropColumn('legacy'), false);

        $this->assertSame('ALTER TABLE `posts` DROP COLUMN `legacy`;', $sql);
    }

    // ------------------------------------------------------- foreign keys

    public function testConstrainedForeignIdOnSqliteIsInlinedInCreateTable(): void
    {
        $this->pdo->exec('PRAGMA foreign_keys = ON');

        $this->migrate('users', fn(Blueprint $t) => $t->id());
        $this->migrate('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        });

        $this->pdo->exec('INSERT INTO users DEFAULT VALUES');
        $this->pdo->exec('INSERT INTO posts (user_id) VALUES (1)');
        $this->pdo->exec('DELETE FROM users WHERE id = 1');

        $this->assertSame('0', (string) $this->pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn());

        $this->expectException(\PDOException::class);
        $this->pdo->exec('INSERT INTO posts (user_id) VALUES (99)');
    }

    public function testForeignKeyOnExistingSqliteTableThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot add a foreign key');

        $this->blueprint('posts', fn(Blueprint $t) => $t->foreignId('user_id')->constrained(), false)->toSql();
    }

    public function testConstrainedGuessesPluralTable(): void
    {
        $sql = $this->sql('mysql', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained();
            $table->foreignId('address_id')->constrained();
            $table->foreignId('author_id')->constrained('people', 'pk')->nullOnDelete();
        });

        $this->assertStringContainsString('FOREIGN KEY (category_id) REFERENCES categories (id)', $sql);
        $this->assertStringContainsString('FOREIGN KEY (address_id) REFERENCES addresses (id)', $sql);
        $this->assertStringContainsString('FOREIGN KEY (author_id) REFERENCES people (pk) ON DELETE SET NULL', $sql);
    }

    public function testCompositeForeignKey(): void
    {
        $sql = $this->sql('mysql', function (Blueprint $table) {
            $table->integer('a');
            $table->integer('b');
            $table->foreign(['a', 'b'])->references(['x', 'y'])->on('other')->name('fk_custom');
        });

        $this->assertStringContainsString(
            'ALTER TABLE posts ADD CONSTRAINT fk_custom FOREIGN KEY (a, b) REFERENCES other (x, y)',
            $sql
        );
    }

    public function testDropForeignPerDriver(): void
    {
        $mysql = $this->sql('mysql', fn(Blueprint $t) => $t->dropForeign(['user_id']), false);
        $pgsql = $this->sql('pgsql', fn(Blueprint $t) => $t->dropForeign('fk_posts_user_id'), false);

        $this->assertSame('ALTER TABLE `posts` DROP FOREIGN KEY `fk_posts_user_id`;', $mysql);
        $this->assertSame('ALTER TABLE "posts" DROP CONSTRAINT "fk_posts_user_id";', $pgsql);
    }

    // ------------------------------------------------------------- MySQL

    public function testMysqlColumnModifiers(): void
    {
        $sql = $this->sql('mysql', function (Blueprint $table) {
            $table->id();
            $table->integer('votes')->unsigned()->comment("it's");
            $table->timestamp('seen_at', 3)->useCurrent()->useCurrentOnUpdate();
            $table->string('code')->charset('utf8mb4')->collation('utf8mb4_bin');
            $table->integer('double_votes')->storedAs('votes * 2');
            $table->string('secret')->nullable()->invisible();
            $table->smallIncrements('small_id');
        });

        $this->assertStringContainsString("votes INT UNSIGNED NOT NULL COMMENT 'it''s'", $sql);
        $this->assertStringContainsString(
            'seen_at TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            $sql
        );
        $this->assertStringContainsString('code VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL', $sql);
        $this->assertStringContainsString('double_votes INT GENERATED ALWAYS AS (votes * 2) STORED NOT NULL', $sql);
        $this->assertStringContainsString('secret VARCHAR(255) NULL INVISIBLE', $sql);
        $this->assertStringContainsString('`small_id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT', $sql);
    }

    public function testMysqlTableOptions(): void
    {
        $sql = $this->sql('mysql', function (Blueprint $table) {
            $table->engine('MyISAM')->charset('utf8mb4')->collation('utf8mb4_unicode_ci')->comment('Blog posts');
            $table->id();
        });

        $this->assertStringContainsString(
            ") ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Blog posts'",
            $sql
        );
    }

    public function testMysqlChangeAfterAndFirst(): void
    {
        $sql = $this->sql('mysql', function (Blueprint $table) {
            $table->string('title', 100)->nullable()->change();
            $table->string('slug')->first();
        }, false);

        $this->assertStringContainsString('ALTER TABLE `posts` MODIFY COLUMN title VARCHAR(100) NULL', $sql);
        $this->assertStringContainsString('ADD COLUMN slug VARCHAR(255) NOT NULL FIRST', $sql);
    }

    public function testMysqlIndexTypesAndDrops(): void
    {
        $sql = $this->sql('mysql', function (Blueprint $table) {
            $table->index(['a', 'b'])->algorithm('hash');
            $table->fullText('body');
            $table->spatialIndex('area');
            $table->dropIndex('old_idx');
            $table->dropUnique(['email']);
            $table->dropPrimary();
            $table->renameIndex('a', 'b');
        }, false);

        $this->assertStringContainsString('CREATE INDEX `idx_posts_a_b` ON `posts` (`a`, `b`) USING hash', $sql);
        $this->assertStringContainsString('CREATE FULLTEXT INDEX `posts_body_fulltext` ON `posts` (`body`)', $sql);
        $this->assertStringContainsString('CREATE SPATIAL INDEX `posts_area_spatial` ON `posts` (`area`)', $sql);
        $this->assertStringContainsString('DROP INDEX `old_idx` ON `posts`', $sql);
        $this->assertStringContainsString('DROP INDEX `posts_email_unique` ON `posts`', $sql);
        $this->assertStringContainsString('ALTER TABLE `posts` DROP PRIMARY KEY', $sql);
        $this->assertStringContainsString('ALTER TABLE `posts` RENAME INDEX `a` TO `b`', $sql);
    }

    public function testMysqlCompositePrimaryKeyInCreate(): void
    {
        $sql = $this->sql('mysql', function (Blueprint $table) {
            $table->integer('a');
            $table->integer('b');
            $table->primary(['a', 'b']);
        });

        $this->assertStringContainsString('PRIMARY KEY (`a`, `b`)', $sql);
        $this->assertSame(1, substr_count($sql, 'PRIMARY KEY'));
    }

    public function testSingleColumnPrimaryKeyIsNotDuplicated(): void
    {
        $sql = $this->sql('mysql', fn(Blueprint $t) => $t->string('code')->primary());

        $this->assertSame(1, substr_count($sql, 'PRIMARY KEY'));
    }

    public function testMorphsCreateColumnsAndCompositeIndex(): void
    {
        $sql = $this->sql('mysql', fn(Blueprint $t) => $t->morphs('taggable'));

        $this->assertStringContainsString('taggable_type VARCHAR(255) NOT NULL', $sql);
        $this->assertStringContainsString('taggable_id BIGINT UNSIGNED NOT NULL', $sql);
        $this->assertStringContainsString(
            'CREATE INDEX `idx_posts_taggable_type_taggable_id` ON `posts` (`taggable_type`, `taggable_id`)',
            $sql
        );

        $drop = $this->sql('mysql', fn(Blueprint $t) => $t->dropMorphs('taggable'), false);
        $this->assertStringContainsString('DROP INDEX `idx_posts_taggable_type_taggable_id` ON `posts`', $drop);
        $this->assertStringContainsString('DROP COLUMN `taggable_type`', $drop);
    }

    public function testLongIndexNamesAreShortened(): void
    {
        $sql = $this->sql('mysql', fn(Blueprint $t) => $t->index([
            'a_really_long_column_name_one',
            'a_really_long_column_name_two',
            'a_really_long_column_name_three',
        ]), false);

        preg_match('/CREATE INDEX `([^`]+)`/', $sql, $match);
        $this->assertLessThanOrEqual(63, strlen($match[1]));
    }

    public function testStringDefaultsAreEscapedPerDriver(): void
    {
        $mysql = $this->sql('mysql', fn(Blueprint $t) => $t->string('a')->default("it's \\ ok"));
        $pgsql = $this->sql('pgsql', fn(Blueprint $t) => $t->string('a')->default("it's"));

        $this->assertStringContainsString("DEFAULT 'it''s \\\\ ok'", $mysql);
        $this->assertStringContainsString("DEFAULT 'it''s'", $pgsql);
    }

    public function testInjectionInIdentifiersIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->sql('mysql', fn(Blueprint $t) => $t->string('a')->collation("x'; DROP TABLE users; --"));
    }

    // -------------------------------------------------------- PostgreSQL

    public function testPostgresChangeColumn(): void
    {
        $sql = $this->sql('pgsql', fn(Blueprint $t) => $t->string('title', 100)->nullable()->default('x')->change(), false);

        $this->assertStringContainsString(
            'ALTER TABLE "posts" ALTER COLUMN "title" TYPE VARCHAR(100) USING "title"::VARCHAR(100)',
            $sql
        );
        $this->assertStringContainsString('ALTER COLUMN "title" DROP NOT NULL', $sql);
        $this->assertStringContainsString("ALTER COLUMN \"title\" SET DEFAULT 'x'", $sql);
    }

    public function testPostgresCommentsIndexesAndSerial(): void
    {
        $sql = $this->sql('pgsql', function (Blueprint $table) {
            $table->comment('Posts');
            $table->increments('id');
            $table->string('title')->comment('Headline');
            $table->integer('seq')->autoIncrement();
            $table->fullText('title');
            $table->index('title')->algorithm('hash');
            $table->spatialIndex('area');
            $table->dropPrimary();
        });

        $this->assertStringContainsString('"id" SERIAL NOT NULL', $sql);
        $this->assertStringContainsString('seq SERIAL NOT NULL', $sql);
        $this->assertStringContainsString('COMMENT ON COLUMN "posts"."title" IS \'Headline\'', $sql);
        $this->assertStringContainsString('COMMENT ON TABLE "posts" IS \'Posts\'', $sql);
        $this->assertStringContainsString("USING GIN (to_tsvector('english', coalesce(\"title\", '')))", $sql);
        $this->assertStringContainsString('CREATE INDEX "idx_posts_title" ON "posts" USING hash ("title")', $sql);
        $this->assertStringContainsString('USING GIST ("area")', $sql);
        $this->assertStringContainsString('DROP CONSTRAINT "posts_pkey"', $sql);
    }

    public function testPostgresTimePrecisionAndCollation(): void
    {
        $sql = $this->sql('pgsql', function (Blueprint $table) {
            $table->timestampTz('seen_at', 6);
            $table->string('name')->collation('en_US');
        });

        $this->assertStringContainsString('seen_at TIMESTAMPTZ(6) NOT NULL', $sql);
        $this->assertStringContainsString('name VARCHAR(255) COLLATE "en_US" NOT NULL', $sql);
    }

    // --------------------------------------------------------------- misc

    public function testTemporaryTable(): void
    {
        $this->migrate('scratch', function (Blueprint $table) {
            $table->temporary();
            $table->id();
        });

        $this->assertSame(
            'temp',
            $this->pdo->query("SELECT 'temp' FROM sqlite_temp_master WHERE name = 'scratch'")->fetchColumn()
        );
    }

    public function testGeneratedColumnOnSqlite(): void
    {
        $this->migrate('posts', function (Blueprint $table) {
            $table->id();
            $table->integer('price');
            $table->integer('with_tax')->virtualAs('price * 2')->nullable();
        });

        $this->pdo->exec('INSERT INTO posts (price) VALUES (5)');
        $this->assertSame('10', (string) $this->pdo->query('SELECT with_tax FROM posts')->fetchColumn());
    }

    public function testRememberTokenSoftDeletesAndTimestampsHelpers(): void
    {
        $this->migrate('users', function (Blueprint $table) {
            $table->id();
            $table->rememberToken();
            $table->softDeletes();
            $table->timestamps();
            $table->ulid();
        });

        $this->assertSame(
            ['id', 'remember_token', 'deleted_at', 'created_at', 'updated_at', 'ulid'],
            $this->columns('users')
        );
    }

    public function testNothingToDoThrows(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->blueprint('posts', fn(Blueprint $t) => null, false)->toSql();
    }
}
