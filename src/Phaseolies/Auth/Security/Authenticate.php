<?php

namespace Phaseolies\Auth\Security;

use Phaseolies\Support\Facades\Hash;
use Phaseolies\Support\Facades\Crypt;
use Phaseolies\Database\Entity\Model;

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
     * @var Model|null
     */
    private $statelessUser = null;

    /**
     * Cache for user version checks during the current request.
     *
     * @var array
     */
    private static $versionCheckCache = [];

    /**
     * Per-instance resolved user cache
     *
     * @var Model|null
     */
    private ?Model $resolvedUser = null;

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
     * @return Model
     */
    protected function getModel(): Model
    {
        return app($this->config['model']);
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
     * The session key used to cache the full user object for this actor.
     *
     * @return string
     */
    protected function getCacheKey(): string
    {
        return 'cache_auth_' . $this->actorName;
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

        if (!$user || !Hash::check($password, $user->password)) {
            return false;
        }

        $this->login($user, $remember);

        return true;
    }

    /**
     * Log in a user instance.
     *
     * @param Model $user
     * @param bool $remember
     * @return bool
     * @throws \InvalidArgumentException
     */
    public function login($user, bool $remember = false): bool
    {
        $authModel = $this->getModel();

        if (!$user instanceof $authModel) {
            throw new \InvalidArgumentException(
                "Argument #1 ($user) must be an instance of $authModel " . gettype($user) . ' given'
            );
        }

        if ($this->hasTwoFactorEnabled($user) && !$this->isApiRequest()) {
            session()->put($this->getTwoFactorUserKey(), $user->id);
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
     * @return Model|null
     */
    public function loginUsingId(int $id, bool $remember = false): ?Model
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
     * @return Model|null
     */
    public function onceUsingId(int $id): ?Model
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
     * @return Model|null
     */
    public function user(): ?Model
    {
        if ($this->resolvedUser !== null) {
            return $this->resolvedUser;
        }

        if ($this->statelessUser !== null) {
            return $this->statelessUser;
        }

        $authModel  = $this->getModel();
        $cacheKey   = $this->getCacheKey();
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

        if (session()->has($cacheKey)) {
            $cache = session($cacheKey);
            if ($this->isUserCacheValid($cache)) {
                return $this->resolvedUser = $cache['user'];
            }
        }

        if (session()->has($sessionKey)) {
            $user = $authModel::find(session($sessionKey));

            if ($user) {
                $this->cacheUser($user);
                return $this->resolvedUser = $user;
            }
        }

        if (cookie()->has($this->getRememberCookieName())) {
            try {
                /**
                 * @var string
                 */
                $rememberToken = Crypt::decrypt(cookie()->get($this->getRememberCookieName()));
            } catch (\Exception $e) {
                $this->expireRememberCookie();
                return null;
            }

            $segments = explode('|', $rememberToken);

            if (count($segments) !== 3) {
                $this->expireRememberCookie();
                return null;
            }

            [$id, $token, $hash] = $segments;

            if (!Hash::check($id . $token, $hash)) {
                $this->expireRememberCookie();
                return null;
            }

            $user = $authModel::find($id);

            if (!$user || !$user->remember_token) {
                $this->expireRememberCookie();
                return null;
            }

            if (Hash::check($token, $user->remember_token)) {
                if ($this->hasTwoFactorEnabled($user)) {
                    session()->put($this->getTwoFactorUserKey(), $user->id);
                    session()->put($this->getTwoFactorRememberKey(), true);
                }

                $this->setUser($user);

                return $this->resolvedUser = $user;
            }

            // Token didn't match - possible theft attempt
            $this->expireRememberCookie();
            $user->remember_token = null;
            $user->save();
        }

        return null;
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

        if ($user && $user?->remember_token) {
            $user->remember_token = null;
            $user->withoutHook();
            $user->save();
        }

        $this->resolvedUser  = null;
        $this->statelessUser = null;

        session()->forget($this->getSessionKey());
        session()->forget($this->getCacheKey());
        session()->forget($this->getViaRememberKey());

        // Only fully invalidate the session on the default actor so that other
        // actors (e.g. "admin") remain active when only the "web" actor logs out.
        if ($this->actorName === config('auth.default', 'web')) {
            session()->invalidate();
            session()->regenerateToken();
        }

        if (cookie()->has($this->getRememberCookieName())) {
            $this->expireRememberCookie();
        }
    }

    /**
     * Set the authenticated user in the session.
     *
     * @param Model $user
     */
    private function setUser(Model $user): void
    {
        session()->put($this->getSessionKey(), $user->id);

        $this->cacheUser($user);

        $this->resolvedUser = $user;
    }

    /**
     * Cache the user data
     *
     * @param Model $user
     * @return void
     */
    private function cacheUser(Model $user): void
    {
        session()->put($this->getCacheKey(), [
            'user'       => $user,
            'version'    => $user?->updated_at,
            'expires_at' => now()->addMinutes(30)->timestamp,
        ]);
    }

    /**
     * Check cache expiry
     *
     * @param array $cache
     * @return bool
     */
    private function isUserCacheValid(array $cache): bool
    {
        if ($cache['expires_at'] < time()) {
            return false;
        }

        $userId = $cache['user']->id;

        if (isset(self::$versionCheckCache[$this->actorName][$userId])) {
            return $cache['version'] === self::$versionCheckCache[$this->actorName][$userId];
        }

        $currentVersion = $cache['user']->newQuery()
            ->select('updated_at')
            ->where('id', $userId)
            ->first();

        self::$versionCheckCache[$this->actorName][$userId] = $currentVersion?->updated_at;

        return $cache['version'] === $currentVersion?->updated_at;
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
     * @return int|null
     */
    public function id(): ?int
    {
        return $this->user()?->id ?? null;
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
