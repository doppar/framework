<?php

namespace Tests\Unit\Session;

use Phaseolies\Session\Exceptions\SessionException;
use Phaseolies\Session\Stores\FileStore;
use PHPUnit\Framework\TestCase;

class FileStoreTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/doppar_sessions_' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        (new FileStore($this->path))->flush();

        foreach (glob($this->path . '/*') ?: [] as $entry) {
            is_dir($entry) ? @rmdir($entry) : @unlink($entry);
        }

        @rmdir($this->path);
    }

    private function id(string $prefix = 'ab'): string
    {
        return $prefix . bin2hex(random_bytes(19));
    }

    public function test_it_round_trips_session_data(): void
    {
        $store = new FileStore($this->path);
        $id = $this->id();

        $this->assertSame($id, $store->write($id, ['user' => 7, 'nested' => ['a' => 1]]));

        $this->assertSame(
            ['id' => $id, 'data' => ['user' => 7, 'nested' => ['a' => 1]]],
            $store->read($id)
        );
    }

    public function test_it_shards_files_by_the_first_two_characters_of_the_id(): void
    {
        $store = new FileStore($this->path);
        $id = $this->id('cd');
        $store->write($id, ['a' => 1]);

        $this->assertFileExists($this->path . '/cd/' . $id);
    }

    public function test_session_files_are_private_to_the_application_user(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('POSIX permissions do not apply on Windows.');
        }

        $store = new FileStore($this->path);
        $id = $this->id('cd');
        $store->write($id, ['a' => 1]);

        $this->assertSame('0600', substr(sprintf('%o', fileperms($this->path . '/cd/' . $id)), -4));
    }

    public function test_it_rejects_ids_that_are_not_generated_ids(): void
    {
        $store = new FileStore($this->path);
        $store->write($this->id(), ['a' => 1]);

        foreach (['../../etc/passwd', 'abc', str_repeat('A', 40), $this->id() . '/..', '', "ab\0" . str_repeat('c', 37)] as $bad) {
            $this->assertNull($store->read($bad), "Accepted [$bad]");
        }

        $this->expectException(SessionException::class);
        $store->write('../evil', ['a' => 1]);
    }

    public function test_it_treats_unknown_expired_and_corrupt_sessions_as_missing(): void
    {
        $store = new FileStore($this->path, 60);

        $this->assertNull($store->read($this->id()));

        $expired = $this->id();
        $store->write($expired, ['a' => 1]);
        touch($this->path . '/ab/' . $expired, time() - 120);
        $this->assertNull($store->read($expired));
        $this->assertFileDoesNotExist($this->path . '/ab/' . $expired);

        $corrupt = $this->id();
        $store->write($corrupt, ['a' => 1]);
        file_put_contents($this->path . '/ab/' . $corrupt, 'not-a-serialized-array');
        $this->assertNull($store->read($corrupt));

        // A read must never delete a file that may be mid-write; gc() does that.
        $this->assertFileExists($this->path . '/ab/' . $corrupt);
    }

    public function test_touch_slides_the_expiry_and_destroy_removes_the_session(): void
    {
        $store = new FileStore($this->path, 60);
        $id = $this->id();
        $store->write($id, ['a' => 1]);

        touch($this->path . '/ab/' . $id, time() - 50);
        $store->touch($id);
        $this->assertGreaterThan(time() - 5, filemtime($this->path . '/ab/' . $id));

        $store->destroy($id);
        $this->assertNull($store->read($id));
    }

    public function test_gc_removes_only_expired_sessions_and_legacy_native_files(): void
    {
        $store = new FileStore($this->path, 60);
        $fresh = $this->id('aa');
        $old = $this->id('bb');
        $store->write($fresh, ['a' => 1]);
        $store->write($old, ['a' => 1]);
        touch($this->path . '/bb/' . $old, time() - 600);

        file_put_contents($this->path . '/sess_legacy', 'x');
        touch($this->path . '/sess_legacy', time() - 600);

        $this->assertSame(2, $store->gc(60));
        $this->assertNotNull($store->read($fresh));
        $this->assertNull($store->read($old));
        $this->assertFileDoesNotExist($this->path . '/sess_legacy');
    }

    public function test_sweep_is_bounded_to_a_single_shard(): void
    {
        $store = new FileStore($this->path, 60);

        // Fill every shard with one expired session, then sweep once.
        for ($i = 0; $i < 256; $i++) {
            $id = sprintf('%02x', $i) . bin2hex(random_bytes(19));
            $store->write($id, ['a' => 1]);
            touch($this->path . '/' . substr($id, 0, 2) . '/' . $id, time() - 600);
        }

        $this->assertLessThanOrEqual(1, $store->sweep(60));
        $this->assertGreaterThanOrEqual(255, count(glob($this->path . '/*/*')));
    }

    public function test_flush_removes_everything(): void
    {
        $store = new FileStore($this->path);
        $store->write($this->id('aa'), ['a' => 1]);
        $store->write($this->id('bb'), ['a' => 1]);

        $this->assertSame(2, $store->flush());
        $this->assertSame([], glob($this->path . '/*/*') ?: []);
    }

    public function test_writes_leave_no_temporary_files_behind(): void
    {
        $store = new FileStore($this->path);
        $id = $this->id();

        for ($i = 0; $i < 20; $i++) {
            $store->write($id, ['i' => $i]);
        }

        $files = array_values(array_diff(scandir($this->path . '/ab'), ['.', '..']));

        $this->assertSame([$id], $files);
        $this->assertSame(19, $store->read($id)['data']['i']);
    }

    public function test_a_storage_path_containing_glob_characters_still_works(): void
    {
        // [ ] { } are legal in directory names on every OS and make glob() match nothing.
        // * and ? are only added off Windows, where they are not valid in file names.
        $path = $this->path . '/we[ir]d {dir}' . (DIRECTORY_SEPARATOR === '\\' ? '' : ' *?');
        $store = new FileStore($path, 60);

        $fresh = $this->id('aa');
        $old = $this->id('bb');
        $store->write($fresh, ['a' => 1]);
        $store->write($old, ['a' => 1]);
        touch($path . '/bb/' . $old, time() - 600);
        file_put_contents($path . '/sess_legacy', 'x');
        touch($path . '/sess_legacy', time() - 600);

        $this->assertSame(2, $store->gc(60));
        $this->assertNotNull($store->read($fresh));
        $this->assertSame(1, $store->flush());
        $this->assertNull($store->read($fresh));

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
            $entry->isDir() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
        }

        @rmdir($path);
    }

    public function test_a_failing_rename_falls_back_to_writing_in_place(): void
    {
        // Windows can refuse rename() while antivirus or another process has
        // the target open. The session must still be written, with no temp files left.
        $store = new class($this->path) extends FileStore
        {
            public int $attempts = 0;

            protected function move(string $from, string $to): bool
            {
                $this->attempts++;

                return false;
            }
        };

        $id = $this->id();
        $store->write($id, ['user' => 7]);
        $store->write($id, ['user' => 8]);

        $this->assertSame(6, $store->attempts);
        $this->assertSame(8, $store->read($id)['data']['user']);
        $this->assertSame([$id], array_values(array_diff(scandir($this->path . '/ab'), ['.', '..'])));
    }

    public function test_a_transient_rename_failure_is_retried(): void
    {
        $store = new class($this->path) extends FileStore
        {
            protected function move(string $from, string $to): bool
            {
                static $calls = 0;

                return ++$calls < 2 ? false : parent::move($from, $to);
            }
        };

        $id = $this->id();
        $store->write($id, ['user' => 7]);

        $this->assertSame(7, $store->read($id)['data']['user']);
    }

    public function test_destroy_still_invalidates_a_session_whose_file_cannot_be_deleted(): void
    {
        if (PHP_OS_FAMILY === 'Windows' || (function_exists('posix_geteuid') && posix_geteuid() === 0)) {
            $this->markTestSkipped('Needs a POSIX user that cannot delete from a read-only directory.');
        }

        $store = new FileStore($this->path);
        $id = $this->id();
        $store->write($id, ['user' => 7]);

        // Windows cannot delete a file another process has open. Removing write
        // permission from the directory reproduces that: unlink() fails.
        chmod($this->path . '/ab', 0500);

        try {
            $store->destroy($id);
            $this->assertNull($store->read($id), 'A destroyed session must not be readable.');
        } finally {
            chmod($this->path . '/ab', 0700);
        }
    }

    public function test_a_filesystem_root_style_path_is_not_trimmed_away(): void
    {
        $store = new FileStore('/');
        $reflection = new \ReflectionProperty($store, 'path');

        $this->assertSame('/', $reflection->getValue($store));
    }

    private function loggingStore(float $timeout = 0.05): FileStore
    {
        return new class($this->path, 7200, $timeout) extends FileStore {
            /** @var string[] */
            public array $reports = [];

            protected function report(string $message): void
            {
                $this->reports[] = $message;
            }
        };
    }

    /**
     * Take the lock of the shard an id belongs to from outside the store.
     *
     * @return resource
     */
    private function holdLockOf(string $id)
    {
        $directory = $this->path . '/.locks';
        is_dir($directory) || mkdir($directory, 0700, true);

        $handle = fopen($directory . '/' . substr($id, 0, 2), 'c');
        $this->assertTrue(flock($handle, LOCK_EX | LOCK_NB), 'The test could not take the lock.');

        return $handle;
    }

    public function test_giving_up_on_a_session_lock_is_logged_and_the_callback_still_runs(): void
    {
        $store = $this->loggingStore(0.05);
        $id = $this->id();
        $held = $this->holdLockOf($id);

        $ran = $store->synchronized($id, fn () => 'ran');

        flock($held, LOCK_UN);
        fclose($held);

        $this->assertSame('ran', $ran);
        $this->assertCount(1, $store->reports);
        $this->assertStringContainsString('not acquired', $store->reports[0]);
        $this->assertStringContainsString('0.05', $store->reports[0]);
    }

    public function test_a_lock_that_is_acquired_normally_logs_nothing(): void
    {
        $store = $this->loggingStore();

        $this->assertSame('ok', $store->synchronized($this->id(), fn () => 'ok'));
        $this->assertSame([], $store->reports);
    }

    public function test_an_unusable_lock_directory_is_logged_and_the_callback_still_runs(): void
    {
        // A file where the lock directory should be makes it impossible to create.
        mkdir($this->path, 0700, true);
        file_put_contents($this->path . '/.locks', 'in the way');

        $store = $this->loggingStore();

        $this->assertSame('ran', $store->synchronized($this->id(), fn () => 'ran'));
        $this->assertCount(1, $store->reports);
        $this->assertStringContainsString('lock directory', $store->reports[0]);

        unlink($this->path . '/.locks');
    }

    public function test_the_lock_is_released_when_the_callback_throws(): void
    {
        $store = $this->loggingStore();
        $id = $this->id();

        try {
            $store->synchronized($id, function () {
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame('free', $store->synchronized($id, fn () => 'free'));
        $this->assertSame([], $store->reports);
    }

    public function test_destroy_waits_for_the_session_lock(): void
    {
        $id = $this->id();
        $store = new FileStore($this->path);
        $store->write($id, ['user' => 1]);

        // A request is saving the session. A logout must wait for it, or the
        // save could land after the destroy and bring the session back.
        $stored = null;

        $store->synchronized($id, function () use ($id, &$stored) {
            $script = sprintf(
                'require %s; (new Phaseolies\\Session\\Stores\\FileStore(%s, 7200, 10))->destroy(%s);',
                var_export(dirname(__DIR__, 2) . '/vendor/autoload.php', true),
                var_export($this->path, true),
                var_export($id, true)
            );

            $process = proc_open([PHP_BINARY, '-r', $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            $this->assertIsResource($process);

            // Give the child time to reach the lock and block on it.
            usleep(600000);

            $stored = file_exists($this->path . '/' . substr($id, 0, 2) . '/' . $id);

            $this->process = [$process, $pipes];
        });

        [$process, $pipes] = $this->process;
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        proc_close($process);

        $this->assertTrue($stored, 'destroy() removed the session while another request held its lock.');
        $this->assertNull($store->read($id), 'destroy() never ran once the lock was released.');
    }

    /** @var array{0: resource, 1: array}|null */
    private ?array $process = null;

    public function test_an_id_that_ends_in_a_newline_is_not_a_valid_id(): void
    {
        // "$" lets a trailing newline through, which "\z" does not.
        $store = new FileStore($this->path);
        $id = $this->id();
        $store->write($id, ['a' => 1]);

        $this->assertTrue(FileStore::isValidId($id));
        $this->assertFalse(FileStore::isValidId($id . "\n"));
        $this->assertNull($store->read($id . "\n"));

        $store->destroy($id . "\n");
        $this->assertNotNull($store->read($id), 'A look-alike id destroyed the real session.');

        $this->expectException(SessionException::class);
        $store->write($id . "\n", ['a' => 1]);
    }

    public function test_a_callback_under_a_lock_can_use_the_store_without_waiting_for_itself(): void
    {
        $store = $this->loggingStore(5.0);
        $id = $this->id();
        $store->write($id, ['a' => 1]);

        $started = microtime(true);

        $store->synchronized($id, function () use ($store, $id) {
            // Nested, same session: must reuse the lock instead of waiting out the timeout.
            $store->synchronized($id, fn () => null);
            $store->destroy($id);
        });

        $this->assertLessThan(1.0, microtime(true) - $started, 'The nested call waited for the lock it already holds.');
        $this->assertNull($store->read($id));
        $this->assertSame([], $store->reports);
    }

    public function test_the_lock_is_free_again_after_a_nested_call(): void
    {
        $store = $this->loggingStore(0.05);
        $id = $this->id();

        $store->synchronized($id, fn () => $store->synchronized($id, fn () => null));

        $held = $this->holdLockOf($id);
        flock($held, LOCK_UN);
        fclose($held);

        $this->assertSame('ok', $store->synchronized($id, fn () => 'ok'));
        $this->assertSame([], $store->reports);
    }
}
