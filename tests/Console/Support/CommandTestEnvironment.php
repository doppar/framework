<?php

namespace Tests\Unit\Console\Support {

final class CommandTestEnvironment
{
    public static string $root;

    public static array $config = [];

    public static array $appBindings = [];

    public static ?object $appInstance = null;

    public static array $errors = [];

    public static function reset(): void
    {
        self::$root = sys_get_temp_dir() . '/doppar-command-tests-' . bin2hex(random_bytes(5));
        self::$config = [
            'app.name' => 'Doppar Demo',
            'app.timezone' => 'UTC',
            'database.default' => 'testing',
        ];
        self::$appBindings = [];
        self::$appInstance = null;
        self::$errors = [];

        if (!is_dir(self::$root)) {
            mkdir(self::$root, 0755, true);
        }
    }

    public static function cleanup(): void
    {
        if (!isset(self::$root) || !is_dir(self::$root)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::$root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isLink()) {
                unlink($item->getPathname());
                continue;
            }

            if ($item->isDir()) {
                rmdir($item->getPathname());
                continue;
            }

            unlink($item->getPathname());
        }

        rmdir(self::$root);
    }

    public static function path(string $path = ''): string
    {
        $base = rtrim(self::$root, '/');

        if ($path === '') {
            return $base;
        }

        return $base . '/' . ltrim($path, '/');
    }

    public static function config(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return self::$config;
        }

        return self::$config[$key] ?? $default;
    }

    public static function app(?string $key = null): mixed
    {
        if ($key === null) {
            return self::$appInstance;
        }

        return self::$appBindings[$key] ?? null;
    }

    public static function bind(string $key, mixed $value): void
    {
        self::$appBindings[$key] = $value;
    }

    public static function recordError(string $message): void
    {
        self::$errors[] = $message;
    }
}

final class FakeStringHelper
{
    public function suffixAppend(string $value, string $suffix): string
    {
        return str_ends_with($value, $suffix) ? $value : $value . $suffix;
    }

    public function removeSuffix(string $value, string $suffix): string
    {
        if (!str_ends_with($value, $suffix)) {
            return $value;
        }

        return substr($value, 0, -strlen($suffix));
    }

    public function snake(string $value): string
    {
        $value = preg_replace('/(?<!^)[A-Z]/', '_$0', $value) ?? $value;

        return strtolower($value);
    }
}

final class FakeSessionStore
{
    public int $flushCount = 0;

    public function flush(): void
    {
        $this->flushCount++;
    }
}

final class FakeDatabaseInspector
{
    public array $columns = [];

    public function getTableColumns(string $table): array
    {
        return $this->columns[$table] ?? [];
    }
}

trait InteractsWithFakeCommandIO
{
    public array $fakeArguments = [];

    public array $fakeOptions = [];

    public array $capturedLines = [];

    public array $capturedInfos = [];

    public array $capturedErrors = [];

    public array $capturedWarnings = [];

    public array $capturedSuccesses = [];

    protected function argument($key = null)
    {
        if ($key === null) {
            return $this->fakeArguments;
        }

        return $this->fakeArguments[$key] ?? null;
    }

    protected function option($key = null)
    {
        if ($key === null) {
            return $this->fakeOptions;
        }

        return $this->fakeOptions[$key] ?? null;
    }

    protected function info($string): void
    {
        $this->capturedInfos[] = (string) $string;
    }

    protected function error($string): void
    {
        $this->capturedErrors[] = (string) $string;
    }

    protected function line(string $string, ?string $style = null): void
    {
        $this->capturedLines[] = [$string, $style];
    }

    protected function newLine($count = 1): void
    {
    }

    protected function displaySuccess(string $message): void
    {
        $this->capturedSuccesses[] = $message;
    }

    protected function displayError(string $message): void
    {
        $this->capturedErrors[] = $message;
    }

    protected function displayWarning(string $message): void
    {
        $this->capturedWarnings[] = $message;
    }

    protected function displayInfo(string $message): void
    {
        $this->capturedInfos[] = $message;
    }

    protected function executeWithTiming(callable $callback): int
    {
        $result = $callback();

        return is_int($result) ? $result : 0;
    }

    protected function withTiming(callable $operation, ?string $successMessage = null): int
    {
        $result = $operation();

        if ($successMessage !== null) {
            $this->displaySuccess($successMessage);
        }

        return is_int($result) ? $result : 0;
    }
}
}

namespace Phaseolies\Console\Commands {

    use Tests\Unit\Console\Support\CommandTestEnvironment;
    use Tests\Unit\Console\Support\FakeDatabaseInspector;
    use Tests\Unit\Console\Support\FakeSessionStore;
    use Tests\Unit\Console\Support\FakeStringHelper;

    if (!function_exists(__NAMESPACE__ . '\base_path')) {
        function base_path(string $path = ''): string
        {
            return CommandTestEnvironment::path($path);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\storage_path')) {
        function storage_path(string $path = ''): string
        {
            return CommandTestEnvironment::path('storage/' . ltrim($path, '/'));
        }
    }

    if (!function_exists(__NAMESPACE__ . '\resource_path')) {
        function resource_path(string $path = ''): string
        {
            return CommandTestEnvironment::path('resources/' . ltrim($path, '/'));
        }
    }

    if (!function_exists(__NAMESPACE__ . '\public_path')) {
        function public_path(string $path = ''): string
        {
            return CommandTestEnvironment::path('public/' . ltrim($path, '/'));
        }
    }

    if (!function_exists(__NAMESPACE__ . '\config')) {
        function config(?string $key = null, mixed $default = null): mixed
        {
            return CommandTestEnvironment::config($key, $default);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\app')) {
        function app(?string $key = null): mixed
        {
            return CommandTestEnvironment::app($key);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\session')) {
        function session(): FakeSessionStore
        {
            $session = CommandTestEnvironment::app('session');

            if ($session instanceof FakeSessionStore) {
                return $session;
            }

            $session = new FakeSessionStore();
            CommandTestEnvironment::bind('session', $session);

            return $session;
        }
    }

    if (!function_exists(__NAMESPACE__ . '\db')) {
        function db(): FakeDatabaseInspector
        {
            $db = CommandTestEnvironment::app('db');

            if ($db instanceof FakeDatabaseInspector) {
                return $db;
            }

            $db = new FakeDatabaseInspector();
            CommandTestEnvironment::bind('db', $db);

            return $db;
        }
    }

    if (!function_exists(__NAMESPACE__ . '\str')) {
        function str(): FakeStringHelper
        {
            return new FakeStringHelper();
        }
    }

    if (!function_exists(__NAMESPACE__ . '\error')) {
        function error(string $message): void
        {
            CommandTestEnvironment::recordError($message);
        }
    }
}

namespace Phaseolies\Console\Commands\Migrations {

    use Tests\Unit\Console\Support\CommandTestEnvironment;

    if (!function_exists(__NAMESPACE__ . '\base_path')) {
        function base_path(string $path = ''): string
        {
            return CommandTestEnvironment::path($path);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\config')) {
        function config(?string $key = null, mixed $default = null): mixed
        {
            return CommandTestEnvironment::config($key, $default);
        }
    }

    if (!function_exists(__NAMESPACE__ . '\app')) {
        function app(?string $key = null): mixed
        {
            return CommandTestEnvironment::app($key);
        }
    }
}

namespace Phaseolies\Console\Commands\Cron {

    use Tests\Unit\Console\Support\CommandTestEnvironment;

    if (!function_exists(__NAMESPACE__ . '\storage_path')) {
        function storage_path(string $path = ''): string
        {
            return CommandTestEnvironment::path('storage/' . ltrim($path, '/'));
        }
    }

    if (!function_exists(__NAMESPACE__ . '\error')) {
        function error(string $message): void
        {
            CommandTestEnvironment::recordError($message);
        }
    }
}

namespace Phaseolies\Console\Commands\Tests {

    use Tests\Unit\Console\Support\CommandTestEnvironment;

    if (!function_exists(__NAMESPACE__ . '\base_path')) {
        function base_path(string $path = ''): string
        {
            return CommandTestEnvironment::path($path);
        }
    }
}
