<?php

namespace Phaseolies\Support\View;

class View extends Factory
{
    /**
     * Rendered content for named blocks.
     *
     * @var array<string, string>
     */
    protected $blocks = [];

    /**
     * Stack of currently open block names used during rendering.
     * Ensures correct pairing of begin/end block calls.
     *
     * @var array<int, string>
     */
    protected $blockStacks = [];

    /**
     * List of parent templates to be rendered (via @extends).
     * Populated in reverse order and consumed during fetch().
     *
     * @var array<int, string>
     */
    protected $parents = [];

    /**
     * Reference to the underlying view factory, typically the framework’s main view engine.
     *
     * @var \Phaseolies\Support\View\Factory
     */
    protected $factory;

    /**
     * In-memory cache of rendered views keyed by view name and input data.
     * Used to prevent redundant rendering within a single request.
     *
     * @var array<string, string>
     */
    protected static array $cache = [];

    /**
     * Stack of currently rendering view names.
     * Useful for debugging and internal state tracking.
     *
     * @var array<int, string>
     */
    protected $renderStack = [];

    public function __construct()
    {
        $this->factory = app('view');
    }

    /**
     * Render the view template.
     *
     * @param string $name
     * @param array $data
     * @param bool $returnOnly
     * @return string
     */
    public function render($name, array $data = [], $returnOnly = false)
    {
        $html = $this->fetch($name, $data);

        return $returnOnly ? $html : print($html);
    }

    /**
     * Fetch the view data passed by user.
     *
     * @param string $view
     * @param array  $data
     */
    public function fetch($name, array $data = []): string
    {
        if (!is_string($name) || empty($name)) {
            throw new \InvalidArgumentException('View name must be a non-empty string');
        }

        $cacheKey = $this->generateCacheKey($name, $data);

        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        $this->renderStack[] = $name;

        try {
            $this->parents[] = $name;
            extract($data, EXTR_SKIP);

            while ($template = array_pop($this->parents)) {
                $this->beginBlock('__current_template__');
                require $this->prepare($template);
                $this->endBlock(true);
            }

            $result = $this->block('__current_template__', '');

            return self::$cache[$cacheKey] = $result;
        } finally {
            if (!empty($this->renderStack)) {
                array_pop($this->renderStack);
            }
        }
    }

    /**
     * Helper method for #extends() directive to define parent view.
     *
     * @param string $name
     * @return void
     */
    protected function addParent($name): void
    {
        array_unshift($this->parents, $name);
    }

    /**
     * Return content of block if exists.
     *
     * @param string $name
     * @param mixed  $default
     * @return string
     */
    protected function block($name, $default = ''): string
    {
        return $this->blocks[$name] ?? $default;
    }

    /**
     * Start a block.
     *
     * @param string $name
     * @return void
     */
    protected function beginBlock($name): void
    {
        if (!is_string($name) || strlen(trim($name)) === 0) {
            throw new \InvalidArgumentException(
                'Block name must be a non-empty string. Received: ' . var_export($name, true)
            );
        }

        // Buffer leaks protection
        if (ob_get_level() > 50) {
            throw new \RuntimeException('Too many nested output buffers');
        }

        array_push($this->blockStacks, $name);

        ob_start();
    }

    /**
     * Ends a block.
     *
     * @param bool $overwrite
     * @return string
     */
    protected function endBlock($overwrite = false): string
    {
        if (empty($this->blockStacks)) {
            throw new \RuntimeException(sprintf(
                'No blocks to end. Current blocks: %s, Render stack: %s',
                json_encode(array_keys($this->blocks)),
                json_encode($this->renderStack)
            ));
        }

        $name = array_pop($this->blockStacks);

        $content = ob_get_clean();

        if ($overwrite || !isset($this->blocks[$name])) {
            $this->blocks[$name] = $content;
        } else {
            $this->blocks[$name] .= $content;
        }

        return $name;
    }

    /**
     * Generate the hash key
     *
     * @param mixed $name
     * @param mixed $payload
     * @return string
     */
    protected function generateCacheKey($name, $payload): string
    {
        $hasher = hash_init('xxh128');

        hash_update($hasher, (string) $name);

        $normalized = $this->normalizeForHash($payload);

        hash_update($hasher, serialize($normalized));

        return hash_final($hasher);
    }

    /**
     * Normalize a value into a serialization-safe structure for hashing.
     *
     * @param mixed $value
     * @return mixed
     */
    private function normalizeForHash($value)
    {
        if ($value instanceof \Closure) {
            return 'closure';
        }

        if (is_array($value)) {
            $normalized = [];

            foreach ($value as $k => $v) {
                $normalized[$k] = $this->normalizeForHash($v);
            }

            if ($this->isAssoc($normalized)) {
                ksort($normalized);
            }

            return $normalized;
        }

        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return (string) $value;
            }

            return ['__class' => get_class($value)];
        }

        if (is_resource($value)) {
            return 'resource:' . get_resource_type($value);
        }

        return $value;
    }

    /**
     * Determine if an array is associative.
     *
     * @param array $array
     * @return bool
     */
    private function isAssoc(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Clean the block state
     *
     * @return void
     */
    public function flush()
    {
        $this->blocks = [];
        $this->blockStacks = [];
        $this->parents = [];
    }

    public function __destruct()
    {
        $this->flush();
    }
}
