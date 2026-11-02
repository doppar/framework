<?php

namespace Tests\Unit\Session;

use Phaseolies\Http\Request;
use Phaseolies\Http\ServerBag;
use Phaseolies\Http\Response;
use Phaseolies\Http\InputBag;
use Phaseolies\Session\Stores\ArrayStore;
use Phaseolies\Session\Stores\FileStore;
use Phaseolies\Support\Session;
use PHPUnit\Framework\TestCase;

class SessionTest extends TestCase
{
    private const COOKIE = 'test_session';

    private string $path;

    private array $config;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/doppar_session_' . bin2hex(random_bytes(6));
        $this->config = [
            'driver' => 'file',
            'lifetime' => 120,
            'expire_on_close' => false,
            'cookie' => self::COOKIE,
            'path' => '/',
            'domain' => null,
            'secure' => false,
            'http_only' => true,
            'same_site' => 'lax',
            'stateless' => ['api/*'],
            'lottery' => [0, 100],
        ];
    }

    protected function tearDown(): void
    {
        (new FileStore($this->path))->flush();

        foreach (glob($this->path . '/*') ?: [] as $entry) {
            is_dir($entry) ? @rmdir($entry) : @unlink($entry);
        }

        @rmdir($this->path);
    }

    private function store(): FileStore
    {
        return new FileStore($this->path, 7200);
    }

    /**
     * Build a request for the given path carrying the given cookies.
     */
    private function request(string $path = '/', array $cookies = [], array $server = []): Request
    {
        $request = new class extends Request {
            public string $fakePath = '/';

            public function __construct()
            {
                //
            }

            public function getPath(): string
            {
                return $this->fakePath;
            }
        };

        $request->fakePath = $path;
        $request->cookies = new InputBag($cookies);
        $request->server = new ServerBag($server);

        return $request;
    }

    private function session(?Request $request = null, ?FileStore $store = null): Session
    {
        $session = new Session($store ?? $this->store(), $this->config);
        $session->attach($request ?? $this->request());

        return $session;
    }

    private function commit(Session $session): Response
    {
        $response = new Response();
        $session->commit($response);

        return $response;
    }

    private function sessionCookie(Response $response): ?\Phaseolies\Http\Response\Cookie
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === self::COOKIE) {
                return $cookie;
            }
        }

        return null;
    }

    private function storedFiles(): array
    {
        return glob($this->path . '/*/*') ?: [];
    }

    public function test_a_request_that_never_uses_the_session_stores_nothing_and_sends_no_cookie(): void
    {
        $session = $this->session();

        $response = $this->commit($session);

        $this->assertNull($this->sessionCookie($response));
        $this->assertDirectoryDoesNotExist($this->path);
    }

    public function test_reading_without_a_cookie_does_not_create_a_session(): void
    {
        $session = $this->session();

        $this->assertNull($session->get('missing'));
        $this->assertFalse($session->has('missing'));
        $this->assertSame([], $session->all());

        $this->assertNull($this->sessionCookie($this->commit($session)));
        $this->assertSame([], $this->storedFiles());
    }

    public function test_writing_creates_one_session_and_sends_a_cookie(): void
    {
        $session = $this->session();
        $session->put('user', 7);

        $cookie = $this->sessionCookie($this->commit($session));

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertCount(1, $this->storedFiles());

        $next = $this->session($this->request('/', [self::COOKIE => $cookie->getValue()]));
        $this->assertSame(7, $next->get('user'));
    }

    public function test_a_second_request_with_the_cookie_reuses_the_same_session(): void
    {
        $first = $this->session();
        $first->put('a', 1);
        $cookie = $this->sessionCookie($this->commit($first));

        for ($i = 0; $i < 3; $i++) {
            $session = $this->session($this->request('/', [self::COOKIE => $cookie->getValue()]));
            $session->put('i', $i);
            $this->commit($session);
        }

        $this->assertCount(1, $this->storedFiles());
    }

    public function test_cookieless_requests_that_only_read_never_pile_up_files(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $session = $this->session();
            $session->get('anything');
            $this->commit($session);
        }

        $this->assertSame([], $this->storedFiles());
    }

    public function test_csrf_token_is_created_lazily_and_is_stable(): void
    {
        $session = $this->session();
        $token = $session->token();
        $cookie = $this->sessionCookie($this->commit($session));

        $this->assertSame(32, strlen($token));
        $this->assertSame($token, $session->token());

        $next = $this->session($this->request('/', [self::COOKIE => $cookie->getValue()]));
        $this->assertSame($token, $next->token());
    }

    public function test_stateless_paths_work_in_memory_but_store_nothing(): void
    {
        $session = $this->session($this->request('/api/users'));
        $session->put('a', 1);

        $this->assertTrue($session->isStateless());
        $this->assertSame(1, $session->get('a'));
        $this->assertNotEmpty($session->token());

        $this->assertNull($this->sessionCookie($this->commit($session)));
        $this->assertSame([], $this->storedFiles());
    }

    public function test_stateless_requests_ignore_an_incoming_session_cookie(): void
    {
        $first = $this->session();
        $first->put('secret', 'x');
        $cookie = $this->sessionCookie($this->commit($first));

        $api = $this->session($this->request('/api/me', [self::COOKIE => $cookie->getValue()]));

        $this->assertNull($api->get('secret'));
    }

    public function test_disable_turns_persistence_off_for_a_route(): void
    {
        $session = $this->session($this->request('/webhook'));
        $session->disable();
        $session->put('a', 1);

        $this->assertNull($this->sessionCookie($this->commit($session)));
        $this->assertSame([], $this->storedFiles());
    }

    public function test_regenerate_moves_the_data_to_a_new_id_and_removes_the_old_one(): void
    {
        $first = $this->session();
        $first->put('user', 7);
        $old = $this->sessionCookie($this->commit($first))->getValue();

        $session = $this->session($this->request('/', [self::COOKIE => $old]));
        $session->regenerate();
        $new = $this->sessionCookie($this->commit($session))->getValue();

        $this->assertNotSame($old, $new);
        $this->assertCount(1, $this->storedFiles());
        $this->assertNull($this->store()->read($old));
        $this->assertSame(7, $this->store()->read($new)['data']['user']);
    }

    public function test_invalidate_empties_the_session_and_expires_the_cookie(): void
    {
        $first = $this->session();
        $first->put('user', 7);
        $old = $this->sessionCookie($this->commit($first))->getValue();

        $session = $this->session($this->request('/', [self::COOKIE => $old]));
        $session->invalidate();
        $cookie = $this->sessionCookie($this->commit($session));

        $this->assertSame([], $this->storedFiles());
        $this->assertSame(1, $cookie->getExpiresTime());
    }

    public function test_destroy_removes_the_stored_session(): void
    {
        $first = $this->session();
        $first->put('user', 7);
        $old = $this->sessionCookie($this->commit($first))->getValue();

        $session = $this->session($this->request('/', [self::COOKIE => $old]));
        $session->destroy();
        $this->commit($session);

        $this->assertNull($this->store()->read($old));
        $this->assertSame([], $session->all());
    }

    public function test_logout_style_invalidate_then_new_token_keeps_one_clean_session(): void
    {
        $first = $this->session();
        $first->put('user', 7);
        $old = $this->sessionCookie($this->commit($first))->getValue();

        $session = $this->session($this->request('/', [self::COOKIE => $old]));
        $session->invalidate();
        $session->regenerateToken();
        $new = $this->sessionCookie($this->commit($session))->getValue();

        $this->assertNotSame($old, $new);
        $this->assertNull($this->store()->read($old));
        $this->assertArrayNotHasKey('user', $this->store()->read($new)['data']);
        $this->assertCount(1, $this->storedFiles());
    }

    public function test_a_forged_or_unknown_cookie_is_treated_as_no_session(): void
    {
        foreach (['../../../etc/passwd', str_repeat('a', 40), 'x'] as $value) {
            $session = $this->session($this->request('/', [self::COOKIE => $value]));

            $this->assertNull($session->get('user'));
            $session->put('user', 1);
            $cookie = $this->sessionCookie($this->commit($session));

            $this->assertNotSame($value, $cookie->getValue());
            $this->assertMatchesRegularExpression(FileStore::ID_PATTERN, $cookie->getValue());
        }
    }

    public function test_set_id_ignores_ids_that_are_not_well_formed(): void
    {
        $session = $this->session();
        $before = $session->getId();

        $session->setId('../../x');

        $this->assertSame($before, $session->getId());
    }

    public function test_an_old_session_id_is_rotated_but_keeps_its_data(): void
    {
        $id = bin2hex(random_bytes(20));
        $this->store()->write($id, ['user' => 7, 'last_regenerated' => time() - 99999]);

        $session = $this->session($this->request('/', [self::COOKIE => $id]));
        $this->assertSame(7, $session->get('user'));
        $new = $this->sessionCookie($this->commit($session))->getValue();

        $this->assertNotSame($id, $new);
        $this->assertNull($this->store()->read($id));
        $this->assertSame(7, $this->store()->read($new)['data']['user']);
    }

    public function test_an_unchanged_session_is_not_rewritten(): void
    {
        $first = $this->session();
        $first->put('a', 1);
        $value = $this->sessionCookie($this->commit($first))->getValue();
        $file = $this->path . '/' . substr($value, 0, 2) . '/' . $value;
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Windows does not report stable inode numbers.');
        }

        $inode = fileinode($file);

        $session = $this->session($this->request('/', [self::COOKIE => $value]));
        $session->get('a');
        $this->commit($session);

        clearstatcache();
        $this->assertSame($inode, fileinode($file));
    }

    public function test_peek_data_survives_a_redirect_like_flash(): void
    {
        $first = $this->session();
        $first->flash('status', 'saved');
        $value = $this->sessionCookie($this->commit($first))->getValue();

        $next = $this->session($this->request('/', [self::COOKIE => $value]));
        $this->assertSame('saved', $next->getPeek('status'));
        $this->assertNull($next->get('status'));
    }

    public function test_the_secure_flag_follows_the_request_when_not_configured(): void
    {
        $this->config['secure'] = null;

        foreach ([['HTTPS' => 'on', true], ['HTTPS' => 'off', false]] as $case) {
            $expected = array_pop($case);
            $session = $this->session($this->request('/', [], $case));
            $session->put('a', 1);

            $this->assertSame($expected, $this->sessionCookie($this->commit($session))->isSecure());
        }
    }

    public function test_samesite_none_forces_a_secure_cookie(): void
    {
        $this->config['same_site'] = 'none';
        $session = $this->session();
        $session->put('a', 1);

        $this->assertTrue($this->sessionCookie($this->commit($session))->isSecure());
    }

    public function test_it_works_with_any_store_implementation(): void
    {
        $store = new ArrayStore();
        $session = new Session($store, $this->config);
        $session->attach($this->request());
        $session->put('a', 1);
        $cookie = $this->sessionCookie($this->commit($session));

        $next = new Session($store, $this->config);
        $next->attach($this->request('/', [self::COOKIE => $cookie->getValue()]));

        $this->assertSame(1, $next->get('a'));
    }

    public function test_the_deletion_cookie_is_secure_too_when_samesite_is_none(): void
    {
        $this->config['same_site'] = 'none';
        $this->config['secure'] = false;
        $session = $this->session();
        $session->put('a', 1);
        $cookie = $this->sessionCookie($this->commit($session));

        $next = $this->session($this->request('/', [self::COOKIE => $cookie->getValue()]));
        $next->invalidate();
        $expired = $this->sessionCookie($this->commit($next));

        $this->assertLessThan(time(), $expired->getExpiresTime());
        $this->assertTrue($expired->isSecure());
        $this->assertSame('none', strtolower((string) $expired->getSameSite()));
    }

    private function twoRequestsOnOneSession(): array
    {
        $first = $this->session();
        $first->put('user', 1);
        $first->put('_flash', ['kept' => 'yes']);
        $cookie = $this->sessionCookie($this->commit($first))->getValue();

        $make = fn() => $this->session($this->request('/', [self::COOKIE => $cookie]));
        $a = $make();
        $b = $make();
        $a->get('user');
        $b->get('user');

        return [$cookie, $a, $b];
    }

    public function test_concurrent_requests_do_not_overwrite_each_others_changes(): void
    {
        [$cookie, $a, $b] = $this->twoRequestsOnOneSession();

        $a->put('cart', ['apple']);
        $a->put('_flash', ['kept' => 'yes', 'from_a' => 'a']);
        $b->put('wishlist', ['pear']);
        $b->put('_flash', ['kept' => 'yes', 'from_b' => 'b']);
        $b->forget('user');

        $this->commit($a);
        $this->commit($b);

        $merged = $this->session($this->request('/', [self::COOKIE => $cookie]))->all();

        $this->assertSame(['apple'], $merged['cart']);
        $this->assertSame(['pear'], $merged['wishlist']);
        $this->assertArrayNotHasKey('user', $merged);
        $this->assertSame(['kept' => 'yes', 'from_a' => 'a', 'from_b' => 'b'], $merged['_flash']);
    }

    public function test_the_last_writer_wins_for_the_same_key(): void
    {
        [$cookie, $a, $b] = $this->twoRequestsOnOneSession();

        $a->put('user', 2);
        $b->put('user', 3);
        $this->commit($a);
        $this->commit($b);

        $this->assertSame(3, $this->session($this->request('/', [self::COOKIE => $cookie]))->get('user'));
    }

    public function test_an_invalidated_session_is_not_merged_with_concurrent_changes(): void
    {
        [$cookie, $a, $b] = $this->twoRequestsOnOneSession();

        $a->put('cart', ['apple']);
        $this->commit($a);

        $b->invalidate();
        $this->commit($b);

        $this->assertNull($this->session($this->request('/', [self::COOKIE => $cookie]))->get('cart'));
        $this->assertSame([], $this->storedFiles());
    }

    public function test_set_id_removes_the_session_it_replaces(): void
    {
        $session = $this->session();
        $session->put('a', 1);
        $old = $this->sessionCookie($this->commit($session))->getValue();

        $next = $this->session($this->request('/', [self::COOKIE => $old]));
        $new = bin2hex(random_bytes(20));
        $next->setId($new);
        $next->put('b', 2);
        $this->commit($next);

        $this->assertNull($this->store()->read($old));
        $this->assertNotNull($this->store()->read($new));
    }

    public function test_the_sweep_runs_after_the_response_not_while_saving(): void
    {
        $this->config['lottery'] = [1, 1];
        $store = new class($this->path, 7200) extends FileStore {
            public int $sweeps = 0;

            public function sweep(int $maxLifetime): int
            {
                $this->sweeps++;

                return 0;
            }
        };

        $session = $this->session(null, $store);
        $session->put('a', 1);
        $this->commit($session);

        $this->assertSame(0, $store->sweeps);

        $session->terminate();
        $session->terminate();

        $this->assertSame(1, $store->sweeps);
    }
}
