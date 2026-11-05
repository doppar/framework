<?php

namespace Tests\Unit\Console;

use PHPUnit\Framework\TestCase;
use Phaseolies\Config\Config;
use Phaseolies\Console\Commands\SessionTableCommand;
use Phaseolies\DI\Container;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Support\MockContainer;

class SessionTableCommandTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/doppar-session-table-' . bin2hex(random_bytes(5));

        Container::setInstance(new MockContainer());
        $this->resetConfig();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->dir);

        $this->resetConfig();
        (new \ReflectionClass(Container::class))->getProperty('instance')->setValue(null, null);
    }

    public function test_it_creates_a_migration_for_the_configured_table(): void
    {
        Config::set('session.table', 'user_sessions');

        $tester = $this->run_();
        $files = glob($this->dir . '/*_create_user_sessions_table.php');

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertCount(1, $files);
        $this->assertMatchesRegularExpression('/^\d{4}_\d{2}_\d{2}_\d{6}_create_user_sessions_table\.php$/', basename($files[0]));

        $migration = (string) file_get_contents($files[0]);

        $this->assertStringContainsString("Schema::create('user_sessions'", $migration);
        $this->assertStringContainsString("Schema::dropIfExists('user_sessions')", $migration);
        $this->assertStringNotContainsString('{{ table }}', $migration);
    }

    public function test_it_defaults_to_the_sessions_table(): void
    {
        $this->run_();

        $this->assertCount(1, glob($this->dir . '/*_create_sessions_table.php'));
    }

    public function test_it_does_not_create_a_second_migration(): void
    {
        $this->run_();

        $tester = $this->run_();

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('already exists', $tester->getDisplay());
        $this->assertCount(1, glob($this->dir . '/*.php'));
    }

    public function test_it_rejects_an_unsafe_table_name(): void
    {
        Config::set('session.table', 'sessions; DROP TABLE users');

        $tester = $this->run_();

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertSame([], glob($this->dir . '/*.php') ?: []);
    }

    private function run_(): CommandTester
    {
        $directory = $this->dir;

        $command = new class ($directory) extends SessionTableCommand {
            public function __construct(private string $directory)
            {
                parent::__construct();
            }

            protected function migrationPath(): string
            {
                return $this->directory;
            }
        };

        $tester = new CommandTester($command);
        $tester->execute([], ['interactive' => false, 'decorated' => false]);

        return $tester;
    }

    private function resetConfig(): void
    {
        $reflection = new \ReflectionClass(Config::class);

        foreach (['config' => [], 'cacheFile' => null, 'loadedFromCache' => false, 'fileHashes' => []] as $name => $value) {
            if ($reflection->hasProperty($name)) {
                $reflection->getProperty($name)->setValue(null, $value);
            }
        }
    }
}
