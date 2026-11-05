<?php

namespace Tests\Unit\Console;

use Phaseolies\Application;
use Phaseolies\Console\Command as CommandLoader;
use Phaseolies\Console\Console;
use Phaseolies\Console\Schedule\Command;
use Phaseolies\DI\Container;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Support\MockContainer;

abstract class GrammarCommand extends Command
{
    public array $seen = [];

    public function handle(): int
    {
        $this->seen = ['arguments' => $this->argument(), 'options' => $this->option()];

        return 0;
    }
}

class CommandSignatureGrammarTest extends TestCase
{
    protected function setUp(): void
    {
        Container::setInstance(new MockContainer());
    }

    protected function tearDown(): void
    {
        (new \ReflectionClass(Container::class))->getProperty('instance')->setValue(null, null);
    }

    private function command(string $signature): GrammarCommand
    {
        return new class($signature) extends GrammarCommand {
            public function __construct(string $signature)
            {
                $this->name = $signature;
                parent::__construct();
            }
        };
    }

    private function run_(string $signature, array $input = []): GrammarCommand
    {
        $command = $this->command($signature);
        (new CommandTester($command))->execute($input, ['interactive' => false]);

        return $command;
    }

    public function testPlainArgumentsAndOptionsStillWork(): void
    {
        $command = $this->run_('plain {user} {nick?} {--force} {-F|--flag} {--queue=} {--mode=fast}', [
            'user' => 'ada', '--force' => true, '--queue' => 'high',
        ]);

        $this->assertSame('ada', $command->seen['arguments']['user']);
        $this->assertNull($command->seen['arguments']['nick']);
        $this->assertTrue($command->seen['options']['force']);
        $this->assertFalse($command->seen['options']['flag']);
        $this->assertSame('high', $command->seen['options']['queue']);
        $this->assertSame('fast', $command->seen['options']['mode']);
        $this->assertSame('F', $command->getDefinition()->getOption('flag')->getShortcut());
    }

    public function testArgumentDefault(): void
    {
        $this->assertSame('guest', $this->run_('a {name=guest}')->seen['arguments']['name']);
        $this->assertSame('ada', $this->run_('a {name=guest}', ['name' => 'ada'])->seen['arguments']['name']);
        $this->assertFalse($this->command('a {name=guest}')->getDefinition()->getArgument('name')->isRequired());
    }

    public function testRequiredArrayArgument(): void
    {
        $definition = $this->command('a {files*}')->getDefinition()->getArgument('files');

        $this->assertTrue($definition->isArray());
        $this->assertTrue($definition->isRequired());
        $this->assertSame(['a.txt', 'b.txt'], $this->run_('a {files*}', ['files' => ['a.txt', 'b.txt']])->seen['arguments']['files']);
    }

    public function testOptionalArrayArgument(): void
    {
        $this->assertSame([], $this->run_('a {files?*}')->seen['arguments']['files']);
        $this->assertSame(['x'], $this->run_('a {files?*}', ['files' => ['x']])->seen['arguments']['files']);
    }

    public function testArrayOption(): void
    {
        $definition = $this->command('a {--tag=*}')->getDefinition()->getOption('tag');

        $this->assertTrue($definition->isArray());
        $this->assertSame([], $definition->getDefault());
        $this->assertSame([], $this->run_('a {--tag=*}')->seen['options']['tag']);
        $this->assertSame(['x', 'y'], $this->run_('a {--tag=*}', ['--tag' => ['x', 'y']])->seen['options']['tag']);
    }

    public function testArrayOptionWithShortcut(): void
    {
        $this->assertSame('t', $this->command('a {-t|--tag=*}')->getDefinition()->getOption('tag')->getShortcut());
    }

    public function testNegatableOption(): void
    {
        $definition = $this->command('a {--cache!}')->getDefinition()->getOption('cache');

        $this->assertTrue($definition->isNegatable());
        $this->assertNull($this->run_('a {--cache!}')->seen['options']['cache']);
        $this->assertTrue($this->run_('a {--cache!}', ['--cache' => true])->seen['options']['cache']);
        $this->assertFalse($this->run_('a {--cache!}', ['--no-cache' => true])->seen['options']['cache']);
    }

    public function testNegatableOptionWithBooleanDefault(): void
    {
        $this->assertTrue($this->run_('a {--cache!=true}')->seen['options']['cache']);
        $this->assertFalse($this->run_('a {--cache!=true}', ['--no-cache' => true])->seen['options']['cache']);
        $this->assertFalse($this->run_('a {--cache!=false}')->seen['options']['cache']);
        $this->assertTrue($this->run_('a {--cache!=false}', ['--cache' => true])->seen['options']['cache']);
    }

    public function testDescriptionsAreKept(): void
    {
        $definition = $this->command('a {name : Who to greet} {--tag=* : Labels to attach} {--cache! : Use the cache}')->getDefinition();

        $this->assertSame('Who to greet', $definition->getArgument('name')->getDescription());
        $this->assertSame('Labels to attach', $definition->getOption('tag')->getDescription());
        $this->assertSame('Use the cache', $definition->getOption('cache')->getDescription());
    }

    public function testEverythingTogether(): void
    {
        $command = $this->run_('deploy {env=staging} {services?*} {--tag=*} {--cache!=true} {-F|--force}', [
            'services' => ['api', 'web'], '--tag' => ['v1'], '--no-cache' => true, '--force' => true,
        ]);

        $this->assertSame('staging', $command->seen['arguments']['env']);
        $this->assertSame(['api', 'web'], $command->seen['arguments']['services']);
        $this->assertSame(['v1'], $command->seen['options']['tag']);
        $this->assertFalse($command->seen['options']['cache']);
        $this->assertTrue($command->seen['options']['force']);
    }

    #[DataProvider('invalidDefinitions')]
    public function testInvalidDefinitionsFailLoudlyInsteadOfBeingDropped(string $signature, string $message): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage($message);

        $this->command($signature);
    }

    public static function invalidDefinitions(): array
    {
        return [
            'junk argument' => ['a {not valid}', 'Invalid definition "{not valid}"'],
            'junk option' => ['a {--bad option}', 'Invalid definition "{--bad option}"'],
            'array with a default' => ['a {files*=x}', 'an array argument cannot have a default'],
            'negatable with a value' => ['a {--cache!=maybe}', 'can only default to true or false'],
            'single dash long name' => ['a {-force}', 'Invalid definition "{-force}"'],
        ];
    }

    public function testSymfonyOrderingRulesStillApply(): void
    {
        $this->expectException(\Symfony\Component\Console\Exception\LogicException::class);

        $this->command('a {files*} {after}');
    }

    public function testTheOriginalExceptionIsRethrownNotAGenericOne(): void
    {
        $original = new \DomainException('the real problem', 7);

        $command = new class($original) extends Command {
            public function __construct(private \Throwable $error)
            {
                $this->name = 'fail';
                parent::__construct();
            }

            public function handle(): int
            {
                throw $this->error;
            }
        };

        try {
            (new CommandTester($command))->execute([]);
            $this->fail('Expected the exception to propagate');
        } catch (\Throwable $e) {
            $this->assertSame($original, $e, 'class, code and trace must survive');
        }
    }

    public function testAFailingLoggerDoesNotHideTheRealError(): void
    {
        // The test container has no `log` binding, so Log::error() itself throws.
        $command = new class extends Command {
            protected $name = 'fail';

            public function handle(): int
            {
                throw new \RuntimeException('the real problem');
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('the real problem');

        (new CommandTester($command))->execute([]);
    }

    public function testIsCommandClassIgnoresHelpersAbstractsTraitsAndInterfaces(): void
    {
        $loader = new class($this->createStub(Application::class)) extends CommandLoader {
            public function check(string $class): bool
            {
                return $this->isCommandClass($class);
            }
        };

        $this->assertTrue($loader->check(\Phaseolies\Console\Commands\Server\ServerStartCommand::class));
        $this->assertFalse($loader->check(GrammarCommand::class), 'abstract');
        $this->assertFalse($loader->check(\Phaseolies\Console\Support\InteractsWithMigrations::class), 'trait');
        $this->assertFalse($loader->check(\Phaseolies\Console\Console::class), 'not a Command');
        $this->assertFalse($loader->check(\Stringable::class), 'interface');
        $this->assertFalse($loader->check('App\\Does\\Not\\Exist'), 'missing');
    }

    public function testOneBrokenCommandDoesNotStopTheOthersFromLoading(): void
    {
        $broken = \Phaseolies\Console\Commands\Server\ServerStartCommand::class;

        $app = $this->createStub(Application::class);
        $app->method('make')->willReturnCallback(function (string $class) use ($broken) {
            if ($class === $broken) {
                throw new \RuntimeException('cannot be built');
            }

            return new SymfonyCommand('stub:' . md5($class));
        });

        $loader = new class($app) extends CommandLoader {
            public array $skipped = [];

            protected function reportSkippedCommand(string $class, \Throwable $e): void
            {
                $this->skipped[$class] = $e->getMessage();
            }
        };

        $console = new Console($app);
        $loader->registerCommands($console);

        $this->assertSame([$broken => 'cannot be built'], $loader->skipped);
        $this->assertGreaterThan(40, count($console->all()), 'the other commands are still registered');
    }
}
