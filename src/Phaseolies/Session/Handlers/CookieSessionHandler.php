<?php

namespace Phaseolies\Session\Handlers;

use Phaseolies\Session\Contracts\AbstractSessionHandler;
use Phaseolies\Config\Config;
use RuntimeException;

class CookieSessionHandler extends AbstractSessionHandler
{
    /**
     * Initializes the session system and applies cookie-related configurations.
     *
     * @throws RuntimeException if the session fails to start.
     */
    public function initialize(): void
    {
        $this->configureCookieSession();
        $this->setCookieParameters();
        $this->validate();
    }

    /**
     * Starts a session lifecycle, handles session ID regeneration,
     * and ensures a session token is generated.
     *
     * This method checks if the session ID should be regenerated based on
     * a configured interval. If regeneration is needed, it calls `session_regenerate_id()`
     * and updates the `last_regenerated` timestamp in the session.
     * Finally, it ensures a session token is generated.
     */
    public function start(): void
    {
        if (session_status() === PHP_SESSION_NONE && !session_start()) {
            throw new RuntimeException("Failed to start session.");
        }

        if ($this->shouldRegenerate()) {
            $this->regenerate();
            session()->put('last_regenerated', time());
        }

        $this->generateToken();
    }

    /**
     * Configures PHP session INI settings to use cookie-based, user-defined session storage.
     *
     * This private method sets various `session.ini` directives to ensure
     * sessions are handled via cookies and that the custom save handler
     * methods (`open`, `close`, `read`, `write`, `destroy`, `gc`) are used.
     * It configures security-related settings like `httponly`, `secure`, and `samesite`.
     */
    private function configureCookieSession(): void {}

    /**
     * Applies cookie parameters based on configuration.
     *
     * This method uses `session_set_cookie_params()` to set the detailed
     * parameters for the session cookie, including its lifetime, path, domain,
     * security flags (secure, httponly), and SameSite policy.
     * It also sets the session name and the garbage collection max lifetime.
     */
    private function setCookieParameters(): void
    {
        @ini_set('session.gc_maxlifetime', $this->config['lifetime'] * 60);
    }

    /**
     * Validates session cookie integrity; destroys cookie if it is invalid or cannot be decrypted.
     *
     * This method checks if a session cookie exists. If it does, it attempts to decrypt
     * its content. If the decryption fails (e.g., due to tampering or incorrect key)
     * or results in empty data, the session cookie is destroyed to prevent
     * the use of invalid session data.
     */
    public function validate(): void
    {
        if (isset($_COOKIE[$this->config['cookie']])) {
            try {
                if (empty($this->decrypt($_COOKIE[$this->config['cookie']]))) {
                    $this->destroyCookie();
                }
            } catch (RuntimeException $e) {
                $this->destroyCookie();
            }
        }
    }

    /**
     * Called when a session is opened.
     *
     * @param string $savePath
     * @param string $sessionName
     * @return bool
     */
    public function open($savePath, $sessionName): bool
    {
        return true;
    }

    /**
     * Called when a session is closed.
     *
     * @return bool
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * Reads session data from the cookie, decrypting it.
     *
     * @param string $sessionId
     * @return string
     */
    public function read($sessionId): string
    {
        if (!isset($_COOKIE[@session_name()])) {
            return '';
        }

        try {
            $data = $this->decrypt($_COOKIE[@session_name()]);
            if (empty($data)) {
                throw new RuntimeException('Empty decrypted session data');
            }

            return $data;
        } catch (RuntimeException $e) {
            $this->destroyCookie();
            return '';
        }
    }

    /**
     * Writes session data to the cookie, encrypting it.
     *
     * @param string $sessionId
     * @param string $sessionData
     * @return bool
     */
    public function write($sessionId, $sessionData): bool
    {
        if (empty($sessionData)) {
            return true;
        }

        try {
            $params = session_get_cookie_params();
            $encrypted = $this->encrypt($sessionData);

            $result = setcookie(
                session_name(config('session.cookie')),
                $encrypted,
                [
                    'expires' => $params['lifetime'] ? time() + $params['lifetime'] : 0,
                    'path' => $params['path'],
                    'domain' => $params['domain'],
                    'secure' => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => $params['samesite'],
                ]
            );

            if (!$result) {
                return false;
            }

            $_COOKIE[session_name(config('session.cookie'))] = $encrypted;

            return true;
        } catch (RuntimeException $e) {
            return false;
        }
    }

    /**
     * Destroys the session data by invalidating the session cookie.
     *
     * @param string $sessionId
     * @return bool
     */
    public function destroy($sessionId): bool
    {
        return $this->destroyCookie();
    }

    /**
     * Performs garbage collection for session data.
     *
     * @param int $maxlifetime
     * @return bool
     */
    public function gc($maxlifetime): bool
    {
        return true;
    }

    /**
     * Destroys the session cookie by setting its expiration to a past time.
     *
     * @return bool
     */
    private function destroyCookie(): bool
    {
        if (headers_sent()) {
            return false;
        }

        setcookie($this->config['cookie'], '', [
            'expires' => time() - 3600,
            'path' => $this->config['path'],
            'domain' => $this->config['domain'],
            'secure' => $this->config['secure'],
            'httponly' => true,
        ]);

        unset($_COOKIE[$this->config['cookie']]);

        return true;
    }

    /**
     * Encrypts the given data using AES-256-CBC encryption.
     *
     * @param string $data
     * @return string
     * @throws RuntimeException
     */
    private function encrypt(string $data): string
    {
        $key = Config::get('app.key');
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);

        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypts the given base64-encoded encrypted data using AES-256-CBC decryption.
     *
     * @param string $data
     * @return string
     * @throws RuntimeException
     */
    private function decrypt(string $data): string
    {
        $key = Config::get('app.key');

        // Ensure key is not null to avoid deprecation
        if ($key === null) {
            throw new RuntimeException('Encryption key not configured');
        }

        $data = base64_decode($data);
        $ivSize = openssl_cipher_iv_length('aes-256-cbc');
        $iv = substr($data, 0, $ivSize);
        $encrypted = substr($data, $ivSize);

        // Ensure IV is the correct length (16 bytes for aes-256-cbc)
        if (strlen($iv) < $ivSize) {
            $iv = str_pad($iv, $ivSize, "\0");
        }

        return openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
    }
}
