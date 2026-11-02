<?php

namespace Phaseolies\Support;

use Phaseolies\Http\Request;
use Phaseolies\Http\Response;
use Phaseolies\Http\Response\Cookie;
use Phaseolies\Session\Contracts\CommittableSession;
use Phaseolies\Session\Contracts\SessionInterface;
use Phaseolies\Session\Contracts\SessionStoreInterface;
use Phaseolies\Session\SessionManager;
use Phaseolies\Session\Stores\FileStore;

class Session implements SessionInterface, CommittableSession
{
    /**
     * Keys maintained by the session itself that do not make it worth storing.
     *
     * @var array<string, true>
     */
    protected const INTERNAL_KEYS = ['last_regenerated' => true];

    /**
     * The session data.
     *
     * @var array
     */
    protected $data = [];

    /**
     * The session peek data.
     *
     * @var array
     */
    protected $peek = [];

    /**
     * Flash data key.
     *
     * @var string
     */
    protected $flashKey = '_flash';

    /**
     * Whether the stored session was looked up yet.
     */
    protected bool $loaded = false;

    /**
     * Whether persistence is switched off for this request.
     */
    protected bool $disabled = false;

    /**
     * The id of the session, generated on demand.
     */
    protected ?string $id = null;

    /**
     * The id the session is currently stored under, if it is stored.
     */
    protected ?string $storedId = null;

    /**
     * An id whose stored data must be removed when the session is committed.
     */
    protected ?string $previousId = null;

    /**
     * The serialized data as it was loaded, to detect changes.
     */
    protected ?string $snapshot = null;

    /**
     * Whether the cookie must be expired when the session is committed.
     */
    protected bool $forgetCookie = false;

    /**
     * The request the session belongs to.
     */
    protected ?Request $request = null;

    /**
     * Constructor.
     *
     * @param SessionStoreInterface|null $store
     * @param array|null $config
     */
    public function __construct(
        protected ?SessionStoreInterface $store = null,
        protected ?array $config = null
    ) {
        if ($this->config !== null) {
            $this->config = SessionManager::normalize($this->config);
        }
    }

    /**
     * Bind the session to the request it belongs to.
     *
     * @param Request $request
     * @return void
     */
    public function attach(Request $request): void
    {
        $this->request = $request;
    }

    /**
     * Turn persistence off for the rest of the request.
     *
     * @return void
     */
    public function disable(): void
    {
        $this->disabled = true;
    }

    /**
     * Whether the session was loaded during this request.
     *
     * @return bool
     */
    public function isLoaded(): bool
    {
        return $this->loaded;
    }

    /**
     * Whether this request is not allowed to persist a session.
     *
     * @return bool
     */
    public function isStateless(): bool
    {
        if ($this->disabled) {
            return true;
        }

        $patterns = (array) ($this->config()['stateless'] ?? []);

        if ($patterns === [] || $this->request === null) {
            return false;
        }

        $path = trim((string) parse_url($this->request->getPath(), PHP_URL_PATH), '/');

        foreach ($patterns as $pattern) {
            $regex = str_replace('\\*', '.*', preg_quote(trim((string) $pattern, '/'), '#'));

            if (preg_match('#^' . $regex . '$#i', $path) === 1) {
                return $this->disabled = true;
            }
        }

        return false;
    }

    /**
     * Persist the session and queue its cookie.
     *
     * @param Response|null $response
     * @return void
     */
    public function commit(?Response $response = null): void
    {
        if (!$this->loaded || $this->isStateless()) {
            return;
        }

        $store = $this->store();
        $config = $this->config();

        if ($this->isBlank()) {
            $stale = $this->previousId ?? $this->storedId;

            if ($stale !== null) {
                $store->destroy($stale);
            }

            if ($stale !== null || $this->forgetCookie || $this->incomingCookie() !== null) {
                $this->queueCookie($response, $this->expiredCookie($config));
            }

            $this->storedId = $this->previousId = null;

            return;
        }

        $this->id ??= self::newId();
        $this->data['last_regenerated'] ??= time();

        $changed = $this->storedId !== $this->id || serialize($this->data) !== $this->snapshot;

        if ($changed || $store->isClientSide()) {
            $value = $store->write($this->id, $this->data);
        } else {
            $store->touch($this->id);
            $value = $this->id;
        }

        if ($this->previousId !== null && $this->previousId !== $this->id) {
            $store->destroy($this->previousId);
        }

        $this->storedId = $this->id;
        $this->previousId = null;
        $this->snapshot = serialize($this->data);

        $this->queueCookie($response, $this->makeCookie($value, $config));

        $this->sweep($store, $config);
    }

    /**
     * Look up the stored session the first time the session is used.
     *
     * @return void
     */
    protected function load(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->loaded = true;

        if (!$this->isStateless()) {
            $value = $this->incomingCookie();
            $record = $value === null ? null : $this->store()->read($value);

            if ($record !== null) {
                $this->id = $this->storedId = $record['id'];
                $this->data = $record['data'];
                $this->snapshot = serialize($this->data);

                if ($this->shouldRotate()) {
                    $this->regenerate(true);
                }
            }
        }

        $this->peek = array_replace($this->data, $this->peek);
    }

    /**
     * Whether the session id is due for rotation.
     *
     * @return bool
     */
    protected function shouldRotate(): bool
    {
        return !isset($this->data['last_regenerated'])
            || (time() - (int) $this->data['last_regenerated']) > ($this->config()['lifetime'] * 60);
    }

    /**
     * Whether the session holds nothing worth storing.
     *
     * @return bool
     */
    protected function isBlank(): bool
    {
        return array_diff_key($this->data, self::INTERNAL_KEYS) === [];
    }

    /**
     * The raw value of the session cookie sent by the client.
     *
     * @return string|null
     */
    protected function incomingCookie(): ?string
    {
        $name = $this->config()['cookie'];

        $value = $this->request !== null
            ? $this->request->cookies->get($name)
            : ($_COOKIE[$name] ?? null);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Build the session cookie.
     *
     * @param string $value
     * @param array $config
     * @return Cookie
     */
    protected function makeCookie(string $value, array $config): Cookie
    {
        $sameSite = strtolower((string) ($config['same_site'] ?: 'lax'));
        $secure = $config['secure'] ?? ($this->request?->isSecure() ?? false);

        return new Cookie(
            $config['cookie'],
            $value,
            $config['expire_on_close'] ? 0 : time() + $config['lifetime'] * 60,
            $config['path'],
            $config['domain'],
            // Browsers reject SameSite=None without Secure.
            $sameSite === 'none' ? true : (bool) $secure,
            (bool) $config['http_only'],
            false,
            $sameSite
        );
    }

    /**
     * Build a cookie that makes the browser drop the session cookie.
     *
     * @param array $config
     * @return Cookie
     */
    protected function expiredCookie(array $config): Cookie
    {
        return new Cookie(
            $config['cookie'],
            '',
            1,
            $config['path'],
            $config['domain'],
            (bool) ($config['secure'] ?? false),
            (bool) $config['http_only'],
            false,
            strtolower((string) ($config['same_site'] ?: 'lax'))
        );
    }

    /**
     * Attach the cookie to the response, or send it as a header without one.
     *
     * @param Response|null $response
     * @param Cookie $cookie
     * @return void
     */
    protected function queueCookie(?Response $response, Cookie $cookie): void
    {
        if ($response !== null) {
            $response->headers->setCookie($cookie);
        } elseif (!headers_sent()) {
            header('Set-Cookie: ' . $cookie, false);
        }
    }

    /**
     * Occasionally remove expired sessions.
     *
     * @param SessionStoreInterface $store
     * @param array $config
     * @return void
     */
    protected function sweep(SessionStoreInterface $store, array $config): void
    {
        [$chances, $total] = array_pad(array_map('intval', (array) $config['lottery']), 2, 100);

        if ($chances > 0 && $total > 0 && random_int(1, $total) <= $chances) {
            $store->sweep($config['lifetime'] * 60);
        }
    }

    /**
     * Generate a session id.
     *
     * @return string
     */
    protected static function newId(): string
    {
        return bin2hex(random_bytes(20));
    }

    /**
     * @return SessionStoreInterface
     */
    protected function store(): SessionStoreInterface
    {
        return $this->store ??= SessionManager::store();
    }

    /**
     * @return array
     */
    protected function config(): array
    {
        return $this->config ??= SessionManager::config();
    }

    /**
     * Get a session value by key.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        $this->load();

        return $this->data[$key] ?? $default;
    }

    /**
     * Get a session value by key and remove it.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function pull(string $key, $default = null)
    {
        $value = $this->get($key, $default);

        $this->forget($key);

        return $value;
    }

    /**
     * Get a peek value by key and remove it from the session.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getPeek(string $key, $default = null)
    {
        $this->load();

        $value = $this->peek[$key] ?? $default;

        $this->forget($key);

        return $value;
    }

    /**
     * Put a key-value pair into the peek data.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function putPeek(string $key, $value): void
    {
        $this->peek[$key] = $value;
    }

    /**
     * Remove all session peek data.
     *
     * @return void
     */
    public function flushPeek(): void
    {
        $this->peek = [];
    }

    /**
     * Put a key-value pair into the session.
     *
     * @param string|array $key
     * @param mixed $value
     * @return void
     */
    public function put(string|array $key, $value = null): void
    {
        $this->load();

        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->data[$k] = $v;
            }
        } else {
            $this->data[$key] = $value;
        }
    }

    /**
     * Check if a session key exists.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        $this->load();

        return isset($this->data[$key]);
    }

    /**
     * Remove a session key.
     *
     * @param string $key
     * @return void
     */
    public function forget(string $key): void
    {
        $this->load();

        unset($this->data[$key]);
    }

    /**
     * Remove all session data.
     *
     * @return void
     */
    public function flush(): void
    {
        $this->load();

        $this->data = [];
    }

    /**
     * Regenerate the session ID.
     *
     * @param bool $deleteOldSession
     * @return void
     */
    public function regenerate(bool $deleteOldSession = true): void
    {
        $this->load();

        if ($deleteOldSession && $this->storedId !== null) {
            $this->previousId ??= $this->storedId;
        }

        $this->id = self::newId();
        $this->data['last_regenerated'] = time();
    }

    /**
     * Get all session data.
     *
     * @return array
     */
    public function all(): array
    {
        $this->load();

        return $this->data;
    }

    /**
     * Get the session ID.
     *
     * @return string
     */
    public function getId(): string
    {
        $this->load();

        return $this->id ??= self::newId();
    }

    /**
     * Set the session ID.
     *
     * @param string $id
     * @return void
     */
    public function setId(string $id): void
    {
        $this->load();

        if (!FileStore::isValidId($id)) {
            return;
        }

        $store = $this->store();

        if (!$store->isClientSide() && !$this->isStateless() && ($record = $store->read($id)) !== null) {
            $this->data = $record['data'];
            $this->storedId = $record['id'];
            $this->snapshot = serialize($this->data);
        }

        $this->id = $id;
    }

    /**
     * Destroy the session.
     *
     * @return void
     */
    public function destroy(): void
    {
        $this->load();

        $this->previousId ??= $this->storedId;
        $this->storedId = $this->id = $this->snapshot = null;
        $this->forgetCookie = true;
        $this->data = [];
        $this->peek = [];
    }

    /**
     * Get the CSRF token, creating it the first time it is needed.
     *
     * @return string|null
     */
    public function token(): ?string
    {
        $this->load();

        return $this->data['_token'] ??= $this->generateToken();
    }

    /**
     * Set a flash message.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function flash(string $key, $value): void
    {
        $this->put($key, $value);
    }

    /**
     * Keep flash data for the next request.
     *
     * @param string|array $keys
     * @return void
     */
    public function reflash($keys): void
    {
        if (is_string($keys)) {
            $keys = [$keys];
        }

        foreach ($keys as $key) {
            if ($this->has($this->flashKey . '.' . $key)) {
                $this->flash($key, $this->get($this->flashKey . '.' . $key));
            }
        }
    }

    /**
     * Invalidate the session (regenerate ID and clear all data).
     *
     * @return void
     */
    public function invalidate(): void
    {
        $this->regenerate(true);

        $this->flush();
    }

    /**
     * Regenerate the CSRF token.
     *
     * @return void
     */
    public function regenerateToken(): void
    {
        $this->put('_token', $this->generateToken());
    }

    /**
     * Generate a new CSRF token.
     *
     * @return string
     */
    protected function generateToken(): string
    {
        return bin2hex(random_bytes(16));
    }
}
