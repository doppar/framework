<?php

namespace Phaseolies\Auth\Security;

use Phaseolies\Support\Facades\Hash;
use App\Models\User;
use Phaseolies\Database\Eloquent\Model;

class Authenticate extends Model
{
    private $data = [];

    /**
     * The current stateless user (for onceUsingId)
     * @var User|null
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
        $authKey = app(User::class)->getAuthKeyName();

        $authKeyValue = $credentials[$authKey] ?? '';
        $password = $credentials['password'] ?? '';

        $user = User::query()->where($authKey, '=', $authKeyValue)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            return false;
        }

        $this->login($user, $remember);
        return true;
    }

    /**
     * Log in a user instance.
     *
     * @param User $user
     * @param bool $remember
     * @return bool
     * @throws \InvalidArgumentException
     */
    public function login($user, bool $remember = false): bool
    {
        if (!$user instanceof User) {
            throw new \InvalidArgumentException(
                'Argument #1 ($user) must be an instance of App\Models\User but ' . gettype($user) . ' given'
            );
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
     * @return User|null
     */
    public function loginUsingId(int $id, bool $remember = false): ?User
    {
        $user = User::find($id);

        if ($user) {
            $this->login($user, $remember);
        }

        return $user;
    }

    /**
     * Log in a user by their ID for a single request (no session/cookie).
     *
     * @param int $id
     * @return User|null
     */
    public function onceUsingId(int $id): ?User
    {
        $user = User::find($id);

        if ($user) {
            $this->statelessUser = $user;
            return $user;
        }

        return null;
    }

    /**
     * Get the currently authenticated user.
     *
     * @return User|null
     */
    public function user(): ?User
    {
        if ($this->statelessUser !== null) {
            return $this->statelessUser;
        }

        if (session()->has('user')) {
            $user = User::find(session()->get('user')->id);
            if ($user) {
                $reflectionProperty = new \ReflectionProperty(User::class, 'unexposable');
                $reflectionProperty->setAccessible(true);
                $user->makeHidden($reflectionProperty->getValue($user));
                return $user;
            }
        }

        if (cookie()->has('remember_token')) {
            $user = User::query()->where('remember_token', '=', cookie()->get('remember_token'))->first();
            if ($user) {
                $this->setUser($user);
                return $user;
            }
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
        $this->statelessUser = null;
        session()->forget('user');

        if (cookie()->has('remember_token')) {
            setcookie('remember_token', '', time() - 3600, '/');
        }
    }

    /**
     * Set the authenticated user in the session.
     *
     * @param User $user
     */
    private function setUser(User $user): void
    {
        session()->put('user', $user);
    }

    /**
     * Set the remember token for the user.
     *
     * @param User $user
     */
    private function setRememberToken(User $user): void
    {
        $token = bin2hex(random_bytes(32));
        $user->remember_token = Hash::make($token);
        $user->save();

        setcookie(
            'remember_token',
            $token,
            time() + 60 * 60 * 24 * 30, // 30 days
            '/',
            '',
            true,  // HTTPS only
            true   // HttpOnly
        );
    }

    /**
     * Check if user was authenticated via remember token.
     *
     * @return bool
     */
    public function viaRemember(): bool
    {
        return cookie()->has('remember_token');
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
     * @return bool
     */
    public function can(string $scope): bool
    {
        return Guard::allows($scope);
    }
}
