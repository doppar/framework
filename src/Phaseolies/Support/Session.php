<?php

namespace Phaseolies\Support;

class Session
{
    /**
     * The session data.
     *
     * @var array
     */
    protected $data = [];

    /**
     * The session peek data.
     *
     * @var array
     */
    protected $peek = [];

    /**
     * Flash data key.
     *
     * @var string
     */
    protected $flashKey = '_flash';

    /**
     * Constructor.
     *
     * @param array|null $data
     */
    public function __construct(?array &$data = [])
    {
        $this->data = &$_SESSION;
        $this->peek = &$_SESSION;
    }

    /**
     * Get a session value by key.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        $value = $this->data[$key] ?? $default;

        return $value;
    }

    /**
     * Get a session value by key.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function pull(string $key, $default = null)
    {
        $value = $this->data[$key] ?? $default;

        $this->forget($key);

        return $value;
    }

    /**
     * Get a session value by key.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getPeek(string $key, $default = null)
    {
        $value = $this->peek[$key] ?? $default;

        $this->forget($key);

        return $value;
    }

    /**
     * Put a key-value pair into the session.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function putPeek(string $key, $value): void
    {
        $this->peek[$key] = $value;
    }

    /**
     * Remove all session peek data.
     *
     * @return void
     */
    public function flushPeek(): void
    {
        $this->peek = [];
    }

    /**
     * Put a key-value pair into the session.
     *
     * @param string|array $key
     * @param mixed $value
     * @return void
     */
    public function put(string|array $key, $value = null): void
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->data[$k] = $v;
            }
        } else {
            $this->data[$key] = $value;
        }
    }


    /**
     * Check if a session key exists.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    /**
     * Remove a session key.
     *
     * @param string $key
     * @return void
     */
    public function forget(string $key): void
    {
        unset($this->data[$key]);
    }

    /**
     * Remove all session data.
     *
     * @return void
     */
    public function flush(): void
    {
        $this->data = [];
    }

    /**
     * Regenerate the session ID.
     *
     * @param bool $deleteOldSession
     * @return void
     */
    public function regenerate(bool $deleteOldSession = true): void
    {
        session_regenerate_id($deleteOldSession);
    }

    /**
     * Get all session data.
     *
     * @return array
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * Get the session ID.
     *
     * @return string
     */
    public function getId(): string
    {
        return session_id();
    }

    /**
     * Set the session ID.
     *
     * @param string $id
     * @return void
     */
    public function setId(string $id): void
    {
        session_id($id);
    }

    /**
     * Destroy the session.
     *
     * @return void
     */
    public function destroy(): void
    {
        session_destroy();

        $this->data = [];
        $this->peek = [];
    }

    /**
     * Get the CSRF token.
     *
     * @return string|null
     */
    public function token(): ?string
    {
        return $this->get('_token');
    }

    /**
     * Set a flash message.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function flash(string $key, $value): void
    {
        $this->put($key, $value);
    }

    /**
     * Keep flash data for the next request.
     *
     * @param string|array $keys
     * @return void
     */
    public function reflash($keys): void
    {
        if (is_string($keys)) {
            $keys = [$keys];
        }

        foreach ($keys as $key) {
            if ($this->has($this->flashKey . '.' . $key)) {
                $this->flash($key, $this->get($this->flashKey . '.' . $key));
            }
        }
    }

    /**
     * Invalidate the session (regenerate ID and clear all data).
     *
     * @return void
     */
    public function invalidate(): void
    {
        $this->regenerate(true);

        $this->flush();
    }

    /**
     * Regenerate the CSRF token.
     *
     * @return void
     */
    public function regenerateToken(): void
    {
        $this->put('_token', $this->generateToken());
    }

    /**
     * Generate a new CSRF token.
     *
     * @return string
     */
    protected function generateToken(): string
    {
        return bin2hex(random_bytes(16));
    }
}
