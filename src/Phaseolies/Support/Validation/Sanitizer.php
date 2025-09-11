<?php

namespace Phaseolies\Support\Validation;

use Phaseolies\Http\Support\ValidationRules;

class Sanitizer
{
    use ValidationRules;

    protected $data;

    protected $rules;

    protected $errors = [];

    /**
     * Create a new Sanitizer instance.
     *
     * @param array $data The input data to validate.
     * @param array $rules The validation rules.
     */
    public function __construct(array $data, array $rules)
    {
        $this->data = $data;
        $this->rules = $rules;
    }

    /**
     * Create a new Validator instance statically.
     *
     * @param array $data The input data to validate.
     * @param array $rules The validation rules.
     * @return self
     */
    public function request(array $data, array $rules): self
    {
        return new self($data, $rules);
    }

    /**
     * Validate the data against the rules.
     *
     * @return bool
     */
    public function validate(): bool
    {
        foreach ($this->rules as $field => $ruleString) {
            $rulesArray = explode('|', $ruleString);

            foreach ($rulesArray as $rule) {
                $this->applyRule($field, $rule);
            }
        }

        return empty($this->errors);
    }

    /**
     * Check if validation fails.
     *
     * @return bool
     */
    public function fails(): bool
    {
        return !$this->validate();
    }

    /**
     * Get the validation errors.
     *
     * @return array
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Get the validated passed data.
     *
     * @return array
     */
    public function passed(): array
    {
        $passed = [];

        foreach ($this->rules as $field => $ruleString) {
            if (isset($this->data[$field])) {
                $passed[$field] = $this->data[$field];
            }
        }

        return $passed;
    }

    /**
     * Apply a single validation rule to a field.
     *
     * @param string $field
     * @param string $rule
     */
    protected function applyRule(string $field, string $rule)
    {
        $value = $this->data[$field] ?? null;

        // Handle rules with parameters (e.g., min:2, max:100)
        if (str_contains($rule, ':')) {
            [$rule, $param] = explode(':', $rule);
        }

        $errorMessage = $this->sanitizeUserRequest($this->data, $field, $rule, $param ?? null);

        if ($errorMessage) {
            $this->addError($field, $errorMessage);
        }
    }

    /**
     * Add an error message for a field.
     *
     * @param string $field
     * @param string $message
     */
    protected function addError(string $field, string $message)
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }

        $this->errors[$field][] = $message;
    }
}
