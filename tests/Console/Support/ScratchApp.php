<?php

namespace Tests\Console\Support;

use Phaseolies\DI\Container;

/**
 * A throwaway application directory with a stub "pool" script.
 *
 * The stub records every call in calls.log (working directory, PHP binary,
 * arguments, environment flag) and behaves according to its first argument:
 *   slow <seconds>  sleeps, then exits 0
 *   fail            prints to stderr and exits 3
 *   ok              prints "ok"
 *   echo ...        prints its arguments as JSON
 * Anything else, including cron:finish, is only recorded.
 */
final class ScratchApp
{
    public string $root;

    public function __construct()
    {
        $this->root = rtrim(sys_get_temp_dir(), '/') . '/doppar-cron-app-' . bin2hex(random_bytes(5));

        mkdir($this->root . '/storage/schedule', 0755, true);

        file_put_contents($this->root . '/pool', <<<'PHP'
<?php
$args = array_slice($argv, 1);
file_put_contents(__DIR__ . '/calls.log', json_encode([
    'cwd' => getcwd(),
    'php' => PHP_BINARY,
    'args' => $args,
    'flag' => getenv('APP_SCHEDULE_RUNNING'),
]) . "\n", FILE_APPEND | LOCK_EX);

switch ($args[0] ?? '') {
    case 'slow':
        sleep((int) ($args[1] ?? 2));
        echo "slow done\n";
        exit(0);
    case 'fail':
        fwrite(STDERR, "boom\n");
        exit(3);
    case 'ok':
        echo "ok\n";
        exit(0);
    case 'echo':
        echo json_encode(array_slice($args, 1)), "\n";
        exit(0);
}
PHP);

        Container::setInstance((new ScratchAppContainer())->setRoot($this->root));
    }

    public function path(string $path = ''): string
    {
        return $path === '' ? $this->root : $this->root . '/' . ltrim($path, '/');
    }

    /**
     * @return array<int, array{cwd: string, php: string, args: array<int, string>, flag: string|false}>
     */
    public function calls(): array
    {
        $file = $this->path('calls.log');

        if (!file_exists($file)) {
            return [];
        }

        return array_map(
            fn(string $line) => json_decode($line, true),
            array_values(array_filter(explode("\n", (string) file_get_contents($file))))
        );
    }

    /**
     * Calls whose first argument is the given command name.
     *
     * @return array<int, array<string, mixed>>
     */
    public function callsTo(string $command): array
    {
        return array_values(array_filter($this->calls(), fn(array $call) => ($call['args'][0] ?? null) === $command));
    }

    public function destroy(): void
    {
        Container::forgetInstance();

        if (!is_dir($this->root)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($this->root);
    }

    /**
     * Wait until a condition holds, or fail after the timeout.
     */
    public static function waitUntil(callable $condition, float $timeoutSeconds = 10.0): bool
    {
        $deadline = microtime(true) + $timeoutSeconds;

        while (microtime(true) < $deadline) {
            if ($condition()) {
                return true;
            }

            usleep(50_000);
        }

        return (bool) $condition();
    }
}
