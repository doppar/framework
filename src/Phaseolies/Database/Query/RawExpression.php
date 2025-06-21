<?php

namespace Phaseolies\Database\Query;

class RawExpression
{
    /**
     * The raw SQL expression string.
     *
     * @var string
     */
    protected string $value;

    /**
     * The parameter bindings for the raw expression.
     *
     * @var array
     */
    protected array $bindings = [];

    /**
     * Create a new raw SQL expression instance.
     *
     * @param string $value The raw SQL string to be included in queries
     * @param array $bindings The parameter bindings for the SQL expression
     */
    public function __construct(string $value, array $bindings = [])
    {
        $this->value = $value;
        $this->bindings = $bindings;
    }

    /**
     * Get the raw SQL expression value.
     *
     * @return string The raw SQL string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Get the parameter bindings for the expression.
     *
     * @return array The parameter bindings
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Convert the expression to its string representation.
     *
     * @return string The raw SQL string
     */
    public function __toString(): string
    {
        return $this->value;
    }
}
