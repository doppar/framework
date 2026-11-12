<?php

namespace Phaseolies\Auth\Security;

use Phaseolies\Support\Facades\Hash;
use Phaseolies\Support\Facades\Crypt;
use Phaseolies\Auth\Authable;
use Phaseolies\Session\Contracts\CommittableSession;
use Phaseolies\Session\Contracts\SessionStoreInterface;
use Phaseolies\Session\Contracts\UserSessionStore;
use Phaseolies\Session\SessionManager;

class Authenticate
{
    use InteractsWithTwoFactorAuth, InteractsWithRememberCookie;

    /**
     * @var array
     */
    private $data = [];

    /**
     * The name of this actor instance (e.g. "web", "admin").
     *
     * @var string
     */
    protected string $actorName;

    /**
     * The actor configuration array from config/auth.php.
     *
     * @var array
     */
    protected array $config;

    /**
     * The current stateless user (for onceUsingId).
     *
     * @var Authable|null
     */
    private $statelessUser = null;

    /**
     * Per-instance resolved user cache
     *
     * @var Authable|null
     */
    private ?Authable $resolvedUser = null;


    /**
     * Create a new actor instance.
     *
     * @param string $actorName
     * @param array  $config
     */
    public function __construct(string $actorName, array $config)
    {
        $this->actorName = $actorName;
        $this->config    = $config;
    }

    /**
     * Set a property on the actor.
     *
     * @param string $name
     * @param mixed $value
     */
    public function __set($name, $value)
    {
        $this->data[$name] = $value;
    }

    /**
     * Get a property from the actor.
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->data[$name] ?? null;
    }

    /**
     * Resolve a fresh instance of the configured auth model.
     *
     * @return Authable
     * @throws \InvalidArgumentException
     */
    protected function getModel(): Authable
    {
        $model = app($this->config['model']);

        if (!$model instanceof Authable) {
            throw new \InvalidArgumentException(
                'Auth model must extend ' . Authable::class . ', ' . get_debug_type($model) . ' given'
            );
        }

        return $model;
    }

    /**
     * The session key used to store the authenticated user's ID for this actor.
     *
     * @return string
     */
    protected function getSessionKey(): string
    {
        return $this->config['session_key'];
    }

    /**
     * The session key used to store the "authenticated via remember token" flag.
     *
     * @return string
     */
    protected function getViaRememberKey(): string
    {
        return 'auth_via_remember_' . $this->actorName;
    }

    /**
     * The session key holding the fingerprint of the password the session was signed in with.
     *
     * @return string
     */
    protected function getPasswordFingerprintKey(): string
    {
        return 'password_hash_' . $this->actorName;
    }

    /**
     * The session key holding the fingerprint of a password this device is saving.
     *
     * @return string
     */
    protected function getPendingFingerprintKey(): string
    {
        return $this->getPasswordFingerprintKey() . '_next';
    }

    /**
     * Most password changes of one request whose fingerprints are kept pending.
     */
    protected const MAX_PENDING_FINGERPRINTS = 5;

    /**
     * The session key used to store a pending 2FA user ID for this actor.
     *
     * @return string
     */
    protected function getTwoFactorUserKey(): string
    {
        return '2fa_' . $this->actorName . '_user_id';
    }

    /**
     * The session key used to store the 2FA remember flag for this actor.
     *
     * @return string
     */
    protected function getTwoFactorRememberKey(): string
    {
        return '2fa_' . $this->actorName . '_remember';
    }

    /**
     * Attempt to authenticate a user using credentials.
     *
     * @param array $credentials
     * @param bool $remember
     * @return bool
     * @throws \Exception
     */
    public function try(array $credentials = [], bool $remember = false): bool
    {
        $authModel    = $this->getModel();
        $customAuthKey = $authModel->getAuthKeyName();

        $authKeyValue = $credentials[$customAuthKey] ?? '';
        $password     = $credentials['password'] ?? '';

        $user = $authModel::where($customAuthKey, $authKeyValue)->first();

        if (!$user || !Hash::check($password, $user->getAuthPassword())) {
            return false;
        }

        $this->login($user, $remember);

        return true;
    }

    /**
     * Log in a user instance.
     *
     * @param Authable $user
     * @param bool $remember
     * @return bool
     * @throws \InvalidArgumentException
     */
    public function login(Authable $user, bool $remember = false): bool
    {
        $authModel = $this->getModel();
        $modelClass = $authModel::class;

        if (!$user instanceof $modelClass) {
            throw new \InvalidArgumentException(
                'Argument #1 ($user) must be an instance of ' . $modelClass . ', ' . get_debug_type($user) . ' given'
            );
        }

        if ($this->hasTwoFactorEnabled($user) && !$this->isApiRequest()) {
            session()->put($this->getTwoFactorUserKey(), $user->getAuthIdentifier());
            session()->put($this->getTwoFactorRememberKey(), $remember);

            return true;
        }

        $this->setUser($user);

        if ($remember) {
            $this->setRememberToken($user);
        }

        return true;
    }

    /**
     * Log in a user by their ID.
     *
     * @param int $id
     * @param bool $remember
     * @return Authable|null
     */
    public function loginUsingId(int $id, bool $remember = false): ?Authable
    {
        $authModel = $this->getModel();

        $user = $authModel::find($id);

        if ($user) {
            $this->login($user, $remember);
        }

        return $user;
    }

    /**
     * Log in a user by their ID for a single request (no session/cookie).
     *
     * @param int $id
     * @return Authable|null
     */
    public function onceUsingId(int $id): ?Authable
    {
        $authModel = $this->getModel();

        $user = $authModel::find($id);

        if ($user) {
            $this->statelessUser = $user;

            return $user;
        }

        return null;
    }

    /**
     * Get the currently authenticated user.
     *
     * @return Authable|null
     */
    public function user(): ?Authable
    {
        if ($this->resolvedUser !== null) {
            return $this->resolvedUser;
        }

        if ($this->statelessUser !== null) {
            return $this->statelessUser;
        }

        $authModel  = $this->getModel();
        $sessionKey = $this->getSessionKey();

        if ($this->isApiRequest()) {
            $hasAuthApi = false;
            foreach (app('route')->getCurrentMiddlewareNames() ?? [] as $middleware) {
                if (str_starts_with($middleware, 'auth-api')) {
                    $hasAuthApi = true;
                    break;
                }
            }

            if ($hasAuthApi) {
                return $this->resolvedUser = $hasAuthApi
                    ? app(\Doppar\Flarion\ApiAuthenticate::class)->user()
                    : null;
            }
        }

        if (session()->has($sessionKey)) {
            $user = $authModel::find(session($sessionKey));

            if ($user) {
                return $this->resolvedUser = $user;
            }
        }

        if (cookie()->has($this->getRememberCookieName())) {
            $remembered = $this->readRememberCookie();
            $user = $remembered === null ? null : $authModel::find($remembered[0]);

            if ($user && $this->rememberTokenMatches($user, $remembered[1])) {
                if ($this->hasTwoFactorEnabled($user)) {
                    session()->put($this->getTwoFactorUserKey(), $user->getAuthIdentifier());
                    session()->put($this->getTwoFactorRememberKey(), true);
                }

                $this->setUser($user);

                return $this->resolvedUser = $user;
            }

            $this->expireRememberCookie();
        }

        return null;
    }

    /**
     * Read this device's remember cookie.
     *
     * @return array{0: string, 1: string}|null
     */
    protected function readRememberCookie(): ?array
    {
        $value = cookie()->get($this->getRememberCookieName());

        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            $plain = Crypt::decrypt($value);
        } catch (\Exception) {
            return null;
        }

        $segments = is_string($plain) ? explode('|', $plain) : [];

        if (count($segments) !== 3) {
            return null;
        }

        [$id, $token, $hash] = $segments;

        return Hash::check($id . $token, $hash) ? [$id, $token] : null;
    }

    /**
     * Whether a remember token is the one the user currently holds.
     *
     * @param Authable $user
     * @param string $token
     * @return bool
     */
    protected function rememberTokenMatches(Authable $user, string $token): bool
    {
        $stored = $user->getRememberToken();

        return is_string($stored) && $stored !== '' && Hash::check($token, $stored);
    }

    /**
     * Whether this device's remember cookie is valid for the user.
     *
     * @param Authable $user
     * @return bool
     */
    protected function hasValidRememberCookie(Authable $user): bool
    {
        $remembered = $this->readRememberCookie();

        return $remembered !== null
            && $remembered[0] === (string) $user->getAuthIdentifier()
            && $this->rememberTokenMatches($user, $remembered[1]);
    }

    /**
     * Check if the user is authenticated.
     *
     * @return bool
     */
    public function check(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Logs the currently authenticated user out of the application.
     *
     * @return void
     */
    public function logout(): void
    {
        $user = $this->user();

        if ($user && $user->getRememberToken()) {
            $user->setRememberToken(null);
            $user->withoutHook();
            $user->save();
        }

        $this->endSession();
    }

    /**
     * Sign this device out, leaving the user's other devices alone.
     *
     * @param bool $expireRememberCookie
     * @return void
     */
    protected function endSession(bool $expireRememberCookie = true): void
    {
        $this->resolvedUser  = null;
        $this->statelessUser = null;

        session()->forget($this->getSessionKey());
        session()->forget($this->getViaRememberKey());
        session()->forget($this->getPasswordFingerprintKey());
        session()->forget($this->getPendingFingerprintKey());

        // Only fully invalidate the session on the default actor so that other
        // actors (e.g. "admin") remain active when only the "web" actor logs out.
        if ($this->actorName === config('auth.default', 'web')) {
            session()->invalidate();
            session()->regenerateToken();
        }

        if ($expireRememberCookie && cookie()->has($this->getRememberCookieName())) {
            $this->expireRememberCookie();
        }
    }

    /**
     * Sign the user out of every other device, keeping this one signed in.
     *
     * @param string $password
     * @return bool
     * @throws \LogicException
     */
    public function logoutOtherDevices(string $password): bool
    {
        $user = $this->user();

        if ($user === null || !Hash::check($password, (string) $user->getAuthPassword())) {
            return false;
        }

        $passwordName = $user->getAuthPasswordName();

        if ($user->{$passwordName} !== $user->getAuthPassword()) {
            throw new \LogicException(sprintf(
                '%s overrides getAuthPassword() but not getAuthPasswordName(), which still names [%s]. Override getAuthPasswordName() to return the password attribute.',
                $user::class,
                $passwordName
            ));
        }

        $hash = Hash::make($password);
        $changes = [$passwordName => $hash];

        $actors = $this->actorsSignedInAs($user);

        $tokenName = $user->getRememberTokenName();

        if (array_key_exists($tokenName, $user->getAttributes())) {
            $remembered = array_values(array_filter(
                $actors,
                static fn (self $actor): bool => $actor->hasValidRememberCookie($user)
            ));

            if ($remembered === []) {
                $user->setRememberToken(null);
            } else {
                $token = $this->newRememberToken($user);

                foreach ($remembered as $actor) {
                    $actor->sendRememberCookie($user, $token);
                }
            }

            if (!in_array($this, $remembered, true) && cookie()->has($this->getRememberCookieName())) {
                $this->expireRememberCookie();
            }

            $changes[$tokenName] = $user->getRememberToken();
        }

        $this->recordPending($user, $actors, $hash);

        $this->persistCredentials($user, $changes);

        $store = $this->sessionStore();
        $id = $user->getAuthIdentifier();

        if ($id !== null && $this->tracksUsersIn($store)) {
            /** @var UserSessionStore $store */
            $store->destroyUserSessions($id, $this->currentSessionIds());
        }

        return true;
    }

    /**
     * Whether this device's session was signed in with the user's current password.
     *
     * @return bool
     */
    public function isSessionCurrent(): bool
    {
        if (!session()->has($this->getSessionKey())) {
            return true;
        }

        $user = $this->user();

        if ($user === null) {
            return true;
        }

        $stored = session($this->getPasswordFingerprintKey());

        if (!is_string($stored)) {
            $this->signOutKeepingSession();

            return $this->user() !== null;
        }

        $fingerprint = $this->passwordFingerprint($user);

        if ($this->acceptFingerprint($fingerprint, $stored, session($this->getPendingFingerprintKey()))) {
            return true;
        }

        if ($this->acceptStoredFingerprint()) {
            return true;
        }

        $this->endSession();

        return false;
    }

    /**
     * Accept a password fingerprint that matches the current or the pending one.
     *
     * @param string $fingerprint
     * @param mixed $current
     * @param mixed $pending
     * @return bool
     */
    protected function acceptFingerprint(string $fingerprint, mixed $current, mixed $pending): bool
    {
        if (is_string($current) && hash_equals($current, $fingerprint)) {
            if (session($this->getPasswordFingerprintKey()) !== $fingerprint) {
                session()->put($this->getPasswordFingerprintKey(), $fingerprint);
            }

            return true;
        }

        foreach (self::fingerprintList($pending) as $candidate) {
            if (hash_equals($candidate, $fingerprint)) {
                session()->put($this->getPasswordFingerprintKey(), $fingerprint);
                session()->forget($this->getPendingFingerprintKey());

                return true;
            }
        }

        return false;
    }

    /**
     * The fingerprints a pending value holds.
     *
     * @param mixed $value
     * @return array<int, string>
     */
    protected static function fingerprintList(mixed $value): array
    {
        if (is_string($value)) {
            return [$value];
        }

        return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }

    /**
     * Check the fingerprints again as this device's session store
     *
     * @return bool
     */
    protected function acceptStoredFingerprint(): bool
    {
        $session = session();

        if (!$session instanceof CommittableSession) {
            return false;
        }

        return (bool) $session->exclusively(function () use ($session): bool {
            $user = $this->getModel()::find(session($this->getSessionKey()));

            if (!$user instanceof Authable) {
                return false;
            }

            $accepted = $this->acceptFingerprint(
                $this->passwordFingerprint($user),
                $session->storedValue($this->getPasswordFingerprintKey()),
                $session->storedValue($this->getPendingFingerprintKey())
            );

            if ($accepted) {
                $this->resolvedUser = $user;
            }

            return $accepted;
        });
    }

    /**
     * Record the user's password in this device's session, after changing it with a query.
     *
     * @return void
     */
    public function refreshSessionFingerprint(): void
    {
        if (!session()->has($this->getSessionKey())) {
            return;
        }

        $user = $this->getModel()::find(session($this->getSessionKey()));

        if (!$user instanceof Authable) {
            return;
        }

        $fingerprint = $this->passwordFingerprint($user);

        if (session($this->getPasswordFingerprintKey()) !== $fingerprint) {
            session()->put($this->getPasswordFingerprintKey(), $fingerprint);
        }

        session()->forget($this->getPendingFingerprintKey());
    }

    /**
     * The devices the user is signed in on, this one included, most recently active first.
     *
     * @return array<int, array{ip_address: string|null, user_agent: string|null, last_activity: int, is_current: bool}>
     */
    public function sessions(): array
    {
        $user = $this->user();
        $store = $this->sessionStore();
        $id = $user?->getAuthIdentifier();

        if ($id === null || !$this->tracksUsersIn($store)) {
            return [];
        }

        $current = $this->currentSessionIds();

        /** @var UserSessionStore $store */
        return array_map(static fn (array $session): array => [
            'ip_address' => $session['ip_address'],
            'user_agent' => $session['user_agent'],
            'last_activity' => $session['last_activity'],
            'is_current' => in_array($session['id'], $current, true),
        ], $store->userSessions($id));
    }

    /**
     * The ids this device's session goes by
     *
     * @return array<int, string>
     */
    protected function currentSessionIds(): array
    {
        $session = session();
        $ids = [$session->getId()];

        if ($session instanceof CommittableSession && ($stored = $session->storedId()) !== null) {
            $ids[] = $stored;
        }

        return array_values(array_unique($ids));
    }

    /**
     * Record a password about to be saved for a user
     *
     * @param Authable $user
     * @param string $hash
     * @return void
     */
    public function recordPendingPassword(Authable $user, string $hash): void
    {
        try {
            $session = session();
        } catch (\Throwable) {
            return;
        }

        if (!$session instanceof CommittableSession) {
            return;
        }

        $actors = $this->actorsSignedInAs($user, false);

        if ($actors !== []) {
            $this->recordPending($user, $actors, $hash);
        }
    }

    /**
     * Record a password hash as pending for every actor
     *
     * @param Authable $user
     * @param array<int, self> $actors
     * @param string $hash
     * @return void
     */
    protected function recordPending(Authable $user, array $actors, string $hash): void
    {
        $session = session();
        $id = (string) $user->getAuthIdentifier();
        $fingerprint = $this->fingerprintOf($hash);

        $record = function () use ($actors, $id, $fingerprint): void {
            foreach ($actors as $actor) {
                // An actor signed in only by its remember cookie has no
                // fingerprint here; it gets one when the cookie signs it in.
                if ((string) session($actor->getSessionKey()) === $id) {
                    $actor->addPendingFingerprint($fingerprint);
                }
            }
        };

        $session instanceof CommittableSession ? $session->exclusively($record) : $record();
    }

    /**
     * Add a fingerprint to this actor's pending ones, in the stored session too.
     *
     * @param string $fingerprint
     * @return void
     */
    protected function addPendingFingerprint(string $fingerprint): void
    {
        $session = session();
        $key = $this->getPendingFingerprintKey();

        $pending = self::fingerprintList(session($key));

        if ($session instanceof CommittableSession) {
            $pending = array_merge(self::fingerprintList($session->storedValue($key)), $pending);
        }

        if (in_array($fingerprint, $pending, true)) {
            return;
        }

        $pending = array_slice(array_values(array_unique([...$pending, $fingerprint])), -self::MAX_PENDING_FINGERPRINTS);

        $session instanceof CommittableSession ? $session->putThrough($key, $pending) : $session->put($key, $pending);
    }

    /**
     * The actors this device is signed in to as the user
     *
     * @param Authable $user
     * @param bool $includeThis
     * @return array<int, self>
     */
    protected function actorsSignedInAs(Authable $user, bool $includeThis = true): array
    {
        $actors = $includeThis ? [$this] : [];
        $id = (string) $user->getAuthIdentifier();

        foreach (array_keys((array) config('auth.actors', [])) as $name) {
            $actor = $name === $this->actorName ? $this : app('auth')->actor($name);

            if (!$actor instanceof self || in_array($actor, $actors, true)) {
                continue;
            }

            if ((string) session($actor->getSessionKey()) !== $id && !$actor->hasValidRememberCookie($user)) {
                continue;
            }

            if ($actor->getModel()->getTable() === $user->getTable()) {
                $actors[] = $actor;
            }
        }

        return $actors;
    }

    /**
     * Sign this actor out without ending the session, keeping its other data.
     *
     * @return void
     */
    protected function signOutKeepingSession(): void
    {
        $this->resolvedUser = null;

        session()->forget($this->getSessionKey());
        session()->forget($this->getViaRememberKey());
        session()->forget($this->getPasswordFingerprintKey());
        session()->forget($this->getPendingFingerprintKey());
        session()->regenerate();
    }

    /**
     * Write credential columns straight to the user's row.
     *
     * @param Authable $user
     * @param array<string, mixed> $changes
     * @return void
     */
    protected function persistCredentials(Authable $user, array $changes): void
    {
        $user->newQuery()
            ->where($user->getKeyName(), $user->getAuthIdentifier())
            ->update($changes);

        foreach ($changes as $attribute => $value) {
            $user->{$attribute} = $value;
        }

        $user->setOriginalAttributes(array_replace($user->getOriginalAttributes(), $changes));
    }

    /**
     * A fingerprint of the user's password hash, safe to keep in the session.
     *
     * @param Authable $user
     * @return string
     */
    protected function passwordFingerprint(Authable $user): string
    {
        return $this->fingerprintOf($user->getAuthPassword());
    }

    /**
     * A fingerprint of a password hash, safe to keep in the session.
     *
     * @param string|null $hash
     * @return string
     */
    protected function fingerprintOf(?string $hash): string
    {
        return hash_hmac('sha256', (string) $hash, (string) config('app.key', ''));
    }

    /**
     * The store the application's sessions are kept in.
     *
     * @return SessionStoreInterface
     */
    protected function sessionStore(): SessionStoreInterface
    {
        return SessionManager::store();
    }

    /**
     * Whether the store knows which sessions belong to this actor's users.
     *
     * @param SessionStoreInterface $store
     * @return bool
     */
    protected function tracksUsersIn(SessionStoreInterface $store): bool
    {
        return $store instanceof UserSessionStore
            && $this->getSessionKey() === SessionManager::userSessionKey();
    }

    /**
     * Set the authenticated user in the session.
     *
     * @param Authable $user
     */
    private function setUser(Authable $user): void
    {
        session()->regenerate();

        session()->put($this->getSessionKey(), $user->getAuthIdentifier());
        session()->put($this->getPasswordFingerprintKey(), $this->passwordFingerprint($user));

        $this->resolvedUser = $user;
    }

    /**
     * Check if user was authenticated via remember token.
     *
     * @return bool
     */
    public function viaRemember(): bool
    {
        return session($this->getViaRememberKey(), false)
            && cookie()->has($this->getRememberCookieName());
    }

    /**
     * Get the authenticated user id
     *
     * @return int|string|null
     */
    public function id(): int|string|null
    {
        return $this->user()?->getAuthIdentifier();
    }

    /**
     * Get the name of the actor.
     *
     * @return string
     */
    public function name(): string
    {
        return $this->actorName;
    }

    /**
     * Check if the user is authorized to do some action.
     *
     * @param string $scope
     * @param mixed  ...$arguments
     * @return bool
     */
    public function can(string $scope, mixed ...$arguments): bool
    {
        if (!class_exists(\Doppar\Authorizer\Support\Facades\Guard::class)) {
            throw new \RuntimeException(
                'Authorization failed: Doppar Guard class not found. Please install the "doppar/guard" package and ensure it is properly configured before using this feature.'
            );
        }

        return (bool) \Doppar\Authorizer\Support\Facades\Guard::allows($scope, ...$arguments);
    }

    /**
     * Determine if the current request is targeting the API.
     *
     * @return bool
     */
    private function isApiRequest(): bool
    {
        return (bool) request()->isApiRequest();
    }
}
