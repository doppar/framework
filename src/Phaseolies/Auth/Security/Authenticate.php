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
     * Fingerprint of the signed-in user's password when isSessionCurrent() checked it
     *
     * @var string|null
     */
    private ?string $checkedFingerprint = null;

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
        $this->checkedFingerprint = null;

        session()->forget($this->getSessionKey());
        session()->forget($this->getViaRememberKey());
        session()->forget($this->getPasswordFingerprintKey());

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

        $changes = [$passwordName => Hash::make($password)];

        $tokenName = $user->getRememberTokenName();

        if (array_key_exists($tokenName, $user->getAttributes())) {
            if ($this->hasValidRememberCookie($user)) {
                $this->issueRememberToken($user);
            } else {
                $user->setRememberToken(null);

                if (cookie()->has($this->getRememberCookieName())) {
                    $this->expireRememberCookie();
                }
            }

            $changes[$tokenName] = $user->getRememberToken();
        }

        $this->persistCredentials($user, $changes);

        if (session()->has($this->getSessionKey())) {
            session()->put($this->getPasswordFingerprintKey(), $this->passwordFingerprint($user));
        }

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
            $this->endSession(false);

            $user = $this->user();

            if ($user === null) {
                return false;
            }

            $this->checkedFingerprint = $this->passwordFingerprint($user);

            return true;
        }

        $fingerprint = $this->passwordFingerprint($user);

        if (hash_equals($fingerprint, $stored)) {
            $this->checkedFingerprint = $fingerprint;

            return true;
        }

        $this->endSession();

        return false;
    }

    /**
     * Record the user's current password in this device's session, after this request changed it.
     *
     * @param bool $reload
     * @return void
     */
    public function refreshSessionFingerprint(bool $reload = true): void
    {
        if (!session()->has($this->getSessionKey())) {
            return;
        }

        if ($reload) {
            $user = $this->getModel()::find(session($this->getSessionKey()));
        } else {
            $user = $this->resolvedUser;

            if ($user === null || $this->checkedFingerprint === null || !is_string(session($this->getPasswordFingerprintKey()))) {
                return;
            }

            if (hash_equals($this->checkedFingerprint, $this->passwordFingerprint($user))) {
                return;
            }
        }

        if (!$user instanceof Authable) {
            return;
        }

        $fingerprint = $this->passwordFingerprint($user);

        if (session($this->getPasswordFingerprintKey()) !== $fingerprint) {
            session()->put($this->getPasswordFingerprintKey(), $fingerprint);
        }
    }

    /**
     * The other devices the user is signed in on, most recently active first.
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
        return hash_hmac('sha256', (string) $user->getAuthPassword(), (string) config('app.key', ''));
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
