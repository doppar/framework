<?php

namespace Phaseolies\Database\Migration;

class IndexDefinition
{
    /**
     * Create a new table level index definition.
     *
     * @param string $type index|unique|fulltext|spatial|primary
     * @param array $columns
     * @param string|null $name
     * @param string|null $algorithm
     */
    public function __construct(
        public string $type,
        public array $columns,
        public ?string $name = null,
        public ?string $algorithm = null
    ) {
    }

    /**
     * Set a custom name for the index.
     *
     * @param string $name
     * @return self
     */
    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Set the index algorithm (e.g. btree, hash, gin, gist).
     *
     * @param string $algorithm
     * @return self
     */
    public function algorithm(string $algorithm): self
    {
        $this->algorithm = $algorithm;

        return $this;
    }
}
