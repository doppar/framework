<?php

namespace Tests\Unit\Session;

use Phaseolies\Http\InputBag;
use Phaseolies\Http\Request;
use Phaseolies\Http\Response;
use Phaseolies\Http\Response\Cookie;
use Phaseolies\Http\ServerBag;
use Phaseolies\Session\Stores\ArrayStore;
use Phaseolies\Session\Stores\FileStore;
use Phaseolies\Support\Session;
use PHPUnit\Framework\TestCase;

/**
 * Automatic id rotation next to other requests on the same session.
 */
class RotationTest extends TestCase
{
    private const COOKIE = 'test_session';

    private string $path;

    private array $config;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/doppar_rotation_' . bin2hex(random_bytes(6));
        $this->config = [
            'cookie' => self::COOKIE,
            'lifetime' => 120,
            'stateless' => [],
            'lottery' => [0, 100],
            'secure' => false,
        ];
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->path)) {
            return;
        }

        $entries = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($entries as $entry) {
            $entry->isDir() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
        }

        @rmdir($this->path);
    }

    private function store(): FileStore
    {
        return new FileStore($this->path, 7200);
    }

    private function request(array $cookies = []): Request
    {
        $request = new class extends Request {
            public function __construct()
            {
            }

            public function getPath(): string
            {
                return '/';
            }
        };

        $request->cookies = new InputBag($cookies);
        $request->server = new ServerBag([]);

        return $request;
    }

    private function session(string $cookie, $store = null): Session
    {
        $session = new Session($store ?? $this->store(), $this->config);
        $session->attach($this->request([self::COOKIE => $cookie]));

        return $session;
    }

    private function commit(Session $session): Response
    {
        $response = new Response();
        $session->commit($response);

        return $response;
    }

    private function cookie(Response $response): ?Cookie
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === self::COOKIE) {
                return $cookie;
            }
        }

        return null;
    }

    private function dueSession(array $data = ['user' => 7], $store = null): string
    {
        $id = bin2hex(random_bytes(20));
        ($store ?? $this->store())->write($id, $data + ['last_regenerated' => time() - 99999]);

        return $id;
    }

    /**
     * Every stored record that holds session data, which excludes the
     * short lived pointers an old id leaves behind.
     *
     * @return array<string, array>
     */
    private function liveRecords(): array
    {
        $records = [];

        foreach (glob($this->path . '/*/*') ?: [] as $file) {
            $data = @unserialize((string) file_get_contents($file));

            if (is_array($data) && !isset($data['_rotated_to'])) {
                $records[basename($file)] = $data;
            }
        }

        return $records;
    }

    public function test_a_logout_that_overlaps_an_automatic_rotation_is_not_undone(): void
    {
        $id = $this->dueSession();
        $logout = $this->session($id);
        $rotating = $this->session($id);
        $logout->all();
        $rotating->all();

        $logout->invalidate();
        $this->commit($logout);

        $late = $this->commit($rotating);

        foreach ($this->liveRecords() as $record) {
            $this->assertArrayNotHasKey('user', $record, 'The logged-out user is logged in again.');
        }

        $this->assertNull($this->cookie($late), 'The late request sent a session cookie.');
    }

    public function test_a_logout_that_arrives_after_an_automatic_rotation_ends_the_session(): void
    {
        $id = $this->dueSession();
        $logout = $this->session($id);
        $rotating = $this->session($id);
        $logout->all();
        $rotating->all();

        $this->commit($rotating);

        $logout->invalidate();
        $this->commit($logout);

        foreach ($this->liveRecords() as $record) {
            $this->assertArrayNotHasKey('user', $record, 'The rotation survived the logout.');
        }
    }

    public function test_destroying_a_session_that_was_rotated_meanwhile_ends_the_whole_lineage(): void
    {
        $id = $this->dueSession();
        $destroy = $this->session($id);
        $rotating = $this->session($id);
        $destroy->all();
        $rotating->all();

        $rotated = $this->cookie($this->commit($rotating))->getValue();

        $destroy->destroy();
        $this->commit($destroy);

        $this->assertNull($this->store()->read($rotated), 'The rotated session outlived the destroy.');
        $this->assertSame([], $this->liveRecords());
    }

    public function test_parallel_requests_on_a_session_due_for_rotation_keep_one_session(): void
    {
        $id = $this->dueSession(['user' => 7, 'cart' => ['x']]);

        $requests = [];

        foreach (['a', 'b', 'c'] as $name) {
            $requests[$name] = $this->session($id);
            $requests[$name]->all();
        }

        $requests['a']->put('flash_a', 'a');
        $requests['b']->put('flash_b', 'b');
        $requests['c']->put('cart', ['x', 'apple']);

        $cookies = [];

        foreach ($requests as $session) {
            $cookies[] = $this->cookie($this->commit($session))->getValue();
        }

        $this->assertCount(1, array_unique($cookies), 'The requests split the session over several ids.');
        $this->assertNotSame($id, $cookies[0], 'The session id was not rotated.');

        $live = $this->liveRecords();

        $this->assertCount(1, $live, 'More than one copy of the session is stored.');

        $data = $live[$cookies[0]];

        $this->assertSame(7, $data['user']);
        $this->assertSame('a', $data['flash_a']);
        $this->assertSame('b', $data['flash_b']);
        $this->assertSame(['x', 'apple'], $data['cart']);
    }

    public function test_a_request_that_still_sends_the_old_cookie_follows_the_rotation(): void
    {
        $id = $this->dueSession();
        $rotating = $this->session($id);
        $rotating->all();
        $new = $this->cookie($this->commit($rotating))->getValue();

        $late = $this->session($id);

        $this->assertSame(7, $late->get('user'), 'The old id did not lead to the session.');

        $response = $this->commit($late);

        $this->assertSame($new, $this->cookie($response)->getValue());
        $this->assertGreaterThan(time(), $this->cookie($response)->getExpiresTime(), 'The user was logged out.');
    }

    public function test_the_old_id_stops_working_when_the_grace_period_is_over(): void
    {
        $id = $this->dueSession();
        $rotating = $this->session($id);
        $rotating->all();
        $this->commit($rotating);

        // Let the pointer left on the old id expire.
        $this->store()->write($id, ['_rotated_to' => bin2hex(random_bytes(20)), '_rotated_until' => time() - 1]);

        $this->assertNull($this->session($id)->get('user'));
    }

    public function test_a_pointer_to_a_session_that_is_gone_leads_nowhere(): void
    {
        $id = $this->dueSession();
        $rotating = $this->session($id);
        $rotating->all();
        $new = $this->cookie($this->commit($rotating))->getValue();

        $this->store()->destroy($new);

        $this->assertNull($this->session($id)->get('user'));
    }

    public function test_an_explicit_regenerate_leaves_no_pointer_to_the_new_session(): void
    {
        // The old id of a login must never lead to the session that logged in:
        // that id may have been planted by an attacker (session fixation).
        $id = $this->dueSession(['user' => 7]);
        $login = $this->session($id);
        $login->regenerate();
        $new = $this->cookie($this->commit($login))->getValue();

        $this->assertNotSame($id, $new);
        $this->assertNull($this->store()->read($id), 'The old id still exists.');
        $this->assertNull($this->session($id)->get('user'));
    }

    public function test_a_request_that_loaded_a_due_session_and_saves_late_sends_the_new_id(): void
    {
        $id = $this->dueSession();
        $reader = $this->session($id);
        $rotating = $this->session($id);
        $reader->all();
        $rotating->all();

        $new = $this->cookie($this->commit($rotating))->getValue();

        // It follows the rotation instead of sending the id that was rotated away.
        $this->assertSame($new, $this->cookie($this->commit($reader))->getValue());
    }

    public function test_a_read_only_request_does_not_send_an_id_that_was_rotated_after_it_loaded(): void
    {
        // The session is not due yet when the reader loads it ...
        $id = $this->dueSession(['user' => 7, 'last_regenerated' => time() - 100]);
        $reader = $this->session($id);
        $reader->all();

        // ... and becomes due, and is rotated by another request, before it finishes.
        $this->store()->write($id, ['user' => 7, 'last_regenerated' => time() - 99999]);
        $rotating = $this->session($id);
        $rotating->all();
        $new = $this->cookie($this->commit($rotating))->getValue();

        // Sending the old id would overwrite the cookie of the rotated session.
        $this->assertNotSame($id, $new);
        $this->assertNull($this->cookie($this->commit($reader)));
    }

    public function test_a_read_only_request_still_rotates_a_session_that_is_due(): void
    {
        $id = $this->dueSession();
        $session = $this->session($id);
        $session->get('user');

        $new = $this->cookie($this->commit($session))->getValue();

        $this->assertNotSame($id, $new);
        $this->assertSame(7, $this->store()->read($new)['data']['user']);
    }

    public function test_a_rotated_session_is_not_rotated_again_on_its_next_request(): void
    {
        $id = $this->dueSession();
        $first = $this->session($id);
        $first->all();
        $new = $this->cookie($this->commit($first))->getValue();

        $second = $this->session($new);
        $second->put('x', 1);

        $this->assertSame($new, $this->cookie($this->commit($second))->getValue());
    }

    public function test_a_store_that_cannot_lock_rotates_immediately_and_forgets_the_old_id(): void
    {
        $store = new ArrayStore();
        $id = $this->dueSession(['user' => 7], $store);

        $session = $this->session($id, $store);
        $session->all();
        $new = $this->cookie($this->commit($session))->getValue();

        $this->assertNotSame($id, $new);
        $this->assertNull($store->read($id));
        $this->assertSame(7, $store->read($new)['data']['user']);
    }
}
