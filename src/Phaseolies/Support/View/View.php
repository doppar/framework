<?php

namespace Phaseolies\Support\View;

class View extends Factory
{
    protected $blocks = [];

    protected $blockStacks = [];

    protected $templates = [];

    protected $factory;

    public function __construct()
    {
        $this->factory = app('view');
    }

    /**
     * Render the view template.
     *
     * @param string $name
     * @param array  $data
     * @param bool   $returnOnly
     *
     * @return string
     */
    public function render($name, array $data = [], $returnOnly = false)
    {
        $html = $this->fetch($name, $data);

        if (false !== $returnOnly) {
            return $html;
        }

        echo $html;
    }

    /**
     * Fetch the view data passed by user.
     *
     * @param string $view
     * @param array  $data
     */
    public function fetch($name, array $data = []): string
    {
        $this->templates[] = $name;

        if (!empty($data)) {
            extract($data);
        }

        while ($templates = array_shift($this->templates)) {
            $this->beginBlock('content');
            require $this->prepare($templates);
            $this->endBlock(true);
        }

        return $this->block('content');
    }

    /**
     * Helper method for @extends() directive to define parent view.
     *
     * @param string $name
     */
    protected function addParent($name): void
    {
        $this->templates[] = $name;
    }

    /**
     * Return content of block if exists.
     *
     * @param string $name
     * @param mixed  $default
     *
     * @return string
     */
    protected function block($name, $default = ''): string
    {
        return array_key_exists($name, $this->blocks) ? $this->blocks[$name] : $default;
    }

    /**
     * Start a block.
     *
     * @param string $name
     *
     * @return void
     */
    protected function beginBlock($name): void
    {
        array_push($this->blockStacks, $name);
        ob_start();
    }

    /**
     * Ends a block.
     *
     * @param bool $overwrite
     *
     * @return mixed
     */
    protected function endBlock($overwrite = false): mixed
    {
        $name = array_pop($this->blockStacks);

        if ($overwrite || !array_key_exists($name, $this->blocks)) {
            $this->blocks[$name] = ob_get_clean();
        } else {
            $this->blocks[$name] .= ob_get_clean();
        }

        return $name;
    }
}
