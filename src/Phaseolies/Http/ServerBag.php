<?php

namespace Phaseolies\Http;

/**
 * Class ServerBag
 *
 * A container for managing server-related data (e.g., $_SERVER superglobal).
 * Provides methods to retrieve server variables, check their existence, and extract HTTP headers.
 */
class ServerBag
{
    /**
     * @var array The internal storage for server data.
     */
    private array $server;

    /**
     * ServerBag constructor.
     *
     * @param array $server Initial server data (typically the $_SERVER superglobal).
     */
    public function __construct(array $server = [])
    {
        $this->server = $server;
    }


    /**
     * Sets a value for a given key in the server data.
     *
     * @param string $key The key to set.
     * @param mixed $value The value to assign.
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        $this->server[$key] = $value;
    }

    /**
     * Removes a key from the server data.
     *
     * @param string $key The key to remove.
     * @return void
     */
    public function remove(string $key): void
    {
        unset($this->server[$key]);
    }

    /**
     * Returns the parameter value converted to integer.
     */
    public function getInt(string $key, int $default = 0): int
    {
        return $this->filter($key, $default, \FILTER_VALIDATE_INT, ['flags' => \FILTER_REQUIRE_SCALAR]);
    }

    /**
     * Retrieves the value for a given key from the server data.
     *
     * @param string $key The key to retrieve (e.g., 'REQUEST_METHOD', 'HTTP_HOST').
     * @param mixed $default The default value to return if the key does not exist.
     * @return mixed The value associated with the key, or the default value if the key is not found.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }

    /**
     * Checks if a key exists in the server data.
     *
     * @param string $key The key to check.
     * @return bool True if the key exists, false otherwise.
     */
    public function has(string $key): bool
    {
        return isset($this->server[$key]);
    }

    /**
     * Retrieves all server data as an associative array.
     *
     * @return array The entire server data.
     */
    public function all(): array
    {
        return $this->server;
    }

    /**
     * Extracts and returns HTTP headers from the server data.
     *
     * HTTP headers in the $_SERVER superglobal are prefixed with 'HTTP_'.
     * This method converts keys like 'HTTP_CONTENT_TYPE' to 'Content-Type'.
     *
     * @return array An associative array of HTTP headers.
     */
    public function getHeaders(): array
    {
        $headers = [];
        foreach ($this->server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                // Convert 'HTTP_HEADER_NAME' to 'Header-Name'
                $headerName = str_replace('_', '-', substr($key, 5));
                $headers[$headerName] = $value;
            }
        }

        return $headers;
    }

    /**
     * Filter key.
     *
     * @param int                                     $filter  FILTER_* constant
     * @param int|array{flags?: int, options?: array} $options Flags from FILTER_* constants
     *
     * @see https://php.net/filter-var
     */
    public function filter(string $key, mixed $default = null, int $filter = \FILTER_DEFAULT, mixed $options = []): mixed
    {
        $value = $this->get($key, $default);

        // Always turn $options into an array - this allows filter_var option shortcuts.
        if (!\is_array($options) && $options) {
            $options = ['flags' => $options];
        }

        // Add a convenience check for arrays.
        if (\is_array($value) && !isset($options['flags'])) {
            $options['flags'] = \FILTER_REQUIRE_ARRAY;
        }

        if (\is_object($value) && !$value instanceof \Stringable) {
            throw new \Exception(\sprintf('Parameter value "%s" cannot be filtered.', $key));
        }

        if ((\FILTER_CALLBACK & $filter) && !(($options['options'] ?? null) instanceof \Closure)) {
            throw new \InvalidArgumentException(\sprintf('A Closure must be passed to "%s()" when FILTER_CALLBACK is used, "%s" given.', __METHOD__, get_debug_type($options['options'] ?? null)));
        }

        $options['flags'] ??= 0;
        $nullOnFailure = $options['flags'] & \FILTER_NULL_ON_FAILURE;
        $options['flags'] |= \FILTER_NULL_ON_FAILURE;

        $value = filter_var($value, $filter, $options);

        if (null !== $value || $nullOnFailure) {
            return $value;
        }

        throw new \UnexpectedValueException(\sprintf('Parameter value "%s" is invalid and flag "FILTER_NULL_ON_FAILURE" was not set.', $key));
    }
}
