<?php

namespace Phaseolies\Http\Controllers;

use RuntimeException;
use Phaseolies\Support\View\View;
use Phaseolies\Support\Odo\OdoCache;
use Phaseolies\Support\Odo\OdoDirectives;
use Phaseolies\Support\Odo\OdoCondition;
use Phaseolies\Support\Odo\OdoCompiler;
use Phaseolies\Http\Exceptions\NotFoundHttpException;
use Countable;
use Throwable;

class Controller extends View
{
    use OdoCache, OdoCompiler, OdoDirectives, OdoCondition;

    /**
     * @var array
     */
    protected $loopStacks = [];

    /**
     * @var int
     */
    protected $emptyCounter = 0;

    /**
     * @var bool
     */
    protected $firstCaseSwitch = true;

    /**
     * Directive prefix (default: '#')
     *
     * @var string
     */
    protected $directivePrefix = '#';

    /**
     * Opening tag for echo statements (default: '[[')
     *
     * @var string
     */
    protected $openEchoTag = '[[';

    /**
     * Closing tag for echo statements (default: ']]')
     *
     * @var string
     */
    protected $closeEchoTag = ']]';

    /**
     * Opening tag for raw echo statements (default: '[[!')
     *
     * @var string
     */
    protected $openRawEchoTag = '[[!';

    /**
     * Closing tag for raw echo statements (default: '!]]')
     *
     * @var string
     */
    protected $closeRawEchoTag = '!]]';

    /**
     * Opening tag for escaped echo statements (default: '[[[')
     *
     * @var string
     */
    protected $openEscapedEchoTag = '[[[';

    /**
     * Closing tag for escaped echo statements (default: ']]]')
     *
     * @var string
     */
    protected $closeEscapedEchoTag = ']]]';

    /**
     * Opening tag for comments (default: '[[--')
     *
     * @var string
     */
    protected $openCommentTag = '[[--';

    /**
     * Closing tag for comments (default: '--]]')
     *
     * @var string
     */
    protected $closeCommentTag = '--]]';

    /**
     * Maximum allowed nested loops to prevent stack overflow
     *
     * @var int
     */
    protected const MAX_LOOP_DEPTH = 100;

    /**
     * Maximum compilation retries on file lock conflicts
     *
     * @var int
     */
    protected const MAX_COMPILE_RETRIES = 3;

    /**
     * Constructor to initialize the template engine with default settings
     */
    public function __construct()
    {
        parent::__construct();

        // Set the file extension for template files (changed to .odo.php)
        $this->setFileExtension('.odo.php');

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
        $this->loopStacks = [];

        // Load custom syntax from config if available
        $this->loadCustomSyntax();
    }

    /**
     * Load custom syntax configuration
     *
     * @return void
     */
    protected function loadCustomSyntax(): void
    {
        $this->directivePrefix = config('odo.directive_prefix', '#');
        $this->openEchoTag = config('odo.open_echo', '[[');
        $this->closeEchoTag = config('odo.close_echo', ']]');
        $this->openRawEchoTag = config('odo.open_raw_echo', '[[!');
        $this->closeRawEchoTag = config('odo.close_raw_echo', '!]]');
        $this->openEscapedEchoTag = config('odo.open_escaped_echo', '[[[');
        $this->closeEscapedEchoTag = config('odo.close_escaped_echo', ']]]');
        $this->openCommentTag = config('odo.open_comment', '[[--');
        $this->closeCommentTag = config('odo.close_comment', '--]]');
    }

    /**
     * Set custom directive prefix
     *
     * @param string $prefix
     * @return void
     */
    public function setDirectivePrefix(string $prefix): void
    {
        if (strlen($prefix) !== 1) {
            throw new \InvalidArgumentException('Directive prefix must be a single character');
        }
        $this->directivePrefix = $prefix;
    }

    /**
     * Set custom echo tags
     *
     * @param string $open
     * @param string $close
     * @return void
     */
    public function setEchoTags(string $open, string $close): void
    {
        $this->openEchoTag = $open;
        $this->closeEchoTag = $close;
    }

    /**
     * Set custom raw echo tags
     *
     * @param string $open
     * @param string $close
     * @return void
     */
    public function setRawEchoTags(string $open, string $close): void
    {
        $this->openRawEchoTag = $open;
        $this->closeRawEchoTag = $close;
    }

    /**
     * Set custom escaped echo tags
     *
     * @param string $open
     * @param string $close
     * @return void
     */
    public function setEscapedEchoTags(string $open, string $close): void
    {
        $this->openEscapedEchoTag = $open;
        $this->closeEscapedEchoTag = $close;
    }

    /**
     * Set custom comment tags
     *
     * @param string $open
     * @param string $close
     * @return void
     */
    public function setCommentTags(string $open, string $close): void
    {
        $this->openCommentTag = $open;
        $this->closeCommentTag = $close;
    }

    /**
     * Get the current directive prefix
     *
     * @return string
     */
    public function getDirectivePrefix(): string
    {
        return $this->directivePrefix;
    }

    /**
     * Set file extension for the view files - default '.odo.php'.
     *
     * @param string $extension
     * @return void
     */
    public function setFileExtension(string $extension): void
    {
        $this->fileExtension = $extension;
    }

    /**
     * Set view folder location
     * Default to: './views'.
     *
     * @param string $path
     * @return void
     */
    public function setViewFolder(string $path): void
    {
        $this->viewFolder = str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    /**
     * Set echo format
     * Default to: '$this->e($data)'.
     *
     * @param string $format
     * @return void
     */
    public function setEchoFormat(string $format): void
    {
        $this->echoFormat = $format;
    }

    /**
     * Pop a loop from the stack.
     *
     * @return void
     */
    public function popLoop(): void
    {
        if (!empty($this->loopStacks)) {
            array_pop($this->loopStacks);
        }
    }

    /**
     * Handle application view file
     *
     * @param string $view
     * @return string
     * @throws NotFoundHttpException
     * @throws RuntimeException
     */
    protected function findView(string $view): string
    {
        // Handling namespaced views (e.g., 'namespace::view.name')
        // Check for '::' to identify namespaced views
        // Split into namespace and view name
        // Search in published views first, then in package views
        if (str_contains($view, '::')) {
            return $this->findNamespacedView($view);
        }

        // Regular views
        // Search in the main views directory
        return $this->findRegularView($view);
    }

    /**
     * Find a namespaced view file
     *
     * @param string $view
     * @return string
     * @throws NotFoundHttpException
     * @throws RuntimeException
     */
    protected function findNamespacedView(string $view): string
    {
        [$namespace, $viewName] = explode('::', $view, 2);
        if (empty($this->factory->namespaces[$namespace])) {
            throw new RuntimeException("Namespace [{$namespace}] not registered");
        }

        $viewPath = str_replace('.', DIRECTORY_SEPARATOR, $viewName);

        // First check published views in resources/views/vendor/{namespace}
        // Prioritize published views over package views
        $publishedPath = base_path('resources/views/vendor/' . $namespace);
        if (is_dir($publishedPath)) {
            $possiblePaths = [
                $publishedPath . DIRECTORY_SEPARATOR . $viewPath . $this->fileExtension,
                $publishedPath . DIRECTORY_SEPARATOR . $viewPath . '.odo.php',
                $publishedPath . DIRECTORY_SEPARATOR . $viewPath . '.php',
            ];

            foreach ($possiblePaths as $fullPath) {
                if (file_exists($fullPath)) {
                    return $fullPath;
                }
            }
        }

        // Then check package views
        foreach ($this->factory->namespaces[$namespace] as $basePath) {
            $possiblePaths = [
                $basePath . DIRECTORY_SEPARATOR . $viewPath . $this->fileExtension
            ];

            foreach ($possiblePaths as $fullPath) {
                if (file_exists($fullPath)) {
                    return $fullPath;
                }
            }
        }


        throw new NotFoundHttpException("View [{$view}] not found in namespace [{$namespace}]");
    }

    /**
     * Find a regular (non-namespaced) view file
     *
     * @param string $view
     * @return string
     * @throws NotFoundHttpException
     */
    protected function findRegularView(string $view): string
    {
        $viewPath = str_replace('.', DIRECTORY_SEPARATOR, $view);
        $basePath = base_path($this->viewFolder);

        $possiblePaths = [
            $basePath . DIRECTORY_SEPARATOR . $viewPath . $this->fileExtension
        ];

        foreach ($possiblePaths as $fullPath) {
            if (file_exists($fullPath) && is_readable($fullPath)) {
                return $fullPath;
            }
        }

        $attemptedPaths = implode("\n  - ", $possiblePaths);

        throw new NotFoundHttpException(
            "View [{$view}] not found. Attempted paths:\n  - {$attemptedPaths}"
        );
    }

    /**
     * Prepare the view file (locate and compile).
     *
     * @param string $view
     * @return string
     * @throws RuntimeException
     */
    public function prepare(string $view): string
    {
        try {
            $actual = $this->findView($view);
            $viewKey = str_replace(['/', '\\', DIRECTORY_SEPARATOR], '.', $view);

            // stronger hash to avoid collisions
            $hash = hash('xxh128', $viewKey);
            $cache = base_path($this->cacheFolder) . DIRECTORY_SEPARATOR . $viewKey . '__' . $hash . '.php';

            $needsRecompile = $this->needsRecompilation($cache, $actual);

            if ($needsRecompile) {
                $this->compileAndCache($actual, $cache);
            }

            return $cache;
        } catch (NotFoundHttpException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new RuntimeException("Failed to prepare view [{$view}]: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Check if view needs recompilation
     *
     * @param string $cache
     * @param string $actual
     * @return bool
     */
    protected function needsRecompilation(string $cache, string $actual): bool
    {
        if (!is_file($cache)) {
            return true;
        }

        // Check if source file is newer than cache
        clearstatcache(true, $cache);
        clearstatcache(true, $actual);

        return filemtime($actual) > filemtime($cache);
    }

    /**
     * Compile view and write to cache with atomic operation
     *
     * @param string $actual
     * @param string $cache
     * @return void
     * @throws RuntimeException
     */
    protected function compileAndCache(string $actual, string $cache): void
    {
        $retries = 0;
        $lastException = null;

        while ($retries < self::MAX_COMPILE_RETRIES) {
            try {
                $content = $this->compileView($actual);

                // Atomic write using temp file + rename
                $tempFile = $cache . '.' . uniqid('odo_', true) . '.tmp';

                // Write to temp file with exclusive lock
                $written = file_put_contents($tempFile, $content, LOCK_EX);

                if ($written === false) {
                    throw new RuntimeException("Failed to write compiled view to temp file: {$tempFile}");
                }

                // Make sure the file has proper permissions
                @chmod($tempFile, 0644);

                // Atomic rename (on Unix systems)
                if (!rename($tempFile, $cache)) {
                    @unlink($tempFile);
                    throw new RuntimeException("Failed to move compiled view to cache: {$cache}");
                }

                // Verify the cache file was created successfully
                if (!file_exists($cache)) {
                    throw new RuntimeException("Cache file was not created: {$cache}");
                }

                return;
            } catch (Throwable $e) {
                $lastException = $e;
                $retries++;

                // Clean up temp file if it exists
                if (isset($tempFile) && file_exists($tempFile)) {
                    @unlink($tempFile);
                }

                // Small delay before retry to avoid race conditions
                if ($retries < self::MAX_COMPILE_RETRIES) {
                    usleep(10000 * $retries); // 10ms, 20ms, 30ms
                }
            }
        }

        throw new RuntimeException(
            "Failed to compile view after " . self::MAX_COMPILE_RETRIES . " attempts: " .
                ($lastException ? $lastException->getMessage() : 'Unknown error')
        );
    }

    /**
     * Compile view content
     *
     * @param string $actual
     * @return string
     * @throws RuntimeException
     */
    protected function compileView(string $actual): string
    {
        if (!is_file($actual)) {
            throw new RuntimeException("View file not found: {$actual}");
        }

        if (!is_readable($actual)) {
            throw new RuntimeException("View file is not readable: {$actual}");
        }

        $content = @file_get_contents($actual);

        if ($content === false) {
            throw new RuntimeException("Failed to read view file: {$actual}");
        }

        try {
            // Add custom #set() directive
            $this->extend(function ($value) {
                $prefix = preg_quote($this->directivePrefix, '/');
                return preg_replace("/{$prefix}set\(['\"](.*?)['\"]\,(.*)\)/", '<?php $$1 =$2; ?>', $value);
            });

            // Compile in order: Statements, Comments, Echos, Extensions
            $compilers = ['Statements', 'Comments', 'Echos', 'Extensions'];

            foreach ($compilers as $compiler) {
                $method = 'compile' . $compiler;
                if (method_exists($this, $method)) {
                    $content = $this->{$method}($content);
                }
            }

            // Replace PHP blocks (e.g., #php ... #endphp)
            $content = $this->replacePhpBlocks($content);

            return $content;
        } catch (Throwable $e) {
            throw new RuntimeException(
                "Compilation failed for view [{$actual}]: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Add new loop to the stack.
     *
     * @param mixed $data
     * @return void
     * @throws RuntimeException
     */
    public function addLoop($data): void
    {
        // Prevent infinite loop stack growth
        if (count($this->loopStacks) >= self::MAX_LOOP_DEPTH) {
            throw new RuntimeException(
                "Maximum loop nesting depth of " . self::MAX_LOOP_DEPTH . " exceeded. " .
                    "Possible infinite loop or excessive nesting detected."
            );
        }

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
        if (empty($this->loopStacks)) {
            return;
        }

        $loop = &$this->loopStacks[count($this->loopStacks) - 1];
        $loop['iteration']++;
        $loop['index'] = $loop['iteration'] - 1;
        $loop['first'] = ($loop['iteration'] === 1);

        if (isset($loop['count'])) {
            $loop['remaining']--;
            $loop['last'] = ($loop['iteration'] === (int)$loop['count']);
        }
    }

    /**
     * Get an instance of the current loop in the stack.
     *
     * @return \stdClass|null
     */
    public function getFirstLoop(): ?\stdClass
    {
        if (empty($this->loopStacks)) {
            return null;
        }

        $last = end($this->loopStacks);
        return $last ? (object) $last : null;
    }

    /**
     * Get the current loop depth
     *
     * @return int
     */
    public function getLoopDepth(): int
    {
        return count($this->loopStacks);
    }

    /**
     * Clear all loop stacks (useful for testing or cleanup)
     *
     * @return void
     */
    public function clearLoopStacks(): void
    {
        $this->loopStacks = [];
        $this->emptyCounter = 0;
        $this->firstCaseSwitch = true;
    }

    /**
     * Get compilation statistics
     *
     * @return array
     */
    public function getCompilationStats(): array
    {
        return [
            'loop_depth' => count($this->loopStacks),
            'empty_counter' => $this->emptyCounter,
            'cache_folder' => $this->cacheFolder,
            'view_folder' => $this->viewFolder,
            'file_extension' => $this->fileExtension,
            'directive_prefix' => $this->directivePrefix,
            'echo_tags' => [
                'open' => $this->openEchoTag,
                'close' => $this->closeEchoTag,
            ],
        ];
    }
}
