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
        if (session_status() === PHP_SESSION_NONE && !session_start()) {
            throw new RuntimeException("Failed to start session.");
        }

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
    private function configureCookieSession(): void
    {
        @ini_set('session.save_handler', 'user');
        @ini_set('session.use_cookies', 1);
        @ini_set('session.use_only_cookies', 1);
        @ini_set('session.use_strict_mode', 1);
        @ini_set('session.cookie_httponly', $this->config['http_only'] ? 1 : 0);
        @ini_set('session.cookie_secure', $this->config['secure'] ? 1 : 0);
        @ini_set('session.cookie_samesite', $this->config['same_site']);
        @ini_set('session.cookie_path', $this->config['path']);
        @ini_set('session.cookie_domain', $this->config['domain']);
        @ini_set('session.cookie_lifetime', $this->config['expire_on_close'] ? 0 : $this->config['lifetime'] * 60);

        @session_set_save_handler(
            [$this, 'open'],
            [$this, 'close'],
            [$this, 'read'],
            [$this, 'write'],
            [$this, 'destroy'],
            [$this, 'gc']
        );
    }

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
        @session_set_cookie_params([
            'lifetime' => $this->config['expire_on_close'] ? 0 : $this->config['lifetime'] * 60,
            'path' => $this->config['path'],
            'domain' => $this->config['domain'],
            'secure' => $this->config['secure'],
            'httponly' => $this->config['http_only'],
            'samesite' => $this->config['same_site']
        ]);

        @session_name($this->config['cookie']);
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
     * @param string $savePath The path where session data is stored (not used in cookie handler).
     * @param string $sessionName The name of the session (not used in cookie handler).
     * @return bool Always returns true, indicating success.
     */
    public function open($savePath, $sessionName): bool
    {
        return true;
    }

    /**
     * Called when a session is closed.
     *
     * @return bool Always returns true, indicating success.
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * Reads session data from the cookie, decrypting it.
     *
     * @param string $sessionId The ID of the session to read (not directly used as data is in cookie).
     * @return string The decrypted session data, or an empty string if not found or invalid.
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
     * @param string $sessionId The ID of the session to write (not directly used as data is in cookie).
     * @param string $sessionData The raw session data string to be written.
     * @return bool True on successful write, false otherwise (e.g., if `setcookie` fails).
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
                @session_name(),
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

            $_COOKIE[@session_name()] = $encrypted;
            return true;
        } catch (RuntimeException $e) {
            return false;
        }
    }

    /**
     * Destroys the session data by invalidating the session cookie.
     *
     * @param string $sessionId The ID of the session to destroy (not directly used).
     * @return bool True on successful destruction, false if headers have already been sent.
     */
    public function destroy($sessionId): bool
    {
        return $this->destroyCookie();
    }

    /**
     * Performs garbage collection for session data.
     *
     * @param int $maxlifetime The maximum lifetime of a session (not used for cookie handler).
     * @return bool Always returns true.
     */
    public function gc($maxlifetime): bool
    {
        return true;
    }

    /**
     * Destroys the session cookie by setting its expiration to a past time.
     *
     * @return bool True on successful cookie destruction, false if headers have already been sent.
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
     * @param string $data The plain text data to encrypt.
     * @return string The base64-encoded string containing the IV and encrypted data.
     * @throws RuntimeException If the application key is not set or encryption fails.
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
     * @param string $data The base64-encoded string containing the IV and encrypted data.
     * @return string The decrypted plain text data.
     * @throws RuntimeException If the application key is not set, data is malformed, or decryption fails.
     */
    private function decrypt(string $data): string
    {
        $key = Config::get('app.key');
        $data = base64_decode($data);
        $ivSize = openssl_cipher_iv_length('aes-256-cbc');
        $iv = substr($data, 0, $ivSize);
        $encrypted = substr($data, $ivSize);
        return openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
    }
}
