<?php

namespace Phaseolies\Session;

class MessageBag
{
    /**
     * Set a value in the session.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public static function set(string $key, $value): void
    {
        session()->put($key, $value);
    }

    /**
     * Stores the provided input data in the session for later retrieval.
     *
     * @return void
     */
    public static function flashInput(): void
    {
        $input = $_POST;
        $sensitiveInputExclusions = config('app.exclude_sensitive_input');

        foreach ($sensitiveInputExclusions as $field) {
            unset($input[$field]);
        }

        session()->put('input', $input);
    }

    /**
     * Retrieves the old input data that was previously stored in the session..
     *
     * @param string|null $key
     * @return string|null
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
     * @param string $key
     * @return bool
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
     * @return array|null
     */
    public static function all(): ?array
    {
        return session('input') ?? null;
    }
}
