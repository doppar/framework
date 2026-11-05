<?php

namespace Phaseolies\Auth\Security;

use Phaseolies\Support\Facades\Hash;
use Phaseolies\Support\Facades\Crypt;
use Phaseolies\Auth\Authable;

trait InteractsWithRememberCookie
{
    /**
     * The cookie name prefix for remember me tokens
     *
     * @var string
     */
    protected string $rememberCookiePrefix = 'remember_doppar_';

    /**
     * Get the hashed cookie name for remember me functionality
     *
     * @return string
     */
    protected function getRememberCookieName(): string
    {
        $appName = strtolower(str_replace(' ', '_', config('app.name', 'doppar')));

        return $this->rememberCookiePrefix . $this->actorName . '_' . sha1($appName);
    }

    /**
     * Set the remember token for the user.
     *
     * @param Authable $user
     * @return void
     */
    private function setRememberToken(Authable $user): void
    {
        $this->issueRememberToken($user);

        $user->save();
    }

    /**
     * Give the user a new remember token and send it to this device, without saving the user.
     *
     * @param Authable $user
     * @return string
     */
    private function issueRememberToken(Authable $user): string
    {
        $token = $this->newRememberToken($user);

        $this->sendRememberCookie($user, $token);

        return $token;
    }

    /**
     * Give the user a new remember token, without sending it anywhere or saving the user.
     *
     * @param Authable $user
     * @return string
     */
    private function newRememberToken(Authable $user): string
    {
        $token = bin2hex(random_bytes(32));
        $user->setRememberToken(Hash::make($token));

        return $token;
    }

    /**
     * Send this actor's remember cookie for a token the user already holds.
     *
     * @param Authable $user
     * @param string $token
     * @return void
     */
    private function sendRememberCookie(Authable $user, string $token): void
    {
        $id = $user->getAuthIdentifier();

        session()->put($this->getViaRememberKey(), true);

        $this->setRememberCookie($id . '|' . $token . '|' . Hash::make($id . $token));
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
                'expires'  => time() + 60 * 60 * 24 * 30,
                'path'     => config('session.path') ?? '/',
                'domain'   => config('session.domain') ?? '',
                'secure'   => request()->isSecure(),
                'httponly' => true,
                'samesite' => config('session.same_site', 'Lax'),
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
            'expires'  => time() - 3600,
            'path'     => '/',
            'domain'   => config('session.domain') ?? '',
            'secure'   => request()->isSecure(),
            'httponly' => true,
            'samesite' => 'lax',
        ]);

        cookie()->remove($cookieName);
    }
}
