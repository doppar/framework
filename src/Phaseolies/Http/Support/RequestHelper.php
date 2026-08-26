<?php

namespace Phaseolies\Http\Support;

use InvalidArgumentException;
use Phaseolies\Database\Entity\Model;

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
     * @param array|string $keys
     * @return array<string, mixed>
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
     * @param array|string $keys
     * @return array<string, mixed>
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
     * @param array<string> $excludeKeys
     * @return array<string, mixed>
     */
    public function passed(array $excludeKeys = ['csrf_token']): array
    {
        $exclude = array_flip($excludeKeys);

        return array_diff_key($this->passedData, $exclude);
    }

    /**
     * Retrieves the validation errors, excluding the `csrf_token` field.
     *
     * @return array<string, mixed>
     */
    public function failed(array $excludeKeys = ['csrf_token']): array
    {
        return $this->errors ?? [];
    }

    /**
     * Sets the validation passed data.
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public function setPassedData(array $data): self
    {
        $this->passedData = $data;

        return $this;
    }

    /**
     * Sets the validation errors.
     *
     * @param array<string, mixed> $errors
     * @return self
     */
    public function setErrors(array $errors): self
    {
        $this->errors = $errors;

        return $this;
    }

    /**
     * Checks if the input data is empty.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->all());
    }

    /**
     * Retrieves a specific input parameter or all input data.
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
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
     * @param string $param
     * @return bool
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
     * @return Model|null
     */
    public function auth(): ?Model
    {
        return app('auth')->user() ?? null;
    }

    /**
     * Get the currently authenticated user.
     *
     * @return Model|null
     */
    public function user(): ?Model
    {
        return app('auth')->user() ?? null;
    }

    /**
     * Determine if any of the specified keys are present in the request
     *
     * @param string $keys
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
     * @param array $defaults
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
     * @param bool $includeStrings
     * @param bool $includeArrays
     * @param bool $includeWhitespace
     * @return static
     */
    public function nullifyBlanks(bool $includeStrings = true, bool $includeArrays = false, bool $includeWhitespace = true): static
    {
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
        $this->input = [];

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
     * Transform input values based on a map of keys and callbacks
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
     * Cleanse input data based on a set of transformation rules
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
     * Retrieve a value from a multidimensional array using dot notation
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
     * Set a value in a multidimensional array using dot notation
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
