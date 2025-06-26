<?php

namespace Phaseolies\Session\Handlers;

use Phaseolies\Session\Contracts\AbstractSessionHandler;
use Phaseolies\Config\Config;
use RuntimeException;

class CookieSessionHandler extends AbstractSessionHandler
{
    public function initialize(): void
    {
        if (session_status() === PHP_SESSION_NONE && !session_start()) {
            throw new RuntimeException("Failed to start session.");
        }

        $this->configureCookieSession();
        $this->setCookieParameters();
        $this->validate();
    }

    public function start(): void
    {
        if ($this->shouldRegenerate()) {
            $this->regenerate();
            session()->put('last_regenerated', time());
        }

        $this->generateToken();
    }

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

    public function open($savePath, $sessionName): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

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

    public function destroy($sessionId): bool
    {
        return $this->destroyCookie();
    }

    public function gc($maxlifetime): bool
    {
        return true;
    }

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

    private function encrypt(string $data): string
    {
        $key = Config::get('app.key');
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

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
