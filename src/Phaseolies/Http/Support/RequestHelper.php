<?php

namespace Phaseolies\Http\Support;

use InvalidArgumentException;
use App\Models\User;

trait RequestHelper
{
    use InteractsWithDTO;

    /**
     * Stores validation passed data.
     *
     * @var array<string, mixed>
     */
    public array $passedData = [];

    /**
     * Stores validation errors.
     *
     * @var array<string, mixed>
     */
    public array $errors = [];

    /**
     * Stores the input data.
     *
     * @var array<string, mixed>
     */
    public ?array $input = [];

    /**
     * Retrieves all input data except for the specified keys.
     *
     * @param array|string $keys The keys to exclude.
     * @return array<string, mixed> The filtered input data.
     */
    public function except(array|string ...$keys): array
    {
        $keys = count($keys) === 1 && is_array($keys[0])
            ? $keys[0]
            : $keys;

        return array_diff_key($this->all(), array_flip($keys));
    }

    /**
     * Retrieves only the specified keys from the input data.
     *
     * @param array|string $keys The keys to include.
     * @return array<string, mixed> The filtered input data.
     */
    public function only(array|string ...$keys): array
    {
        $keys = count($keys) === 1 && is_array($keys[0])
            ? $keys[0]
            : $keys;

        return array_intersect_key($this->all(), array_flip($keys));
    }

    /**
     * Retrieves the validation passed data, excluding specified fields.
     *
     * @param array<string> $excludeKeys The keys to exclude (e.g., ['csrf_token', 'other_field']).
     * @return array<string, mixed> The validation passed data without the excluded fields.
     */
    public function passed(array $excludeKeys = ['csrf_token']): array
    {
        $exclude = array_flip($excludeKeys);

        return array_diff_key($this->passedData, $exclude);
    }

    /**
     * Retrieves the validation errors, excluding the `csrf_token` field.
     *
     * @return array<string, mixed> The validation errors without `csrf_token`.
     */
    public function failed(array $excludeKeys = ['csrf_token']): array
    {
        return $this->errors ?? [];
    }

    /**
     * Sets the validation passed data.
     *
     * @param array<string, mixed> $data The validation passed data.
     * @return self The current instance.
     */
    public function setPassedData(array $data): self
    {
        $this->passedData = $data;

        return $this;
    }

    /**
     * Sets the validation errors.
     *
     * @param array<string, mixed> $errors The validation errors.
     * @return self The current instance.
     */
    public function setErrors(array $errors): self
    {
        $this->errors = $errors;

        return $this;
    }

    /**
     * Checks if the input data is empty.
     *
     * @return bool True if the input data is empty, false otherwise.
     */
    public function isEmpty(): bool
    {
        return empty($this->all());
    }

    /**
     * Retrieves a specific input parameter or all input data.
     *
     * @param string|null $key The parameter to retrieve (null returns all input)
     * @param mixed $default Default value if parameter doesn't exist
     * @return mixed The input value, default value, or all input
     */
    public function input(?string $key = null, $default = null): mixed
    {
        if (empty($this->input)) {
            $this->input = $this->all();
        }

        return $this->input[$key] ?? $default;
    }

    /**
     * Checks if a specific parameter exists in the input data.
     *
     * @param string $param The parameter to check.
     * @return bool True if the parameter exists, false otherwise.
     */
    public function has(string $param): bool
    {
        if (empty($this->input)) {
            $this->input = $this->all();
        }

        return array_key_exists($param, $this->input) && $this->input[$param] !== '';
    }

    /**
     * Get the authenticated user.
     *
     * @return \App\Models\User|null The authenticated user instance or null if no user is authenticated.
     */
    public function auth(): ?User
    {
        return app('auth')->user() ?? null;
    }

    /**
     * Get the currently authenticated user.
     *
     * @return \Phaseolies\Models\User|null
     */
    public function user(): ?User
    {
        return app('auth')->user() ?? null;
    }

    /**
     * Determine if any of the specified keys are present in the request
     *
     * @param string $keys Keys to check
     * @return bool
     */
    public function hasAny(string ...$keys): bool
    {
        foreach ($keys as $key) {
            if ($this->has($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Merge default values for missing keys
     *
     * @param array $defaults Key-value pairs to merge
     * @return static
     */
    public function mergeIfMissing(array $defaults): static
    {
        $missing = array_filter($defaults, fn($key) => !$this->has($key), ARRAY_FILTER_USE_KEY);

        if (!empty($missing)) {
            $this->merge($missing);
        }

        return $this;
    }

    /**
     * Retrieve an input value by key, apply a callback to it, and return the result.
     *
     * @param string $key
     * @param callable $callback
     * @param mixed $default
     * @return mixed
     */
    public function pipe(string $key, callable $callback, $default = null)
    {
        $value = $this->input($key, $default);

        return $callback($value);
    }

    /**
     * Convert empty inputs to null with customizable rules
     *
     * @param bool $includeStrings Convert empty strings
     * @param bool $includeArrays Convert empty arrays 
     * @param bool $includeWhitespace Convert whitespace-only strings
     * @return static
     */
    public function nullifyBlanks(
        bool $includeStrings = true,
        bool $includeArrays = false,
        bool $includeWhitespace = true
    ): static {
        $data = $this->all();

        array_walk_recursive($data, function (&$value) use ($includeStrings, $includeArrays, $includeWhitespace) {
            if (($includeStrings && $value === '') ||
                ($includeWhitespace && is_string($value) && trim($value) === '') ||
                ($includeArrays && is_array($value) && empty($value))
            ) {
                $value = null;
            }
        });

        $this->request->replace($data);

        return $this;
    }

    /**
     * Execute a callback with the input value for the specified key.
     *
     * @param string $key
     * @param callable $callback
     * @return static
     */
    public function tapInput(string $key, callable $callback): static
    {
        $callback($this->input($key));

        return $this;
    }

    /**
     * Execute a callback with the input value if the input key is filled (not empty).
     *
     * @param string $key
     * @param callable $callback
     * @return static
     */
    public function ifFilled(string $key, callable $callback): static
    {
        if ($this->filled($key)) {
            $callback($this->input($key));
        }

        return $this;
    }

    /**
     * Determine if the given input key is present and not empty.
     *
     * @param  string  $key
     * @return bool
     */
    public function filled(string $key): bool
    {
        $value = $this->input($key);

        // Check if the value exists and is not empty
        // - empty strings
        // - null values
        // - empty arrays
        // are considered "not filled"
        return !empty($value);
    }

    /**
     * Transform input values based on a map of keys and callbacks.
     *
     * Unlike `pipeInputs()`, this method returns a new array with transformed
     * values and does not modify the original input data.
     *
     * @param array $items
     * @return array
     */
    public function transform(array $items): array
    {
        $transformed = [];
        foreach ($items as $key => $callback) {
            $transformed[$key] = $callback($this->input($key));
        }

        return $transformed;
    }

    /**
     * Apply a series of callbacks to corresponding input values and merge the results back.
     *
     * @param array $items
     * @return static
     */
    public function pipeInputs(array $items): static
    {
        foreach ($items as $key => $callback) {
            $value = $this->input($key);
            $this->merge([$key => $callback($value)]);
        }

        return $this;
    }

    /**
     * Validate that the input value for a given key passes a user-defined validator.
     *
     * @param string $key
     * @param callable $validator
     * @throws InvalidArgumentException
     * @return static
     */
    public function ensure($key, callable $validator): static
    {
        $value = $this->input($key);
        if (!$validator($value)) {
            throw new InvalidArgumentException("Validation failed for $key");
        }

        return $this;
    }

    /**
     * Process the current data contextually with a callback and merge the results.
     *
     * @param callable $processor
     * @return static
     */
    public function contextual(callable $processor): static
    {
        $this->merge($processor($this->all()));

        return $this;
    }

    /**
     * Conditionally sanitize the data if the given condition is true.
     *
     * @param bool $condition
     * @param array $rules
     * @return void
     */
    public function sanitizeIf(bool $condition, array $rules): void
    {
        if ($condition) {
            $this->sanitize($rules);
        }
    }

    /**
     * Apply a callback function to the current request instance and return the result.
     *
     * @param callable $callback
     * @return mixed
     */
    public function extract(callable $callback): mixed
    {
        return $callback($this);
    }

    /**
     * Cleanse input data based on a set of transformation rules.
     *
     * This method applies formatting/transformation rules to specified keys in the input array.
     * It supports dot notation for nested keys and handles multiple rules per key.
     *
     * Supported rules:
     * - trim: Removes whitespace from the beginning and end of a string
     * - strip_tags: Removes HTML and PHP tags from a string
     * - int: Casts the value to an integer
     * - lowercase: Converts a string to lowercase
     * - uppercase: Converts a string to uppercase
     *
     * @param array $rules
     * @return array
     */
    public function cleanse(array $rules): array
    {
        $data = $this->all();

        foreach ($rules as $key => $ruleString) {
            $value = $this->getValueFromData($data, $key);
            $rulesList = explode('|', $ruleString);

            foreach ($rulesList as $rule) {
                $value = match ($rule) {
                    'trim'       => is_string($value) ? trim($value) : $value,
                    'strip_tags' => is_string($value) ? strip_tags($value) : $value,
                    'int'        => (int) $value,
                    'lowercase'  => is_string($value) ? strtolower($value) : $value,
                    'uppercase'  => is_string($value) ? strtoupper($value) : $value,
                    default      => $value,
                };
            }

            $this->setValueInData($data, $key, $value);
        }

        return $data;
    }

    /**
     * Retrieve a value from a multidimensional array using dot notation.
     *
     * This method allows accessing nested values in an array using a dot-notated key.
     * For example, given the key "user.address.street", it will return:
     * $data['user']['address']['street'] if it exists, or null otherwise.
     *
     * @param array $data
     * @param string $key
     * @return mixed
     */
    private function getValueFromData(array $data, string $key)
    {
        if (strpos($key, '.') === false) {
            return $data[$key] ?? null;
        }

        $keys = explode('.', $key);
        $current = $data;

        foreach ($keys as $k) {
            if (!is_array($current) || !array_key_exists($k, $current)) {
                return null;
            }
            $current = $current[$k];
        }

        return $current;
    }

    /**
     * Set a value in a multidimensional array using dot notation.
     *
     * This method allows setting a nested value in an array using a dot-notated key.
     * For example, given the key "user.address.street", it will set the value at:
     * $data['user']['address']['street'] = $value
     *
     * @param array $data
     * @param string $key
     * @param mixed $value
     * @return void
     */
    private function setValueInData(array &$data, string $key, $value): void
    {
        if (strpos($key, '.') === false) {
            $data[$key] = $value;
            return;
        }

        $keys = explode('.', $key);
        $current = &$data;

        foreach ($keys as $k) {
            if (!isset($current[$k])) {
                $current[$k] = [];
            }
            $current = &$current[$k];
        }

        $current = $value;
    }

    /**
     * Conditionally apply a callback to the request data.
     *
     * @param bool $condition
     * @param callable $callback
     * @return mixed
     */
    public function mapIf(bool $condition, callable $callback): mixed
    {
        // If the given condition is true,
        // the callback is applied to the entire input
        // data (via $this->all()). Otherwise,
        // the original input data is returned unchanged.
        return $condition ? $callback($this->all()) : $this->all();
    }

    /**
     * Retrieve the input value for the given key and convert it to an array.
     *
     * @param string $key
     * @return array
     */
    public function asArray(string $key): array
    {
        $value = $this->input($key);
        if (is_string($value)) {
            return array_filter(array_map('trim', explode(',', $value)));
        }

        return (array) $value;
    }
}
