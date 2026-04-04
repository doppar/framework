<?php

namespace Phaseolies\Http\Validation;

use Phaseolies\Http\Validation\Contracts\RuleInterface;

class Bind
{
    /**
     * The custom rule instance to be evaluated.
     *
     * @var RuleInterface
     */
    private RuleInterface $rule;

    /**
     * Conditions that must ALL be satisfied for the rule to run
     *
     * @var array<string, mixed>
     */
    private array $conditions = [];

    /**
     * @param RuleInterface $rule
     */
    private function __construct(RuleInterface $rule)
    {
        $this->rule = $rule;
    }

    /**
     * Bind a custom rule class to the validation pipeline.
     *
     * @param RuleInterface $rule
     * @return static
     */
    public static function to(RuleInterface $rule): static
    {
        return new static($rule);
    }

    /**
     * Set conditions that must ALL be present in the request input for this rule to be evaluated
     *
     * @param array<string, mixed> $conditions
     * @return static
     */
    public function context(array $conditions): static
    {
        $this->conditions = $conditions;

        return $this;
    }

    /**
     * Evaluate the bound rule against the full request input
     *
     * @param string $field
     * @param array $input
     * @return string|null
     */
    public function evaluate(string $field, array $input): ?string
    {
        // Skip rule when any context condition is not satisfied
        foreach ($this->conditions as $contextField => $expectedValue) {
            if (($input[$contextField] ?? null) !== $expectedValue) {
                return null;
            }
        }

        $value = $input[$field] ?? null;

        if (!$this->rule->passes($field, $value, $input)) {
            return $this->rule->message($field);
        }

        return null;
    }
}
