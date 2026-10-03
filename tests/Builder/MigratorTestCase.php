<?php

namespace Tests\Unit\Builder;

use PHPUnit\Framework\TestCase;
use Phaseolies\Config\Config;
use Phaseolies\Database\Database;
use Phaseolies\Database\Migration\MigrationRepository;
use Phaseolies\Database\Migration\Migrator;
use Phaseolies\Database\Migration\Schema;
use Phaseolies\DI\Container;
use Tests\Support\MockContainer;
use Phaseolies\Support\Facades\Facade;

/**
 * Harness for tests that run migrations against a real scratch database
 * (SQLite by default) with real migration files.
 */
abstract class MigratorTestCase extends TestCase
{
    protected const CONNECTION = 'migrator_test';

    protected string $dir;

    protected string $dbFile;

    protected Migrator $migrator;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/doppar-migrator-' . bin2hex(random_bytes(5));
        mkdir($this->dir . '/migrations', 0755, true);
        $this->dbFile = $this->dir . '/test.sqlite';

        $container = new MockContainer();
        Container::setInstance($container);
        $this->resetConfig();

        Config::set('database.connections.' . self::CONNECTION, $this->connectionConfig());
        Config::set('database.default', self::CONNECTION);

        $container->bind('db', fn() => new Database());
        $container->bind('schema', fn() => new Schema());

        if (method_exists(Facade::class, 'clearResolvedInstances')) {
            Facade::clearResolvedInstances();
        }

        $this->wipeDatabase();

        $this->migrator = new Migrator(new MigrationRepository(), $this->dir . '/migrations');
    }

    /**
     * The scratch connection the migrator runs against
     *
     * @return array
     */
    protected function connectionConfig(): array
    {
        return [
            'driver' => 'sqlite',
            'database' => $this->dbFile,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ];
    }

    /**
     * Remove everything the test created in the scratch database
     *
     * @return void
     */
    protected function wipeDatabase(): void
    {
    }

    protected function tearDown(): void
    {
        $this->wipeDatabase();
        $this->setStatic(Database::class, 'connections', []);
        $this->setStatic(Database::class, 'drivers', []);
        $this->setStatic(Database::class, 'transactions', []);

        $this->resetConfig();
        (new \ReflectionClass(Container::class))->getProperty('instance')->setValue(null, null);

        foreach (glob($this->dir . '/migrations/*') ?: [] as $file) {
            unlink($file);
        }
        @unlink($this->dbFile);
        @rmdir($this->dir . '/migrations');
        @rmdir($this->dir);
    }

    protected function createTable(string $name, string $table): void
    {
        $this->write($name, "Schema::create('{$table}', fn(Blueprint \$t) => \$t->id());", '', "Schema::dropIfExists('{$table}');");
    }

    protected function write(string $name, string $up, string $extra = '', string $down = ''): void
    {
        $code = <<<PHP
        <?php

        use Phaseolies\Support\Facades\Schema;
        use Phaseolies\Database\Migration\Blueprint;
        use Phaseolies\Database\Migration\Migration;

        return new class extends Migration
        {
            {$extra}

            public function up(): void
            {
                {$up}
            }

            public function down(): void
            {
                {$down}
            }
        };
        PHP;

        file_put_contents("{$this->dir}/migrations/{$name}.php", $code);
    }

    protected function hasTable(string $table): bool
    {
        return (new Database(self::CONNECTION))->tableExists($table);
    }

    protected function resetConfig(): void
    {
        $reflection = new \ReflectionClass(Config::class);

        foreach (['config' => [], 'cacheFile' => null, 'loadedFromCache' => false, 'fileHashes' => []] as $name => $value) {
            if ($reflection->hasProperty($name)) {
                $reflection->getProperty($name)->setValue(null, $value);
            }
        }
    }

    protected function setStatic(string $class, string $property, mixed $value): void
    {
        (new \ReflectionClass($class))->getProperty($property)->setValue(null, $value);
    }
}
