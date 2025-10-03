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
     * @param string $namespace The name of the namespace.
     * @param string|array $paths One or more paths to associate with the namespace.
     * @return void
     */
    public function addNamespace(string $namespace, $paths): void
    {
        $this->namespaces[$namespace] = (array) $paths;
    }

    /**
     * Retrieve the paths associated with a given namespace.
     *
     * @param string $namespace The namespace to look up.
     * @return array The array of paths associated with the namespace, or an empty array if none are found.
     */
    public function getNamespacePaths(string $namespace): array
    {
        return $this->namespaces[$namespace] ?? [];
    }

    /**
     * Determine whether a namespace has been registered.
     *
     * @param string $namespace The namespace to check.
     * @return bool True if the namespace exists, false otherwise.
     */
    public function hasNamespace(string $namespace): bool
    {
        return isset($this->namespaces[$namespace]);
    }
}
