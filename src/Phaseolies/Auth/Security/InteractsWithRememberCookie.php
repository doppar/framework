<?php

namespace Phaseolies\Auth\Security;

use Phaseolies\Support\Facades\Hash;
use Phaseolies\Support\Facades\Crypt;
use Phaseolies\Database\Eloquent\Model;

trait InteractsWithRememberCookie
{
    /**
     * The cookie name prefix for remember me tokens
     */
    protected string $rememberCookiePrefix = 'remember_doppar_';

    /**
     * Get the hashed cookie name for remember me functionality
     */
    protected function getRememberCookieName(): string
    {
        return $this->rememberCookiePrefix . sha1('doppar');
    }

    /**
     * Set the remember token for the user.
     *
     * @param Model $user
     */
    private function setRememberToken(Model $user): void
    {
        $token = bin2hex(random_bytes(32));
        $user->remember_token = Hash::make($token);
        $user->save();

        $cookieValue = $user->id . '|' . $token . '|' . Hash::make($user->id . $token);

        session()->put('auth_via_remember', true);

        $this->setRememberCookie($cookieValue);
    }

    /**
     * Set the remember me cookie
     *
     * @param string $value
     * @return void
     */
    protected function setRememberCookie(string $value): void
    {
        /**
         * @var string
         */
        $encryptedValue = Crypt::encrypt($value);

        setcookie(
            $this->getRememberCookieName(),
            $encryptedValue,
            [
                'expires' => time() + 60 * 60 * 24 * 30,
                'path' => config('session.path') ?? '/',
                'domain' => config('session.domain') ?? '',
                'secure' => request()->isSecure(),
                'httponly' => true,
                'samesite' => config('session.same_site', 'Lax')
            ]
        );
    }

    /**
     * Removes expired cookie
     *
     * @return void
     */
    protected function expireRememberCookie(): void
    {
        $cookieName = $this->getRememberCookieName();

        setcookie($cookieName, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => config('session.domain') ?? '',
            'secure' => request()->isSecure(),
            'httponly' => true,
            'samesite' => 'lax'
        ]);

        cookie()->remove($cookieName);
    }
}
