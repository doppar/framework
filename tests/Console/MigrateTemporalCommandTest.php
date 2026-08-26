<?php

namespace Phaseolies\Console\Commands\Migrations {
    if (!function_exists(__NAMESPACE__ . '\config')) {
        function config($key = null, $default = null)
        {
            return $key === 'database.default' ? 'default_connection' : $default;
        }
    }
}

namespace Tests\Unit\Console {

require_once __DIR__ . '/../Support/Model/MockTemporalRecord.php';

use PHPUnit\Framework\TestCase;
use Phaseolies\Console\Commands\Migrations\MigrateTemporalCommand;
use Tests\Support\Model\MockTemporalRecord;

class MigrateTemporalCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (class_exists(\Tests\Unit\Console\Support\CommandTestEnvironment::class)) {
            \Tests\Unit\Console\Support\CommandTestEnvironment::$config['database.default'] = 'default_connection';
        }
    }

    public function testResolveConnectionForModelPrefersModelConnectionWhenNoOverride(): void
    {
        $command = new MigrateTemporalCommand();
        $method = new \ReflectionMethod($command, 'resolveConnectionForModel');

        $connection = $method->invoke($command, new MockTemporalRecord(), null);

        $this->assertSame('default', $connection);
    }

    public function testResolveConnectionForModelPrefersCommandOverride(): void
    {
        $command = new MigrateTemporalCommand();
        $method = new \ReflectionMethod($command, 'resolveConnectionForModel');

        $connection = $method->invoke($command, new MockTemporalRecord(), 'mysql_second');

        $this->assertSame('mysql_second', $connection);
    }

    public function testResolveConnectionForModelFallsBackToDefaultConfig(): void
    {
        $command = new MigrateTemporalCommand();
        $method = new \ReflectionMethod($command, 'resolveConnectionForModel');

        $model = new class extends \Phaseolies\Database\Entity\Model {
            protected $table = 'plain_temporal_records';
            protected $connection = null;
        };

        $connection = $method->invoke($command, $model, null);

        $this->assertSame('default_connection', $connection);
    }
}
}
