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
     * @var bool
     */
    protected $jitEnabled = true;

    /**
     * 0 = none, 1 = basic, 2 = aggressive
     *
     * @var int
     */
    protected $optimizationLevel = 2;

    /**
     * @var array
     */
    protected $lazyComponents = [];

    /**
     * @var array
     */
    protected $compiledTemplates = [];

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

        $this->jitEnabled = env('BLADE_JIT_ENABLED', true);
        $this->optimizationLevel = env('BLADE_OPTIMIZATION_LEVEL', 2);
        $this->setOptimizationLevel($this->optimizationLevel);
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

            // First check published views in resources/views/vendor/{namespace}
            $publishedPath = base_path('resources/views/vendor/' . $namespace);
            if (is_dir($publishedPath)) {
                $possiblePaths = [
                    $publishedPath . DIRECTORY_SEPARATOR . $viewPath . $this->fileExtension,
                    $publishedPath . DIRECTORY_SEPARATOR . $viewPath . '.blade.php',
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
                    $basePath . DIRECTORY_SEPARATOR . $viewPath . $this->fileExtension,
                    $basePath . DIRECTORY_SEPARATOR . $viewPath . '.blade.php',
                    $basePath . DIRECTORY_SEPARATOR . $viewPath . '.php',
                ];

                foreach ($possiblePaths as $fullPath) {
                    if (file_exists($fullPath)) {
                        return $fullPath;
                    }
                }
            }

            throw new \RuntimeException("View [{$view}] not found in namespace [{$namespace}]");
        }

        // Handle non-namespaced views
        $viewPath = str_replace('.', DIRECTORY_SEPARATOR, $view);
        $fullPath = base_path($this->viewFolder) . DIRECTORY_SEPARATOR .
            $viewPath . $this->fileExtension;

        if (!file_exists($fullPath)) {
            throw new \RuntimeException("View [{$view}] not found");
        }

        return $fullPath;
    }

    /**
     * Set optimization level (0-2)
     *
     * @param int $level
     * @return void
     */
    public function setOptimizationLevel(int $level): void
    {
        if (!in_array($level, [0, 1, 2], true)) {
            throw new \InvalidArgumentException('Optimization level must be 0, 1, or 2');
        }

        $this->optimizationLevel = max(0, min(2, $level));
    }

    /**
     * Prepare the view file (locate and extract).
     *
     * @param string $view
     */
    public function prepare($view, array $data = []): string
    {
        $actual = $this->findView($view);
        $viewKey = str_replace(['/', '\\', DIRECTORY_SEPARATOR], '.', $view);
        $cache = base_path($this->cacheFolder) . DIRECTORY_SEPARATOR . $viewKey . '__' . sprintf('%u', crc32($viewKey)) . '.php';

        $needsRecompile = !is_file($cache) || filemtime($actual) > filemtime($cache);

        if ($needsRecompile) {
            $content = $this->compileView($actual, $data);
            file_put_contents($cache, $content);
        } elseif (!empty($data)) {
            $dataExport = var_export($data, true);
            $content = file_get_contents($cache);
            $content = "<?php extract($dataExport); ?>" . $content;
            file_put_contents($cache, $content);
        }

        // Always apply JIT optimizations if enabled
        if ($this->jitEnabled) {
            $cache = $this->applyJitOptimizations($cache, $viewKey, $needsRecompile);
        }

        return $cache;
    }

    /**
     * Compile view content with standard Blade compilation
     *
     * @param string $actual
     * @param array $data
     * @return string
     */
    protected function compileView(string $actual, array $data): string
    {
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

        if (!empty($data)) {
            $dataExport = var_export($data, true);
            $content = "<?php extract($dataExport); ?>" . $content;
        }

        return $content;
    }

    /**
     * Apply JIT optimizations to compiled template
     *
     * @param string $cachePath
     * @param string $viewKey
     * @param bool $freshContent
     * @return string
     */
    protected function applyJitOptimizations(string $cachePath, string $viewKey, bool $freshContent = false): string
    {
        try {
            // Return already optimized content if available
            if (isset($this->compiledTemplates[$viewKey])) {
                return $this->compiledTemplates[$viewKey];
            }

            $content = file_get_contents($cachePath);
            $optimizedContent = $content;

            // Only optimize if we have fresh content or optimization level requires it
            if ($freshContent || $this->optimizationLevel > 0) {
                // Level 1 optimizations
                if ($this->optimizationLevel >= 1) {
                    $optimizedContent = $this->safeFullMinifyWithJsCssAware($optimizedContent);
                    // $optimizedContent = preg_replace('/\s+/', ' ', $optimizedContent);
                    $optimizedContent = $this->optimizeControlStructures($optimizedContent);
                    $optimizedContent = $this->optimizeEchoStatements($optimizedContent);
                    $optimizedContent = $this->optimizeBladeLoops($optimizedContent);
                }

                // Level 2 optimizations
                if ($this->optimizationLevel >= 2) {
                    $optimizedContent = $this->inlineSmallTemplates($optimizedContent);
                    $optimizedContent = $this->optimizeComplexLoops($optimizedContent);
                    $optimizedContent = $this->lazyLoadComponents($optimizedContent);
                }

                // Save optimized version if different
                if ($optimizedContent !== $content) {
                    file_put_contents($cachePath, $optimizedContent);
                }
            }

            // Cache in memory for this request
            $this->compiledTemplates[$viewKey] = $cachePath;

            return $cachePath;
        } catch (\Throwable $th) {
            error("JIT optimization failed for {$viewKey}: " . $th->getMessage());
            return $cachePath; // Fallback to original
        }
    }

    /**
     * Ignore CSS JS Comments
     *
     * @param string $content
     * @return string
     */
    protected function safeFullMinifyWithJsCssAware(string $content): string
    {
        $placeholders = [];
        $i = 0;

        // Process script blocks
        $content = preg_replace_callback('/<script\b[^>]*>.*?<\/script>/is', function ($matches) use (&$placeholders, &$i) {
            $key = "__SCRIPT_{$i}__";
            $script = preg_replace([
                '/\/\/[^\n]*\n/',    // Remove single-line comments
                '/\/\*.*?\*\//s',    // Remove multi-line comments
                '/\s+/'              // Collapse whitespace
            ], ['', '', ' '], $matches[0]);
            $placeholders[$key] = $script;
            $i++;
            return $key;
        }, $content);

        // Process style blocks
        $content = preg_replace_callback('/<style\b[^>]*>.*?<\/style>/is', function ($matches) use (&$placeholders, &$i) {
            $key = "__STYLE_{$i}__";
            $style = preg_replace([
                '/\/\*.*?\*\//s',    // Remove CSS comments
                '/\s+/'              // Collapse whitespace
            ], ['', ' '], $matches[0]);
            $placeholders[$key] = $style;
            $i++;
            return $key;
        }, $content);

        // Minify HTML
        $content = preg_replace('/\s+/', ' ', $content);

        // Restore protected content
        foreach ($placeholders as $key => $original) {
            $content = str_replace($key, $original, $content);
        }

        return $content;
    }

    /**
     * Optimize complex loops including nested
     *
     * @param string $content
     * @return string
     */
    protected function optimizeComplexLoops(string $content): string
    {
        // Optimize nested loops
        $content = preg_replace_callback(
            '/\<\?php\s+foreach\s*\((.*?)\)\s*:\s*\?\>(.*?)\<\?php\s+endforeach\s*;\s*\?\>/s',
            function ($matches) {
                $expression = $matches[1];
                $loopContent = $matches[2];

                // Count nested loops to optimize depth tracking
                $nestedCount = substr_count($loopContent, '<?php foreach');

                if ($nestedCount > 0) {
                    return "<?php foreach($expression): ?>$loopContent<?php endforeach; ?>";
                }

                // For non-nested loops, we can optimize further
                return "<?php foreach($expression): ?>$loopContent<?php endforeach; ?>";
            },
            $content
        );

        // Optimize loops with known counts
        $content = preg_replace_callback(
            '/\<\?php\s+\$__currentLoopData\s*=\s*(.*?)\s*;\s*\$__env->addLoop\(\$__currentLoopData\);\s*foreach\(\$__currentLoopData\s+as\s+(.*?)\):\s*\$__env->incrementLoopIndices\(\);\s*\$\w+\s*=\s*\$__env->getFirstLoop\(\);\s*\?\>/',
            function ($matches) {
                $data = $matches[1];
                $item = $matches[2];

                // Simple variable optimization
                if (preg_match('/^\$\w+$/', $data)) {
                    return "<?php foreach({$data} as {$item}): ?>";
                }

                // Method call optimization
                if (preg_match('/^\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*->[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*\(\)$/', $data)) {
                    return "<?php foreach({$data} as {$item}): ?>";
                }

                return $matches[0];
            },
            $content
        );

        return $content;
    }

    /**
     * Optimize all the necessary control structures
     *
     * @param string $content
     * @return string
     */
    protected function optimizeControlStructures(string $content): string
    {
        // Optimize if/elseif/else structures
        $content = preg_replace([
            '/\<\?php\s+if\s*\((.*?)\)\s*:\s*\?\>/s',
            '/\<\?php\s+elseif\s*\((.*?)\)\s*:\s*\?\>/s',
            '/\<\?php\s+else\s*:\s*\?\>/s',
            '/\<\?php\s+endif\s*;\s*\?\>/s'
        ], [
            '<?php if($1): ?>',
            '<?php elseif($1): ?>',
            '<?php else: ?>',
            '<?php endif; ?>'
        ], $content);

        // Optimize loops (foreach, for, while)
        $content = preg_replace([
            '/\<\?php\s+foreach\s*\((.*?)\)\s*:\s*\?\>/s',
            '/\<\?php\s+endforeach\s*;\s*\?\>/s',
            '/\<\?php\s+for\s*\((.*?)\)\s*:\s*\?\>/s',
            '/\<\?php\s+endfor\s*;\s*\?\>/s',
            '/\<\?php\s+while\s*\((.*?)\)\s*:\s*\?\>/s',
            '/\<\?php\s+endwhile\s*;\s*\?\>/s'
        ], [
            '<?php foreach($1): ?>',
            '<?php endforeach; ?>',
            '<?php for($1): ?>',
            '<?php endfor; ?>',
            '<?php while($1): ?>',
            '<?php endwhile; ?>'
        ], $content);

        // Preserving newlines in HTML
        $content = preg_replace('/\>\s+\</', '><', $content);

        // Preserve HTML comments
        $content = preg_replace_callback('/<!--(.*?)-->/s', function ($matches) {
            return '<!--' . trim($matches[1]) . '-->';
        }, $content);

        return $content;
    }

    /**
     * Optimize Blade foreach loops
     *
     * @param string $content
     * @return string
     */
    protected function optimizeBladeLoops(string $content): string
    {
        return preg_replace_callback(
            '/\@foreach\s*\((.*?)\)(.*?)\@endforeach/s',
            function ($matches) {
                $expression = trim($matches[1]);
                $loopContent = $matches[2];

                // Optimize the loop content
                $optimizedContent = $this->optimizeControlStructures($loopContent);

                return "<?php foreach($expression): ?>$optimizedContent<?php endforeach; ?>";
            },
            $content
        );
    }

    /**
     * Optimize echo statement effectively
     *
     * @param string $content
     * @return string
     */
    protected function optimizeEchoStatements(string $content): string
    {
        // Combine consecutive echos
        $content = preg_replace('/\<\?=\s*(.*?)\s*\?\>\s*\<\?=\s*(.*?)\s*\?\>/', '<?=$1.$2?>', $content);

        // Remove unnecessary parentheses
        $content = preg_replace('/\<\?=\s*\((.*?)\)\s*\?\>/', '<?=$1?>', $content);

        return $content;
    }

    /**
     * Handling inline small templates
     *
     * @param string $content
     * @return string
     */
    protected function inlineSmallTemplates(string $content): string
    {
        // Inline small @include directives (for very small templates)
        if (preg_match_all('/\@include\(\s*[\'"](.*?)[\'"]\s*(?:,\s*(.*?)\s*)?\)/', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $view = trim($match[1], '\'"');
                $data = isset($match[2]) ? $match[2] : '[]';

                try {
                    $includedContent = $this->compileView($this->findView($view), []);

                    // Only inline if the content is small
                    if (strlen($includedContent) < 500) {
                        $content = str_replace($match[0], $includedContent, $content);
                    }
                } catch (\Exception $e) {
                    // Skip if the view can't be found
                    continue;
                }
            }
        }

        return $content;
    }

    /**
     * Optimize loops
     *
     * @param string $content
     * @return string
     */
    protected function optimizeLoops(string $content): string
    {
        // Optimize foreach loops with known counts
        $content = preg_replace_callback(
            '/\<\?php\s+\$__currentLoopData\s*=\s*(.*?)\s*;\s*\$__env->addLoop\(\$__currentLoopData\);\s*foreach\(\$__currentLoopData\s+as\s+(.*?)\):\s*\$__env->incrementLoopIndices\(\);\s*\$\w+\s*=\s*\$__env->getFirstLoop\(\);\s*\?\>/',
            function ($matches) {
                $data = $matches[1];
                $item = $matches[2];

                // If the data is a simple variable, we can optimize
                if (preg_match('/^\$\w+$/', $data)) {
                    return "<?php foreach({$data} as {$item}): ?>";
                }

                return $matches[0];
            },
            $content
        );

        return $content;
    }

    /**
     * Lazy load components
     *
     * @param string $content
     * @return string
     */
    protected function lazyLoadComponents(string $content): string
    {
        // Find component tags and mark them for lazy loading
        if (preg_match_all(
            '/\<\?php\s+if\s*\(\s*!\s*isset\(\$component\)\s*\)\s*:\s*\$component\s*=\s*\$__env->getComponent\(\s*(.*?)\s*\);\s*endif;\s*echo\s*\$component->render\(\s*(.*?)\s*\);\s*\?\>/',
            $content,
            $matches,
            PREG_SET_ORDER
        )) {

            foreach ($matches as $match) {
                $componentName = trim($match[1], '\'"');
                $componentData = $match[2];

                // Register component for lazy loading
                $this->lazyComponents[$componentName] = true;

                // Replace with lazy loading code
                $lazyCode = <<<PHP
<?php 
if (!isset(\$__lazyComponents['$componentName'])) {
    \$__lazyComponents['$componentName'] = \$__env->getComponent($match[1]);
}
echo \$__lazyComponents['$componentName']->render($componentData);
?>
PHP;
                $content = str_replace($match[0], $lazyCode, $content);
            }
        }

        return $content;
    }

    /**
     * Clear all the compiled templates
     *
     * @return void
     */
    public function clearCompiledTemplates(): void
    {
        $this->compiledTemplates = [];
        $this->lazyComponents = [];
        $this->loopStacks = [];
    }

    /**
     * Add new loop to the stack.
     *
     * @param mixed $data
     * @return void
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
