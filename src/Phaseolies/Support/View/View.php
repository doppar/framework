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
     *
     * @var array<int, string>
     */
    protected $renderStack = [];

    /**
     * Named stacks for inject/slot system
     *
     * @var array<string, array>
     */
    protected array $stacks = [];

    /**
     * Currently open inject stack name
     *
     * @var string|null
     */
    protected ?string $currentInject = null;

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

        if ($cacheKey !== null && isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        $this->renderStack[] = $name;

        $savedParents = $this->parents;
        $hadCurrentBlock = array_key_exists('__current_template__', $this->blocks);
        $savedCurrentBlock = $this->blocks['__current_template__'] ?? null;
        $this->parents = [];

        try {
            $this->parents[] = $name;
            $this->renderTemplates($data);

            $result = $this->block('__current_template__', '');

            if ($cacheKey !== null) {
                self::$cache[$cacheKey] = $result;
            }

            return $result;
        } finally {
            $this->parents = $savedParents;

            if ($hadCurrentBlock) {
                $this->blocks['__current_template__'] = $savedCurrentBlock;
            } else {
                unset($this->blocks['__current_template__']);
            }

            if (!empty($this->renderStack)) {
                array_pop($this->renderStack);
            }
        }
    }

    /**
     * Render the view and the layouts it extends with the given data.
     *
     * @param array $data
     * @return void
     */
    protected function renderTemplates(array $data): void
    {
        (function (array $__viewData): void {
            extract($__viewData, EXTR_SKIP);
            unset($__viewData);

            while ($this->parents !== []) {
                $this->beginBlock('__current_template__');
                require $this->prepare(array_pop($this->parents));
                $this->endBlock(true);
            }
        })($data);
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
     * Generate the hash key, or null when the rendered output must not be reused.
     *
     * @param mixed $name
     * @param mixed $payload
     * @return string|null
     */
    protected function generateCacheKey($name, $payload): ?string
    {
        $hasher = hash_init('xxh128');

        hash_update($hasher, (string) $name);

        $cacheable = true;
        $normalized = $this->normalizeForHash($payload, $cacheable);

        if (!$cacheable) {
            return null;
        }

        hash_update($hasher, serialize($normalized));

        return hash_final($hasher);
    }

    /**
     * Normalize a value into a serialization-safe structure for hashing.
     *
     * @param mixed $value
     * @param bool $cacheable
     * @return mixed
     */
    private function normalizeForHash($value, bool &$cacheable)
    {
        if ($value instanceof \Closure) {
            $cacheable = false;

            return 'closure';
        }

        if (is_array($value)) {
            $normalized = [];

            foreach ($value as $k => $v) {
                $normalized[$k] = $this->normalizeForHash($v, $cacheable);
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

            $cacheable = false;

            return ['__class' => get_class($value)];
        }

        if (is_resource($value)) {
            $cacheable = false;

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
     * Start capturing content for a named inject stack
     *
     * @param string $name
     * @return void
     */
    public function startInject(string $name): void
    {
        if (empty($name)) {
            throw new \InvalidArgumentException('Inject stack name must not be empty');
        }

        $this->currentInject = trim($name, "'\"");
        ob_start();
    }

    /**
     * End capturing and push content into the named stack
     *
     * @return void
     */
    public function endInject(): void
    {
        if ($this->currentInject === null) {
            throw new \RuntimeException('Cannot end inject — no inject block is open');
        }

        $content = ob_get_clean();
        $name = $this->currentInject;

        if (!isset($this->stacks[$name])) {
            $this->stacks[$name] = [];
        }

        $this->stacks[$name][] = $content;
        $this->currentInject = null;
    }

    /**
     * Render all content pushed into a named stack
     *
     * @param string $name
     * @return string
     */
    public function renderSlot(string $name): string
    {
        $name = trim($name, "'\"");

        if (!isset($this->stacks[$name])) {
            return '';
        }

        return implode('', $this->stacks[$name]);
    }

    /**
     * Check if a stack has any content
     *
     * @param string $name
     * @return bool
     */
    public function hasSlot(string $name): bool
    {
        $name = trim($name, "'\"");

        return !empty($this->stacks[$name]);
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
        $this->stacks = [];
        $this->currentInject = null;
    }

    public function __destruct()
    {
        $this->flush();
    }
}
