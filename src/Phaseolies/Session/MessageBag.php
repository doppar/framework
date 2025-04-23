<?php

namespace Phaseolies\Session;

class MessageBag
{
    /**
     * Set a value in the session.
     *
     * @param string $key The key to set.
     * @param mixed $value The value to store.
     *
     * @return void
     */
    public static function set(string $key, $value): void
    {
        session()->put($key, $value);
    }

    /**
     * Stores the provided input data in the session for later retrieval.
     * This is typically used when you want to "flash" the input data for the next request,
     * such as when a form submission fails, and you want to retain the user's input.
     *
     * @return void
     */
    public static function flashInput()
    {
        session()->put('input', $_POST);
    }

    /**
     * Retrieves the old input data that was previously stored in the session.
     * If a specific key is provided, it will return the value associated with that key;
     * otherwise, it returns all stored input data.
     *
     * This is commonly used to retain form values between requests, for example,
     * when a form is redisplayed after a validation failure.
     *
     * @param string|null $key The key for a specific input value (e.g., 'email'). If null, returns all stored input.
     *
     * @return string|null
     *
     */
    public static function old(?string $key = null): ?string
    {
        $input = session('input') ?? null;

        if ($key) {
            $data = session('input');
            session()->forget('input');
            return $data[$key] ?? null;
        }

        return $input;
    }

    /**
     * Checks if old input data exists for a specific key.
     *
     * @param string $key The key for the input value.
     *
     * @return bool True if the input exists, false otherwise.
     */
    public static function has(string $key): bool
    {
        return isset(session('input')[$key]);
    }

    /**
     * Clears all old input data from the session.
     *
     * @return void
     */
    public static function clear(): void
    {
        session()->flushPeek();
        session()->forget('input');
    }

    /**
     * Gets all old input data from the session.
     *
     * @return array|null The old input data, or null if no data exists.
     */
    public static function all(): ?array
    {
        return session('input') ?? null;
    }
}
