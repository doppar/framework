<?php

namespace Phaseolies\Support\Validation;

use Phaseolies\Http\Support\ValidationRules;
use Phaseolies\Http\Validation\Bind;

class Sanitizer
{
    use ValidationRules;

    /**
     * The raw input data to be validated.
     *
     * @var array
     */
    protected $data;

    /**
     * The validation rules to apply on the data.
     *
     * @var array
     */
    protected $rules;

    /**
     * The array of validation error messages.
     *
     * @var array<string, string[]>
     */
    protected $errors = [];

    /**
     * Default error message
     *
     * @var string|null
     */
    protected ?string $message = null;

    /**
     * Create a new Sanitizer instance.
     *
     * @param array $data
     * @param array $rules
     */
    public function __construct(array $data, array $rules)
    {
        $this->data = $data;
        $this->rules = $rules;
    }

    /**
     * Create a new Validator instance statically.
     *
     * @param array $data
     * @param array $rules
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
            // Custom rule class bound via Bind::to(new Rule())->context([...])
            if ($ruleString instanceof Bind) {
                $errorMessage = $ruleString->evaluate($field, $this->data);
                if ($errorMessage) {
                    $this->addError($field, $errorMessage);
                }
                continue;
            }

            $rulesArray = explode('|', $ruleString);

            foreach ($rulesArray as $rule) {
                $this->applyRule($field, $rule);
            }
        }

        if (!empty($this->errors)) {
            $this->message = trans('validation.default');
        } else {
            $this->message = null;
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
        return [
            'message' => $this->message,
            'errors' => $this->errors
        ];
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

        // Handle rules with parameters (e.g., min:2, date_format:Y-m-d H:i:s)
        // Limit to 2 parts so values containing ':' (e.g., datetime formats) are preserved.
        if (str_contains($rule, ':')) {
            [$rule, $param] = explode(':', $rule, 2);
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
