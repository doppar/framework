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

    public function testResolveCommandWithCommandObject()
    {
        $command = m::mock(Command::class);

        $reflection = new \ReflectionClass(Console::class);
        $method = $reflection->getMethod('resolveCommand');
        $method->setAccessible(true);

        $result = $method->invoke($this->console, $command);

        $this->assertSame($command, $result);
    }

    public function testResolveCommandWithString()
    {
        $command = m::mock(Command::class);
        $commandName = 'TestCommand';

        $this->app->shouldReceive('make')
            ->with($commandName)
            ->once()
            ->andReturn($command);

        $reflection = new \ReflectionClass(Console::class);
        $method = $reflection->getMethod('resolveCommand');
        $method->setAccessible(true);

        $result = $method->invoke($this->console, $commandName);

        $this->assertSame($command, $result);
    }

    public function testResolveCommandsWithMixedTypes()
    {
        $command1 = m::mock(Command::class);
        $command2 = m::mock(Command::class);
        $commandName = 'TestCommand';

        // Mock command1 expectations
        $command1->shouldReceive('getName')->andReturn('command1');
        $command1->shouldReceive('setApplication')->with($this->console)->once();
        $command1->shouldReceive('isEnabled')->andReturn(true);
        $command1->shouldReceive('getAliases')->andReturn([]);
        $command1->shouldReceive('getDefinition')->andReturn(new \Symfony\Component\Console\Input\InputDefinition());
        $command1->shouldReceive('ignoreValidationErrors')->andReturnSelf();
        $command1->shouldReceive('setCode')->andReturnSelf();
        $command1->shouldReceive('setDescription')->andReturnSelf();
        $command1->shouldReceive('setHelp')->andReturnSelf();
        $command1->shouldReceive('setHidden')->andReturnSelf();

        // Mock command2 expectations
        $command2->shouldReceive('getName')->andReturn('command2');
        $command2->shouldReceive('setApplication')->with($this->console)->once();
        $command2->shouldReceive('isEnabled')->andReturn(true);
        $command2->shouldReceive('getAliases')->andReturn([]);
        $command2->shouldReceive('getDefinition')->andReturn(new \Symfony\Component\Console\Input\InputDefinition());
        $command2->shouldReceive('ignoreValidationErrors')->andReturnSelf();
        $command2->shouldReceive('setCode')->andReturnSelf();
        $command2->shouldReceive('setDescription')->andReturnSelf();
        $command2->shouldReceive('setHelp')->andReturnSelf();
        $command2->shouldReceive('setHidden')->andReturnSelf();

        $this->app->shouldReceive('make')
            ->with($commandName)
            ->once()
            ->andReturn($command2);

        // Add mixed commands: one object and one string
        $this->console->addCommands([$command1, $commandName]);

        $this->assertTrue($this->console->has('command1'));
        $this->assertTrue($this->console->has('command2'));
    }
}
