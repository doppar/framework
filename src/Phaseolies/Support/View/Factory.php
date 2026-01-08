<?php

namespace Phaseolies\Support\View;

class Factory
{
    /**
     * An associative array of namespace => paths.
     *
     * @var array
     */
    protected $namespaces = [];

    /**
     * Register a namespace with its corresponding paths.
     *
     * @param string $namespace
     * @param string|array $paths
     * @return void
     */
    public function addNamespace(string $namespace, $paths): void
    {
        $this->namespaces[$namespace] = (array) $paths;
    }

    /**
     * Retrieve the paths associated with a given namespace.
     *
     * @param string $namespace
     * @return array
     */
    public function getNamespacePaths(string $namespace): array
    {
        return $this->namespaces[$namespace] ?? [];
    }

    /**
     * Determine whether a namespace has been registered.
     *
     * @param string $namespace.
     * @return bool
     */
    public function hasNamespace(string $namespace): bool
    {
        return isset($this->namespaces[$namespace]);
    }
}
