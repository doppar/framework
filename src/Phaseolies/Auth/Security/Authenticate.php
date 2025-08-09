<?php

namespace Phaseolies\Auth\Security;

use Phaseolies\Support\Facades\Hash;
use Phaseolies\Support\Facades\Crypt;
use Phaseolies\Support\Facades\Cache;
use Phaseolies\Database\Eloquent\Model;

class Authenticate
{
    use InteractsWithTwoFactorAuth, InteractsWithRememberCookie;

    /**
     * @var array
     */
    private $data = [];

    /**
     * The current stateless user (for onceUsingId)
     * @var Model|null
     */
    private $statelessUser = null;

    public function __set($name, $value)
    {
        $this->data[$name] = $value;
    }

    public function __get($name)
    {
        return $this->data[$name] ?? null;
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
        $authModel = app(config('auth.model'));
        $customAuthKey = $authModel->getAuthKeyName();

        $authKeyValue = $credentials[$customAuthKey] ?? '';
        $password = $credentials['password'] ?? '';

        $user = $authModel::query()->where($customAuthKey, $authKeyValue)->first();

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
        $authModel = app(config('auth.model'));

        if (!$user instanceof $authModel) {
            throw new \InvalidArgumentException(
                "Argument #1 ($user) must be an instance of $authModel " . gettype($user) . ' given'
            );
        }

        if ($this->hasTwoFactorEnabled($user)) {
            session()->put('2fa_user_id', $user->id);
            session()->put('2fa_remember', $remember);

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
        $authModel = app(config('auth.model'));

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
        $authModel = app(config('auth.model'));

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
        $authModel = app(config('auth.model'));

        if ($this->statelessUser !== null) {
            return $this->statelessUser;
        }

        if (session()->has('user')) {
            $user = $authModel::find(session('user')->id);

            if ($user) {
                return $user;
            }
        }

        if (cookie()->has($this->getRememberCookieName())) {
            /**
             * @var string
             */
            $rememberToken = Crypt::decrypt(cookie()->get($this->getRememberCookieName()));
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
                $this->setUser($user);
                // Rotating the token for security
                session()->put('auth_via_remember', true);
                $this->setRememberToken($user);
                return $user;
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
     * Log the user out.
     */
    public function logout(): void
    {
        $user = auth()->user();

        if ($user && $user?->remember_token) {
            $user->remember_token = null;
            $user->withoutHook();
            $user->save();
        }

        $this->statelessUser = null;

        session()->forget('user');
        session()->invalidate();
        session()->regenerateToken();

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
        session()->put('user', $user);
    }

    /**
     * Check if user was authenticated via remember token.
     *
     * @return bool
     */
    public function viaRemember(): bool
    {
        return session('auth_via_remember', false)
            && cookie()->has($this->getRememberCookieName());
    }

    /**
     * Get the authenticated user id
     * @return int|null
     */
    public function id(): ?int
    {
        return auth()->user()->id ?? null;
    }

    /**
     * Check if the user is authorized to do some action.
     *
     * @param string $scope
     * @return bool
     */
    public function can(string $scope): bool
    {
        return Cache::stash(
            "auth_scope_{$scope}_" . $this->id(),
            3600,
            fn() => \Doppar\Authorizer\Support\Facades\Guard::allows($scope)
        );
    }
}
