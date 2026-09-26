<?php

namespace Phaseolies\Support\Router\Plan;

/**
 * Serves action plans, from the cheapest place that has them:
 *
 *  1. The in-process memo. A plan is built at most once per process, so a
 *     persistent worker never re-reflects an action, and even a plain
 *     PHP-FPM request never reflects the same action twice.
 *  2. The compiled cache file written by route:cache. It is a plain PHP file
 *     returning an array, so opcache serves it from shared memory and a
 *     request pays for no reflection and loads no attribute class at all.
 *  3. The planner, which reflects once and feeds the memo.
 *
 * The compiled file is only consulted after useCompiled(true), which the
 * router does when it loads the route cache, so plans and routes are always
 * from the same build. Nothing is ever written while handling a request.
 */
final class ActionPlanStore
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $memo = [];

    /**
     * Plans of the compiled file. Null until first needed.
     *
     * @var array<string, array<string, mixed>>|null
     */
    private ?array $compiled = null;

    private bool $useCompiled = false;

    /**
     * Plans of closures, released together with the closure.
     *
     * @var \WeakMap<\Closure, array<string, mixed>>
     */
    private \WeakMap $closures;

    /**
     * @param string|\Closure(): string $path Where the compiled plans live. A closure is
     *        called on first use, so nothing is resolved by a request that never reads the file.
     * @param ActionPlanner|null $planner Built on first use when omitted: a request served from
     *        a compiled plan then never loads the planner (nor any attribute class) at all.
     */
    public function __construct(private string|\Closure $path, private ?ActionPlanner $planner = null)
    {
        $this->closures = new \WeakMap();
    }

    /**
     * Get the plan of a controller action
     *
     * @param string $class
     * @param string $method
     * @return array<string, mixed>
     */
    public function forAction(string $class, string $method): array
    {
        $key = self::key($class, $method);

        if (isset($this->memo[$key])) {
            return $this->memo[$key];
        }

        if ($this->useCompiled && isset($this->compiledPlans()[$key])) {
            return $this->memo[$key] = $this->compiled[$key];
        }

        return $this->memo[$key] = $this->planner()->forAction($class, $method);
    }

    /**
     * Get the plan of a closure route
     *
     * @param \Closure $closure
     * @return array<string, mixed>
     */
    public function forClosure(\Closure $closure): array
    {
        return $this->closures[$closure] ??= $this->planner()->forClosure($closure);
    }

    /**
     * Read plans from the compiled file (or stop doing so)
     *
     * @param bool $use
     * @return void
     */
    public function useCompiled(bool $use = true): void
    {
        $this->useCompiled = $use;
    }

    /**
     * Compile plans for the given actions and write them to the compiled file
     *
     * @param iterable<int, array{0: string, 1: string}> $actions [class, method] pairs
     * @return int
     */
    public function compile(iterable $actions): int
    {
        $plans = [];

        foreach ($actions as [$class, $method]) {
            try {
                $plans[self::key($class, $method)] = $this->planner()->forAction($class, $method);
            } catch (\Throwable) {
                continue;
            }
        }

        ksort($plans);

        $this->write(['format' => ActionPlanner::FORMAT, 'plans' => $plans]);
        $this->compiled = $plans;

        return count($plans);
    }

    /**
     * Delete the compiled file and forget what was loaded from it
     *
     * @return bool
     */
    public function clear(): bool
    {
        $this->compiled = null;

        if (!file_exists($this->path())) {
            return true;
        }

        $this->invalidate();

        return @unlink($this->path());
    }

    /**
     * Forget everything held in memory
     *
     * @return void
     */
    public function flush(): void
    {
        $this->memo = [];
        $this->compiled = null;
        $this->closures = new \WeakMap();
    }

    /**
     * @return ActionPlanner
     */
    private function planner(): ActionPlanner
    {
        return $this->planner ??= new ActionPlanner();
    }

    /**
     * @return string
     */
    public function path(): string
    {
        if ($this->path instanceof \Closure) {
            $this->path = ($this->path)();
        }

        return $this->path;
    }

    /**
     * Get the key a plan is stored under. PHP class and method names are
     * case-insensitive, so the key is too.
     *
     * @param string $class
     * @param string $method
     * @return string
     */
    public static function key(string $class, string $method): string
    {
        return strtolower(ltrim($class, '\\')) . '::' . strtolower($method);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function compiledPlans(): array
    {
        if ($this->compiled !== null) {
            return $this->compiled;
        }

        $this->compiled = [];

        if (!is_file($this->path())) {
            return $this->compiled;
        }

        $data = require $this->path();

        // A file from another release, or a damaged one, is ignored: plans are
        // then built normally instead of being misread.
        if (is_array($data) && ($data['format'] ?? null) === ActionPlanner::FORMAT && is_array($data['plans'] ?? null)) {
            $this->compiled = $data['plans'];
        }

        return $this->compiled;
    }

    /**
     * Write the file atomically, so a request never reads half of it
     *
     * @param array<string, mixed> $data
     * @return void
     */
    private function write(array $data): void
    {
        $directory = dirname($this->path());

        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException("Cannot create the cache directory [{$directory}].");
        }

        $temporary = $this->path() . '.' . bin2hex(random_bytes(4)) . '.tmp';

        if (file_put_contents($temporary, '<?php return ' . var_export($data, true) . ';', LOCK_EX) === false) {
            throw new \RuntimeException("Cannot write the action plans to [{$temporary}].");
        }

        if (!@rename($temporary, $this->path())) {
            @unlink($temporary);

            throw new \RuntimeException("Cannot move the action plans into place at [{$this->path()}].");
        }

        $this->invalidate();
    }

    /**
     * With opcache.validate_timestamps=0 (the usual production setting) opcache
     * never re-checks a file, so a rebuilt or removed cache has to be dropped
     * from it explicitly.
     *
     * @return void
     */
    private function invalidate(): void
    {
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($this->path(), true);
        }
    }
}
