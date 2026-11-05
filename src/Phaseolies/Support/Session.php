<?php

namespace Phaseolies\Support;

use Phaseolies\Http\Request;
use Phaseolies\Http\Response;
use Phaseolies\Http\Response\Cookie;
use Phaseolies\Session\Contracts\CommittableSession;
use Phaseolies\Session\Contracts\LockableSessionStore;
use Phaseolies\Session\Contracts\SessionInterface;
use Phaseolies\Session\Contracts\SessionStoreInterface;
use Phaseolies\Session\RotationPointer;
use Phaseolies\Session\SessionManager;
use Phaseolies\Session\Stores\FileStore;
use Phaseolies\Utilities\PathMatcher;

class Session implements SessionInterface, CommittableSession
{
    /**
     * Keys maintained by the session itself that do not make it worth storing.
     *
     * @var array<string, true>
     */
    protected const INTERNAL_KEYS = ['last_regenerated' => true];

    /**
     * Keys of the pointer an automatically rotated id leaves behind.
     *
     */
    protected const ROTATED_TO = RotationPointer::TO;

    protected const ROTATED_UNTIL = RotationPointer::UNTIL;

    /**
     * Seconds the old id of an automatic rotation keeps leading to the new one.
     */
    protected const ROTATION_GRACE = 60;

    /**
     * The most pointers followed before giving up, which only matters for a loop.
     */
    protected const MAX_HOPS = 3;

    /**
     * How a locked save ended.
     */
    protected const SAVED = 'saved';

    protected const MOVED = 'moved';

    protected const GONE = 'gone';

    protected const EMPTIED = 'emptied';

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
     * Ids whose stored data must be removed when the session is committed.
     *
     * @var string[]
     */
    protected array $previousIds = [];

    /**
     * Whether this request ended the session (destroy, flush, invalidate).
     */
    protected bool $ending = false;

    /**
     * The serialized data as it was loaded, to detect changes.
     */
    protected ?string $snapshot = null;

    /**
     * The data as it was loaded, to find what this request changed.
     */
    protected array $original = [];

    /**
     * Whether the stored data must be replaced instead of merged on commit.
     */
    protected bool $replace = false;

    /**
     * Whether the session was saved by this request.
     */
    protected bool $committed = false;

    /**
     * Whether the cookie must be expired when the session is committed.
     */
    protected bool $forgetCookie = false;

    /**
     * Whether the stored session was due for an automatic rotation when loaded.
     */
    protected bool $rotationDue = false;

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

        $path = (string) parse_url($this->request->getPath(), PHP_URL_PATH);

        foreach ($patterns as $pattern) {
            if (PathMatcher::matches((string) $pattern, $path)) {
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

        $blank = $this->isBlank();

        if ($blank && ($this->ending || $this->storedId === null || !$store instanceof LockableSessionStore)) {
            $this->commitBlank($store, $config, $response);

            return;
        }

        $this->id ??= self::newId();
        $this->data['last_regenerated'] ??= time();

        $serialized = serialize($this->data);
        $changed = $blank || $this->storedId !== $this->id || $serialized !== $this->snapshot || $this->rotationDue;

        if ($changed || $store->isClientSide()) {
            if ($store instanceof LockableSessionStore && $this->storedId !== null) {
                // Another request may have saved, rotated or ended this session
                // since it was loaded. Apply only what this request changed on
                // top of what is stored now.
                [$value, $state] = $this->saveLocked($store);

                if ($state === self::EMPTIED) {
                    $this->commitBlank($store, $config, $response);

                    return;
                }

                if ($value === null) {
                    // Logged out, destroyed or expired by another request while
                    // this one was running. Writing it back would undo that, and
                    // sending its cookie would replace the one that request issued.
                    $this->abandon();

                    return;
                }
            } else {
                if ($this->rotationDue) {
                    // No lock to coordinate with, so rotate on the spot.
                    $this->retire($this->storedId);
                    $this->id = self::newId();
                    $this->data['last_regenerated'] = time();
                }

                $value = $store->write($this->id, $this->data);
            }

            $serialized = serialize($this->data);
        } elseif (!$this->stillCurrent($store)) {
            // Unchanged, but another request rotated or ended the session while
            // this one ran. Sending its id again would overwrite the cookie that
            // request issued with one that no longer works.
            $this->abandon();

            return;
        } else {
            $store->touch($this->id);
            $value = $this->id;
        }

        foreach ($this->previousIds as $old) {
            if ($old !== $this->id) {
                $this->destroyLineage($store, $old);
            }
        }

        $this->storedId = $this->id;
        $this->previousIds = [];
        $this->ending = false;
        $this->snapshot = $serialized;
        $this->original = $this->data;
        $this->replace = false;
        $this->rotationDue = false;
        $this->committed = true;

        $this->queueCookie($response, $this->makeCookie($value, $config));
    }

    /**
     * Apply only what ends the session, for a request that is failing.
     *
     * @param Response|null $response
     * @return void
     */
    public function commitEnd(?Response $response = null): void
    {
        if (!$this->loaded || !$this->ending || $this->isStateless()) {
            return;
        }

        $this->commitBlank($this->store(), $this->config(), $response);
    }

    /**
     * Commit a session that holds nothing: end whatever is stored for it.
     *
     * @param SessionStoreInterface $store
     * @param array $config
     * @param Response|null $response
     * @return void
     */
    protected function commitBlank(SessionStoreInterface $store, array $config, ?Response $response): void
    {
        $ids = array_values(array_unique(array_filter([...$this->previousIds, $this->storedId])));

        foreach ($ids as $id) {
            $this->destroyLineage($store, $id);
        }

        // Only a session this request actually ended has its cookie expired. A
        // cookie that merely names an unknown session is left alone: the browser
        // may already hold a newer one from a request that finished first, and
        // expiring it would sign the user out.
        if ($ids !== [] || $this->forgetCookie) {
            $this->queueCookie($response, $this->expiredCookie($config));
        }

        $this->storedId = null;
        $this->previousIds = [];
        $this->ending = $this->rotationDue = false;
    }

    /**
     * Whether the stored session is still the one this request loaded.
     *
     * @param SessionStoreInterface $store
     * @return bool
     */
    protected function stillCurrent(SessionStoreInterface $store): bool
    {
        if (!$store instanceof LockableSessionStore || $this->storedId === null) {
            return true;
        }

        $record = $store->read($this->storedId);

        return $record !== null && !RotationPointer::isPointer($record['data']);
    }

    /**
     * Run the housekeeping that must not delay the response.
     *
     * @return void
     */
    public function terminate(): void
    {
        if ($this->committed) {
            $this->committed = false;

            $this->sweep($this->store(), $this->config());
        }
    }

    /**
     * Mark an id whose stored data must be removed once the session is saved.
     *
     * @param string|null $id
     * @return void
     */
    protected function retire(?string $id): void
    {
        if ($id !== null && !in_array($id, $this->previousIds, true)) {
            $this->previousIds[] = $id;
        }
    }

    /**
     * Forget that this session is stored, without touching the store or the cookie.
     *
     * @return void
     */
    protected function abandon(): void
    {
        $this->storedId = $this->snapshot = null;
        $this->previousIds = [];
        $this->original = [];
        $this->replace = $this->committed = $this->rotationDue = $this->ending = false;
    }

    /**
     * Save the session while it is locked, following it if it was rotated.
     *
     * @param LockableSessionStore $store
     * @return array{0: string|null, 1: string}
     */
    protected function saveLocked(LockableSessionStore $store): array
    {
        for ($hop = 0; $hop <= self::MAX_HOPS; $hop++) {
            // Only one lock is held at a time: when the session turns out to
            // have moved, the lock is released and the next one is taken.
            $result = $store->synchronized($this->storedId, fn () => $this->saveUnderLock($store));

            if (!is_array($result)) {
                return [null, self::GONE];
            }

            if ($result[1] !== self::MOVED) {
                return $result;
            }
        }

        return [null, self::GONE];
    }

    /**
     * Save the session. Must run while the session is locked.
     *
     * @param LockableSessionStore $store
     * @return array{0: string|null, 1: string}
     */
    protected function saveUnderLock(LockableSessionStore $store): array
    {
        $record = $store->read($this->storedId);

        if ($record !== null && RotationPointer::isPointer($record['data'])) {
            $target = $this->pointerTarget($record['data']);

            if ($target !== null) {
                // Another request rotated the id. Carry on with the new one, so
                // this request's changes land in the one live session.
                $this->follow($target);

                return [null, self::MOVED];
            }

            // A pointer past its time is a session that is gone.
            $record = null;
        }

        if ($record === null) {
            // The session this request loaded no longer exists: another request
            // logged out, destroyed or expired it. Writing it back would undo that.
            // Only data this request built from scratch (flush, invalidate) may
            // create a session again, because none of it comes from the old one.
            return $this->replace ? [$this->persist($store, false), self::SAVED] : [null, self::GONE];
        }

        if (!$this->replace && serialize($record['data']) !== $this->snapshot) {
            $this->data = $this->mergeChanges($record['data'], $this->original, $this->data);
        }

        if ($this->isBlank()) {
            $store->destroy($this->storedId);

            return [null, self::EMPTIED];
        }

        return [$this->persist($store, true), self::SAVED];
    }

    /**
     * Write the session, rotating its id first when that is due.
     *
     * @param LockableSessionStore $store
     * @param bool $live
     * @return string
     */
    protected function persist(LockableSessionStore $store, bool $live): string
    {
        if (!$live || !$this->rotationDue || $this->replace || $this->id !== $this->storedId || !$this->isDue($this->data)) {
            return $store->write($this->id, $this->data);
        }

        // Rotate under the lock: write the session under its new id, then turn
        // the old one into a pointer for a short time. Requests already running
        // on the old id follow it, so none of them splits the session or loses
        // its changes, and none is signed out.
        $old = $this->storedId;
        $new = self::newId();

        $this->data['last_regenerated'] = time();

        $store->write($new, $this->data);
        $store->write($old, RotationPointer::make($new, time() + self::ROTATION_GRACE));

        return $this->id = $new;
    }

    /**
     * Move this request on to the session an automatic rotation created.
     *
     * @param string $to
     * @return void
     */
    protected function follow(string $to): void
    {
        $from = $this->storedId;

        $this->id = $this->id === $from ? $to : $this->id;
        $this->previousIds = array_map(fn (string $id) => $id === $from ? $to : $id, $this->previousIds);
        $this->storedId = $to;
    }

    /**
     * The id an old id leads to, while it is still allowed to.
     *
     * @param array $data
     * @param bool $honourExpiry
     * @return string|null
     */
    protected function pointerTarget(array $data, bool $honourExpiry = true): ?string
    {
        return RotationPointer::target($data, $honourExpiry);
    }

    /**
     * Read a session by id, following the pointer of an automatically rotated one.
     *
     * @param SessionStoreInterface $store
     * @param string $id
     * @return array{id: string, data: array}|null
     */
    protected function readFollowing(SessionStoreInterface $store, string $id): ?array
    {
        for ($hop = 0; $hop <= self::MAX_HOPS; $hop++) {
            $record = $store->read($id);

            if ($record === null || !RotationPointer::isPointer($record['data'])) {
                return $record;
            }

            $target = $this->pointerTarget($record['data']);

            if ($target === null) {
                $store->destroy($id);

                return null;
            }

            $id = $target;
        }

        return null;
    }

    /**
     * End a session and every session an automatic rotation made from it.
     *
     * @param SessionStoreInterface $store
     * @param string $id
     * @return void
     */
    protected function destroyLineage(SessionStoreInterface $store, string $id): void
    {
        for ($hop = 0; $hop <= self::MAX_HOPS; $hop++) {
            if (!$store instanceof LockableSessionStore) {
                $store->destroy($id);

                return;
            }

            // Read and destroy under one lock, so a rotation cannot slip in
            // between and leave a live successor behind.
            $next = $store->synchronized($id, function () use ($store, $id): ?string {
                $record = $store->read($id);

                $store->destroy($id);

                return $record === null ? null : $this->pointerTarget($record['data'], false);
            });

            if (!is_string($next)) {
                return;
            }

            $id = $next;
        }
    }

    /**
     * Whether the id is due for an automatic rotation.
     *
     * @param array $data
     * @return bool
     */
    protected function isDue(array $data): bool
    {
        return !isset($data['last_regenerated'])
            || (time() - (int) $data['last_regenerated']) > ($this->config()['lifetime'] * 60);
    }

    /**
     * Copy what changed between $original and $current onto $stored.
     *
     * @param array $stored
     * @param array $original
     * @param array $current
     * @return array
     */
    protected function mergeChanges(array $stored, array $original, array $current): array
    {
        foreach ($original as $key => $value) {
            if (!array_key_exists($key, $current)) {
                unset($stored[$key]);
            }
        }

        foreach ($current as $key => $value) {
            $known = array_key_exists($key, $original);

            if ($known && $original[$key] === $value) {
                continue;
            }

            $base = $known ? $original[$key] : [];

            if (is_array($value) && is_array($base) && isset($stored[$key]) && is_array($stored[$key])) {
                $stored[$key] = $this->mergeValues($stored[$key], $base, $value);

                continue;
            }

            $stored[$key] = $value;
        }

        return $stored;
    }

    /**
     * Merge two versions of an array value.
     *
     * @param array $stored
     * @param array $original
     * @param array $current
     * @return array
     */
    protected function mergeValues(array $stored, array $original, array $current): array
    {
        if (array_is_list($stored) && array_is_list($original) && array_is_list($current)) {
            return $this->mergeLists($stored, $original, $current);
        }

        return $this->mergeChanges($stored, $original, $current);
    }

    /**
     * Merge two versions of a list by what each request added and removed.
     *
     * @param array $stored
     * @param array $original
     * @param array $current
     * @return array
     */
    protected function mergeLists(array $stored, array $original, array $current): array
    {
        if ($this->sameList($stored, $original)) {
            // Nobody else touched the list, so this request's version stands.
            return $current;
        }

        $removed = $this->missingFrom($original, $current);
        $added = $this->missingFrom($current, $original);

        foreach ($removed as $item) {
            foreach ($stored as $index => $candidate) {
                if ($this->sameItem($candidate, $item)) {
                    unset($stored[$index]);

                    break;
                }
            }
        }

        return array_values(array_merge($stored, $added));
    }

    /**
     * The items of $items that have no counterpart in $other, counting duplicates.
     *
     * @param array $items
     * @param array $other
     * @return array
     */
    protected function missingFrom(array $items, array $other): array
    {
        $missing = [];

        foreach ($items as $item) {
            foreach ($other as $index => $candidate) {
                if ($this->sameItem($item, $candidate)) {
                    unset($other[$index]);

                    continue 2;
                }
            }

            $missing[] = $item;
        }

        return $missing;
    }

    /**
     * @param array $first
     * @param array $second
     * @return bool
     */
    protected function sameList(array $first, array $second): bool
    {
        return serialize($first) === serialize($second);
    }

    /**
     * Whether two list items are identical.
     *
     * @param mixed $first
     * @param mixed $second
     * @return bool
     */
    protected function sameItem(mixed $first, mixed $second): bool
    {
        if (is_scalar($first) || $first === null || is_scalar($second) || $second === null) {
            return $first === $second;
        }

        return serialize($first) === serialize($second);
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
            $record = $value === null ? null : $this->readFollowing($this->store(), $value);

            if ($record !== null) {
                $this->id = $this->storedId = $record['id'];
                $this->data = $this->original = $record['data'];
                $this->snapshot = serialize($this->data);

                // Rotated when saved, under the lock, not here: several requests
                // loading the same session must not each pick their own new id.
                $this->rotationDue = $this->isDue($this->data);
            }
        }

        $this->peek = array_replace($this->data, $this->peek);
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
        $cookie = $this->makeCookie('', $config);

        // Same attributes as the cookie being removed, or browsers ignore it:
        // Chrome rejects SameSite=None without Secure, deletions included.
        return new Cookie(
            $cookie->getName(),
            '',
            1,
            $cookie->getPath(),
            $cookie->getDomain(),
            $cookie->isSecure(),
            $cookie->isHttpOnly(),
            false,
            $cookie->getSameSite()
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
        $this->replace = true;
        $this->ending = true;
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

        if ($deleteOldSession) {
            $this->retire($this->storedId);
        }

        $this->id = self::newId();
        $this->data['last_regenerated'] = time();
        $this->rotationDue = false;
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
     * The id the session is stored under right now, if it is stored.
     *
     * @return string|null
     */
    public function storedId(): ?string
    {
        $this->load();

        return $this->storedId;
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

        // Switching ids must not leave the old session valid.
        if ($this->storedId !== null && $this->storedId !== $id) {
            $this->retire($this->storedId);
        }

        if (!$store->isClientSide() && !$this->isStateless() && ($record = $this->readFollowing($store, $id)) !== null) {
            $this->data = $this->original = $record['data'];
            $this->storedId = $record['id'];
            $this->snapshot = serialize($this->data);

            $id = $record['id'];
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

        $this->retire($this->storedId);
        $this->storedId = $this->id = $this->snapshot = null;
        $this->original = [];
        $this->forgetCookie = true;
        $this->ending = true;
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
