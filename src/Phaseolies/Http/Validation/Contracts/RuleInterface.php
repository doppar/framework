<?php

namespace Phaseolies\Http\Validation\Contracts;

interface RuleInterface
{
    /**
     * Determine if the validation rule passes.
     *
     * @param string $field
     * @param mixed $value
     * @param array $input
     * @return bool
     */
    public function passes(string $field, mixed $value, array $input): bool;

    /**
     * Return the validation error message when the rule fails.
     *
     * @param string $field
     * @return string
     */
    public function message(string $field): string;
}
