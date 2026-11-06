<?php

namespace Tests\Unit\Session;

use PDO;
use PHPUnit\Framework\TestCase;
use Phaseolies\Config\Config;
use Phaseolies\Database\Database;
use Phaseolies\Database\Migration\Schema;
use Phaseolies\DI\Container;
use Phaseolies\Session\Exceptions\SessionException;
use Phaseolies\Session\RotationPointer;
use Phaseolies\Session\Stores\DatabaseStore;
use Phaseolies\Support\Facades\Facade;
use Tests\Support\MockContainer;

/**
 * Runs against SQLite by default. DatabaseStoreMysqlTest and
 * DatabaseStorePgsqlTest run the same tests on MySQL and PostgreSQL when
 * DOPPAR_TEST_MYSQL_* / DOPPAR_TEST_PGSQL_* are configured.
 */
class DatabaseStoreTest extends TestCase
{
    protected const CONNECTION = 'session_store_test';

    protected const SECOND_CONNECTION = 'session_store_test_second';

    protected const TABLE = 'doppar_test_sessions';

    protected string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/doppar-session-db-' . bin2hex(random_bytes(5));
        mkdir($this->dir, 0700, true);

        $container = new MockContainer();
        Container::setInstance($container);
        $this->resetConfig();

        Config::set('database.connections.' . static::CONNECTION, $this->connectionConfig());
        Config::set('database.connections.' . static::SECOND_CONNECTION, $this->connectionConfig());
        Config::set('database.default', static::CONNECTION);

        $container->bind('db', fn () => new Database());
        $container->bind('schema', fn () => new Schema());

        if (method_exists(Facade::class, 'clearResolvedInstances')) {
            Facade::clearResolvedInstances();
        }

        $this->dropTable();
        $this->migrate();
    }

    protected function tearDown(): void
    {
        $this->dropTable();

        foreach (['connections', 'drivers', 'transactions'] as $property) {
            (new \ReflectionClass(Database::class))->getProperty($property)->setValue(null, []);
        }

        $this->resetConfig();
        (new \ReflectionClass(Container::class))->getProperty('instance')->setValue(null, null);

        foreach (glob($this->dir . '/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->dir);
    }

    /**
     * The scratch connection the store runs against.
     *
     * @return array
     */
    protected function connectionConfig(): array
    {
        return ['driver' => 'sqlite', 'database' => $this->dir . '/sessions.sqlite', 'prefix' => ''];
    }

    public function test_it_round_trips_session_data_including_objects_and_binary_strings(): void
    {
        $store = $this->store();
        $id = $this->id();
        $data = ['user' => 7, 'nul' => "a\0b", 'object' => new \ArrayObject(['x' => 1]), 'utf8' => 'héllo'];

        $this->assertSame($id, $store->write($id, $data));

        $record = $this->store()->read($id);

        $this->assertSame($id, $record['id']);
        $this->assertSame("a\0b", $record['data']['nul']);
        $this->assertSame('héllo', $record['data']['utf8']);
        $this->assertEquals(new \ArrayObject(['x' => 1]), $record['data']['object']);
    }

    public function test_a_write_replaces_the_stored_session(): void
    {
        $store = $this->store();
        $id = $this->id();

        $store->write($id, ['step' => 1]);
        $store->write($id, ['step' => 2]);

        $this->assertSame(['step' => 2], $store->read($id)['data']);
        $this->assertSame(1, $this->countRows());
    }

    public function test_it_records_the_user_ip_address_and_user_agent(): void
    {
        $store = $this->store(user: fn (array $data) => $data['user'] ?? null, client: fn () => [
            'ip_address' => '203.0.113.9',
            'user_agent' => 'Mozilla/5.0 Test',
        ]);

        $store->write($id = $this->id(), ['user' => 42]);

        $row = $this->row($id);

        $this->assertSame('42', (string) $row['user_id']);
        $this->assertSame('203.0.113.9', $row['ip_address']);
        $this->assertSame('Mozilla/5.0 Test', $row['user_agent']);
    }

    public function test_a_session_without_a_user_stores_no_user(): void
    {
        $store = $this->store(user: fn (array $data) => $data['user'] ?? null);

        $store->write($id = $this->id(), ['cart' => [1, 2]]);

        $this->assertNull($this->row($id)['user_id']);
    }

    public function test_hostile_user_agents_are_bounded_and_stored_as_printable_ascii(): void
    {
        $store = $this->store(client: fn () => [
            'ip_address' => '198.51.100.1',
            'user_agent' => "Bad\xC3\x28Agent\n" . str_repeat('A', 5000),
        ]);

        $store->write($id = $this->id(), ['x' => 1]);

        $agent = $this->row($id)['user_agent'];

        $this->assertSame(1024, strlen($agent));
        $this->assertStringStartsWith('Bad?(Agent?', $agent);
        $this->assertMatchesRegularExpression('/^[\x20-\x7E]+$/', $agent);
    }

    public function test_a_failing_client_resolver_does_not_stop_the_session_from_saving(): void
    {
        $store = $this->store(client: function (): array {
            throw new \RuntimeException('no request');
        });

        $store->write($id = $this->id(), ['x' => 1]);

        $this->assertSame(['x' => 1], $store->read($id)['data']);
        $this->assertNull($this->row($id)['ip_address']);
    }

    public function test_it_rejects_ids_that_are_not_generated_ids(): void
    {
        $store = $this->store();

        $this->assertNull($store->read('../../etc/passwd'));
        $this->assertNull($store->read(str_repeat('a', 40) . "\n"));
        $this->assertNull($store->read(str_repeat('A', 40)));

        $this->expectException(SessionException::class);

        $store->write('not-an-id', ['x' => 1]);
    }

    public function test_unknown_expired_and_corrupt_sessions_read_as_missing(): void
    {
        $store = $this->store(lifetime: 60);

        $this->assertNull($store->read($this->id()));

        $store->write($expired = $this->id(), ['x' => 1]);
        $this->setActivity($expired, time() - 120);

        $this->assertNull($this->store(lifetime: 60)->read($expired));
        $this->assertNull($this->row($expired), 'An expired session is removed when it is read.');

        $store->write($corrupt = $this->id(), ['x' => 1]);
        $this->pdo()->prepare('UPDATE ' . static::TABLE . ' SET payload = ? WHERE id = ?')->execute(['%%not-base64%%', $corrupt]);

        $this->assertNull($this->store()->read($corrupt));
    }

    public function test_touch_records_the_client_again(): void
    {
        $client = ['ip_address' => '203.0.113.1', 'user_agent' => 'Laptop'];
        $store = $this->store(client: function () use (&$client): array {
            return $client;
        });

        $store->write($id = $this->id(), ['x' => 1]);
        $this->setActivity($id, time() - 3600);

        $client = ['ip_address' => '198.51.100.7', 'user_agent' => 'Laptop on another network'];
        $this->store(client: fn (): array => $client)->touch($id);

        $this->assertSame('198.51.100.7', $this->row($id)['ip_address']);
        $this->assertSame('Laptop on another network', $this->row($id)['user_agent']);
    }

    public function test_last_activity_holds_times_after_2038(): void
    {
        $this->store()->write($id = $this->id(), ['x' => 1]);
        $this->setActivity($id, 4102444800); // 2100-01-01

        $this->assertSame(4102444800, (int) $this->row($id)['last_activity']);
    }

    public function test_touch_refreshes_a_stale_session_and_skips_a_fresh_one(): void
    {
        $store = $this->store(lifetime: 7200);
        $store->write($id = $this->id(), ['x' => 1]);

        // Fresh in this process: no write at all.
        $this->setActivity($id, $stale = time() - 3600);
        $store->touch($id);
        $this->assertSame($stale, (int) $this->row($id)['last_activity']);

        // A process that has not seen it recently refreshes it.
        $this->store(lifetime: 7200)->touch($id);
        $this->assertGreaterThanOrEqual(time() - 1, (int) $this->row($id)['last_activity']);
    }

    public function test_destroy_removes_the_session(): void
    {
        $store = $this->store();
        $store->write($id = $this->id(), ['x' => 1]);

        $store->destroy($id);

        $this->assertNull($store->read($id));
        $this->assertSame(0, $this->countRows());
    }

    public function test_gc_removes_only_expired_sessions(): void
    {
        $store = $this->store(lifetime: 60);
        $store->write($live = $this->id(), ['x' => 1]);
        $store->write($old = $this->id(), ['x' => 1]);
        $this->setActivity($old, time() - 120);

        $this->assertSame(1, $store->gc(60));
        $this->assertNotNull($this->row($live));
        $this->assertNull($this->row($old));
    }

    public function test_sweep_removes_a_bounded_slice_of_expired_sessions(): void
    {
        $store = $this->store(lifetime: 60);
        $this->insertExpired(1005);

        $this->assertSame(1000, $store->sweep(60));
        $this->assertSame(5, $this->countRows());
        $this->assertSame(5, $store->sweep(60));
        $this->assertSame(0, $this->countRows());
    }

    public function test_flush_removes_every_session(): void
    {
        $store = $this->store();
        $store->write($this->id(), ['x' => 1]);
        $store->write($this->id(), ['x' => 2]);

        $this->assertSame(2, $store->flush());
        $this->assertSame(0, $this->countRows());
    }

    public function test_user_sessions_lists_the_live_sessions_of_one_user_most_recent_first(): void
    {
        $store = $this->store(lifetime: 600, user: fn (array $data) => $data['user'] ?? null);

        $store->write($older = $this->id(), ['user' => 1]);
        $store->write($newer = $this->id(), ['user' => 1]);
        $store->write($expired = $this->id(), ['user' => 1]);
        $store->write($otherUser = $this->id(), ['user' => 2]);
        $this->setActivity($older, time() - 100);
        $this->setActivity($expired, time() - 1000);

        $sessions = $store->userSessions(1);

        $this->assertSame([$newer, $older], array_column($sessions, 'id'));
        $this->assertIsInt($sessions[0]['last_activity']);
        $this->assertSame([], $store->userSessions(99));
    }

    public function test_destroy_user_sessions_ends_every_session_of_the_user_except_one(): void
    {
        $store = $this->store(user: fn (array $data) => $data['user'] ?? null);

        $store->write($current = $this->id(), ['user' => 5]);
        $store->write($laptop = $this->id(), ['user' => 5]);
        $store->write($phone = $this->id(), ['user' => 5]);
        $store->write($someoneElse = $this->id(), ['user' => 6]);

        $this->assertSame(2, $store->destroyUserSessions(5, [$current]));

        $this->assertNotNull($store->read($current));
        $this->assertNull($store->read($laptop));
        $this->assertNull($store->read($phone));
        $this->assertNotNull($store->read($someoneElse));
    }

    public function test_destroy_user_sessions_keeps_what_a_kept_session_was_rotated_into(): void
    {
        $store = $this->store(user: fn (array $data) => $data['user'] ?? null);

        // This device's id was rotated by a concurrent request: the old id is
        // a pointer and the session lives on under the new id.
        $store->write($new = $this->id(), ['user' => 5]);
        $store->write($old = $this->id(), RotationPointer::make($new, time() + 60));
        $store->write($phone = $this->id(), ['user' => 5]);

        $this->assertSame(1, $store->destroyUserSessions(5, [$old]));

        $this->assertNotNull($store->read($new));
        $this->assertNull($store->read($phone));
    }

    public function test_destroy_user_sessions_follows_a_rotation_that_happens_while_it_runs(): void
    {
        $phone = $this->id();
        $rotated = $this->id();

        // Rotates the phone's session after the sessions were listed and
        // before they are destroyed.
        $store = new class (fn () => $this->pdo(), static::TABLE, $phone, $rotated) extends DatabaseStore {
            public function __construct(\Closure $connection, string $table, private string $phone, private string $rotated)
            {
                parent::__construct($connection, $table, 7200, fn (array $data) => $data['user'] ?? null);
            }

            protected function lineage(array $ids): array
            {
                $this->write($this->rotated, ['user' => 5]);
                $this->write($this->phone, RotationPointer::make($this->rotated, time() + 60));

                return parent::lineage($ids);
            }
        };

        $store->write($current = $this->id(), ['user' => 5]);
        $store->write($phone, ['user' => 5]);

        $store->destroyUserSessions(5, [$current]);

        $this->assertNull($store->read($phone));
        $this->assertNull($store->read($rotated), 'The session the phone was rotated into is ended too.');
        $this->assertNotNull($store->read($current));
    }

    public function test_a_schema_qualified_table_name_is_rejected(): void
    {
        // The migration would quote it as one identifier and the store as two.
        $this->assertFalse(DatabaseStore::isValidTableName('app.sessions'));
        $this->assertTrue(DatabaseStore::isValidTableName('user_sessions'));

        $this->expectException(SessionException::class);

        new DatabaseStore(fn () => $this->pdo(), 'app.sessions');
    }

    public function test_a_callback_under_a_lock_can_use_the_store_without_waiting_for_itself(): void
    {
        $store = $this->store();
        $store->write($id = $this->id(), ['x' => 1]);

        $started = microtime(true);

        $result = $store->synchronized($id, function () use ($store, $id): string {
            $store->write($id, ['x' => 2]);
            $store->destroy($id);

            return 'done';
        });

        $this->assertSame('done', $result);
        $this->assertLessThan(1.0, microtime(true) - $started);
        $this->assertNull($store->read($id));
    }

    public function test_a_second_connection_waits_for_the_lock_then_gives_up_and_reports(): void
    {
        $holder = $this->store();
        $waiter = $this->store(connection: static::SECOND_CONNECTION, lockTimeout: 0.2);
        $holder->write($id = $this->id(), ['x' => 1]);

        $ran = $holder->synchronized($id, fn () => $waiter->synchronized($id, fn () => 'still ran'));

        $this->assertSame('still ran', $ran);
        $this->assertCount(1, $waiter->reports);
        $this->assertStringContainsString('Session lock was not acquired within 0.2 seconds', $waiter->reports[0]);
    }

    public function test_the_lock_is_free_for_another_connection_after_a_callback_throws(): void
    {
        $holder = $this->store();
        $other = $this->store(connection: static::SECOND_CONNECTION, lockTimeout: 0.2);
        $holder->write($id = $this->id(), ['x' => 1]);

        try {
            $holder->synchronized($id, function (): void {
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException) {
        }

        $other->synchronized($id, fn () => null);

        $this->assertSame([], $other->reports);
    }

    public function test_a_lock_on_one_session_does_not_block_another_session(): void
    {
        $holder = $this->store();
        $other = $this->store(connection: static::SECOND_CONNECTION, lockTimeout: 0.2);
        $holder->write($first = $this->id(), ['x' => 1]);
        $other->write($second = $this->id(), ['x' => 1]);

        if ($this->pdo()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $this->markTestSkipped('SQLite has a single writer, so its lock covers the whole database.');
        }

        $holder->synchronized($first, fn () => $other->synchronized($second, fn () => null));

        $this->assertSame([], $other->reports);
    }

    public function test_an_invalid_table_name_is_rejected(): void
    {
        $this->expectException(SessionException::class);

        new DatabaseStore(fn () => $this->pdo(), 'sessions; DROP TABLE users');
    }

    public function test_a_missing_table_explains_how_to_create_it(): void
    {
        $store = new DatabaseStore(fn () => $this->pdo(), 'missing_sessions_table');

        $this->expectException(SessionException::class);
        $this->expectExceptionMessage('php pool session:table');

        $store->write($this->id(), ['x' => 1]);
    }

    public function test_it_keeps_working_when_the_connection_is_replaced(): void
    {
        $pdo = $this->pdo();
        $store = new RecordingDatabaseStore(function () use (&$pdo): PDO {
            return $pdo;
        }, static::TABLE);

        $store->write($id = $this->id(), ['x' => 1]);

        // A fresh connection to the same database, e.g. after a reconnect.
        (new \ReflectionClass(Database::class))->getProperty('connections')->setValue(null, []);
        $pdo = Database::getPdoInstance(static::CONNECTION);

        $this->assertSame(['x' => 1], $store->read($id)['data']);
    }

    public function test_it_is_never_client_side(): void
    {
        $this->assertFalse($this->store()->isClientSide());
    }

    /**
     * @param int $lifetime
     * @param \Closure|null $user
     * @param \Closure|null $client
     * @param string|null $connection
     * @param float $lockTimeout
     * @return RecordingDatabaseStore
     */
    protected function store(
        int $lifetime = 7200,
        ?\Closure $user = null,
        ?\Closure $client = null,
        ?string $connection = null,
        float $lockTimeout = 2.0
    ): RecordingDatabaseStore {
        $connection ??= static::CONNECTION;

        return new RecordingDatabaseStore(
            static fn (): PDO => Database::getPdoInstance($connection),
            static::TABLE,
            $lifetime,
            $user,
            $client,
            $lockTimeout
        );
    }

    protected function id(): string
    {
        return bin2hex(random_bytes(20));
    }

    protected function pdo(): PDO
    {
        return Database::getPdoInstance(static::CONNECTION);
    }

    protected function row(string $id): ?array
    {
        $statement = $this->pdo()->prepare('SELECT * FROM ' . static::TABLE . ' WHERE id = ?');
        $statement->execute([$id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    protected function countRows(): int
    {
        return (int) $this->pdo()->query('SELECT COUNT(*) FROM ' . static::TABLE)->fetchColumn();
    }

    protected function setActivity(string $id, int $time): void
    {
        $this->pdo()->prepare('UPDATE ' . static::TABLE . ' SET last_activity = ? WHERE id = ?')->execute([$time, $id]);
    }

    protected function insertExpired(int $count): void
    {
        $pdo = $this->pdo();
        $statement = $pdo->prepare('INSERT INTO ' . static::TABLE . ' (id, payload, last_activity) VALUES (?, ?, ?)');
        $payload = base64_encode(serialize(['x' => 1]));

        $pdo->beginTransaction();

        for ($i = 0; $i < $count; $i++) {
            $statement->execute([$this->id(), $payload, time() - 3600]);
        }

        $pdo->commit();
    }

    /**
     * Create the table with the migration "php pool session:table" generates.
     *
     * @return void
     */
    protected function migrate(): void
    {
        $stub = (string) file_get_contents(dirname(__DIR__, 2) . '/src/Phaseolies/Console/Commands/stubs/session/create_sessions_table.stub');
        $file = $this->dir . '/create_sessions_table.php';

        // As generated for a configured session.connection.
        file_put_contents($file, str_replace(
            ['{{ schema }}', '{{ table }}'],
            ["Schema::connection('" . static::CONNECTION . "')->", static::TABLE],
            $stub
        ));

        (require $file)->up();
    }

    protected function dropTable(): void
    {
        try {
            $this->pdo()->exec('DROP TABLE IF EXISTS ' . static::TABLE);
        } catch (\Throwable) {
        }
    }

    protected function resetConfig(): void
    {
        $reflection = new \ReflectionClass(Config::class);

        foreach (['config' => [], 'cacheFile' => null, 'loadedFromCache' => false, 'fileHashes' => []] as $name => $value) {
            if ($reflection->hasProperty($name)) {
                $reflection->getProperty($name)->setValue(null, $value);
            }
        }
    }
}

/**
 * Records what the store reports instead of logging it.
 */
class RecordingDatabaseStore extends DatabaseStore
{
    /**
     * @var string[]
     */
    public array $reports = [];

    protected function report(string $message): void
    {
        $this->reports[] = $message;
    }
}
