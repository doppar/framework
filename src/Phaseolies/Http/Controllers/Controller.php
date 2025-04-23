<?php

namespace Phaseolies\Http\Controllers;

use RuntimeException;
use Countable;
use Phaseolies\Support\View\View;
use Phaseolies\Support\Cache\Cache;
use Phaseolies\Support\Blade\BladeCompiler;
use Phaseolies\Support\Blade\BladeCondition;
use Phaseolies\Support\Blade\Directives;

class Controller extends View
{
    use Cache, BladeCompiler, Directives, BladeCondition;

    protected $loopStacks = [];

    protected $emptyCounter = 0;

    protected $firstCaseSwitch = true;

    /**
     * Constructor to initialize the template engine with default settings
     */
    public function __construct()
    {
        parent::__construct();

        // Set the file extension for template files
        $this->setFileExtension('.blade.php');

        // Set the directory where view files are stored
        $this->setViewFolder('resources/views' . DIRECTORY_SEPARATOR);

        // Set the directory where cached files are stored
        $this->setCacheFolder('storage/framework/views' . DIRECTORY_SEPARATOR);

        // Create the cache folder if it doesn't exist
        $this->createCacheFolder();

        // Set the directory where cached files are stored
        $this->setSymlinkPathFolder('storage/app/public' . DIRECTORY_SEPARATOR);

        // Create the cache folder if it doesn't exist
        $this->createPublicSymlinkFolder();

        // Set the format for echoing variables in templates
        $this->setEchoFormat('$this->e(%s)');

        // Initialize arrays for blocks, block stacks, and loop stacks
        $this->blocks = [];
        $this->blockStacks = [];
        $this->loopStacks = [];
    }

    /**
     * Set file extension for the view files
     * Default to: '.blade.php'.
     *
     * @param string $extension
     */
    public function setFileExtension($extension): void
    {
        $this->fileExtension = $extension;
    }

    /**
     * Set view folder location
     * Default to: './views'.
     *
     * @param string $value
     */
    public function setViewFolder($path): void
    {
        $this->viewFolder = str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    /**
     * Set echo format
     * Default to: '$this->e($data)'.
     *
     * @param string $format
     */
    public function setEchoFormat($format): void
    {
        $this->echoFormat = $format;
    }

    /**
     * Handle application view file
     *
     * @param string $view
     * @return string
     */
    protected function findView(string $view): string
    {
        if (str_contains($view, '::')) {
            [$namespace, $viewName] = explode('::', $view, 2);
            if (empty($this->factory->namespaces[$namespace])) {
                throw new RuntimeException("Namespace [{$namespace}] not registered");
            }

            $viewPath = str_replace('.', DIRECTORY_SEPARATOR, $viewName);

            foreach ($this->factory->namespaces[$namespace] as $basePath) {
                $possiblePaths = [
                    $basePath . DIRECTORY_SEPARATOR . $viewPath . $this->fileExtension,
                    $basePath . DIRECTORY_SEPARATOR . $viewPath . '.blade.php',
                    $basePath . DIRECTORY_SEPARATOR . $viewPath . '.php',
                ];

                foreach ($possiblePaths as $fullPath) {
                    if (file_exists($fullPath)) {
                        return $fullPath;
                    }else{
                        throw new \RuntimeException("View [{$fullPath}] not found in namespace [{$namespace}]");
                    }
                }
            }

            throw new \RuntimeException("View [{$view}] not found in namespace [{$namespace}]");
        }

        $viewPath = str_replace('.', DIRECTORY_SEPARATOR, $view);
        $fullPath = base_path($this->viewFolder) . DIRECTORY_SEPARATOR .
            $viewPath . $this->fileExtension;

        if (!file_exists($fullPath)) {
            throw new \RuntimeException("View [{$view}] not found");
        }

        return $fullPath;
    }

    /**
     * Prepare the view file (locate and extract).
     *
     * @param string $view
     */
    public function prepare($view): string
    {
        $actual = $this->findView($view);
        $viewKey = str_replace(['/', '\\', DIRECTORY_SEPARATOR], '.', $view);
        $cache = base_path($this->cacheFolder) . DIRECTORY_SEPARATOR . $viewKey . '__' . sprintf('%u', crc32($viewKey)) . '.php';

        if (!is_file($cache) || filemtime($actual) > filemtime($cache)) {
            if (!is_file($actual)) {
                throw new RuntimeException('View not found: ' . $actual);
            }

            $content = file_get_contents($actual);
            // Add @set() directive using extend() method, we need 2 parameters here
            $this->extend(function ($value) {
                return preg_replace("/@set\(['\"](.*?)['\"]\,(.*)\)/", '<?php $$1 =$2; ?>', $value);
            });

            $compilers = ['Statements', 'Comments', 'Echos', 'Extensions'];

            foreach ($compilers as $compiler) {
                $content = $this->{'compile' . $compiler}($content);
            }

            // Replace @php and @endphp blocks
            $content = $this->replacePhpBlocks($content);

            file_put_contents($cache, $content);
        }

        return $cache;
    }

    /**
     * Add new loop to the stack.
     *
     * @param mixed $data
     */
    public function addLoop($data): void
    {
        $length = (is_array($data) || $data instanceof Countable) ? count($data) : null;
        $parent = empty($this->loopStacks) ? null : end($this->loopStacks);
        $this->loopStacks[] = [
            'iteration' => 0,
            'index' => 0,
            'remaining' => isset($length) ? $length : null,
            'count' => $length,
            'first' => true,
            'last' => isset($length) ? ($length === 1) : null,
            'depth' => count($this->loopStacks) + 1,
            'parent' => $parent ? (object) $parent : null,
        ];
    }

    /**
     * Increment the top loop's indices.
     *
     * @return void
     */
    public function incrementLoopIndices(): void
    {
        $loop = &$this->loopStacks[count($this->loopStacks) - 1];
        $loop['iteration']++;
        $loop['index'] = $loop['iteration'] - 1;
        $loop['first'] = ((int) $loop['iteration'] === 1);

        if (isset($loop['count'])) {
            $loop['remaining']--;
            $loop['last'] = ((int) $loop['iteration'] === (int) $loop['count']);
        }
    }

    /**
     * Get an instance of the first loop in the stack.
     *
     * @return \stdClass|null
     */
    public function getFirstLoop(): \stdClass|null
    {
        return ($last = end($this->loopStacks)) ? (object) $last : null;
    }
}
