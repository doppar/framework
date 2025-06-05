<?php

namespace Tests\Unit;

use Symfony\Component\Console\Command\Command;
use Phaseolies\Console\Console;
use Phaseolies\Application;
use PHPUnit\Framework\TestCase;
use Mockery as m;

class ConsoleTest extends TestCase
{
    private Application $app;
    private Console $console;

    protected function setUp(): void
    {
        $this->app = m::mock(Application::class);
        $this->console = new Console($this->app, 'Test Console', '1.0.0');
    }

    protected function tearDown(): void
    {
        m::close();
    }

    public function testConstructor()
    {
        $this->assertSame('Test Console', $this->console->getName());
        $this->assertSame('1.0.0', $this->console->getVersion());
    }

    public function testAddCommandsWithStringClassNames()
    {
        $command = m::mock(Command::class);
        $commandName = 'TestCommand';

        $command->shouldReceive('getName')->andReturn('test:command');
        $command->shouldReceive('setApplication')->with($this->console)->once();
        $command->shouldReceive('isEnabled')->andReturn(true);
        $command->shouldReceive('getAliases')->andReturn([]);
        $command->shouldReceive('getDefinition')->andReturn(new \Symfony\Component\Console\Input\InputDefinition());
        $command->shouldReceive('ignoreValidationErrors')->andReturnSelf();
        $command->shouldReceive('setCode')->andReturnSelf();
        $command->shouldReceive('setDescription')->andReturnSelf();
        $command->shouldReceive('setHelp')->andReturnSelf();
        $command->shouldReceive('setHidden')->andReturnSelf();

        $this->app->shouldReceive('make')
            ->with($commandName)
            ->once()
            ->andReturn($command);

        $this->console->addCommands([$commandName]);

        $this->assertTrue($this->console->has('test:command'));
    }

    // resolveCommand() is protected, so by making it public, it is tested
    // public function testResolveCommandWithCommandObject()
    // {
    //     $command = m::mock(Command::class);
    //     $result = $this->console->resolveCommand($command);

    //     $this->assertSame($command, $result);
    // }

    // resolveCommand() is protected, so by making it public, it is tested
    // public function testResolveCommandWithString()
    // {
    //     $command = m::mock(Command::class);
    //     $commandName = 'TestCommand';

    //     $this->app->shouldReceive('make')
    //         ->with($commandName)
    //         ->once()
    //         ->andReturn($command);

    //     $result = $this->console->resolveCommand($commandName);

    //     $this->assertSame($command, $result);
    // }
}
