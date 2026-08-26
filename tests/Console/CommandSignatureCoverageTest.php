<?php

namespace Tests\Unit\Console;

require_once __DIR__ . '/Support/CommandTestEnvironment.php';

use Phaseolies\Application;
use Phaseolies\Console\Commands\VendorPublishCommand;
use Phaseolies\Database\Migration\MigrationCreator;
use Phaseolies\Database\Migration\Migrator;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Tests\Unit\Console\Support\CommandTestEnvironment as Env;

#[AllowMockObjectsWithoutExpectations]
class CommandSignatureCoverageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Env::reset();
        Env::$appInstance = $this->createStub(Application::class);
        Env::bind('migrator', $this->createStub(Migrator::class));
        Env::bind('config', new class
        {
            public int $clearCount = 0;

            public function clearCache(): void
            {
                $this->clearCount++;
            }
        });
    }

    protected function tearDown(): void
    {
        Env::cleanup();

        parent::tearDown();
    }

    #[DataProvider('commandClassProvider')]
    public function testEveryCommandClassHasParsedSignatureAndDescription(string $className): void
    {
        $command = $this->instantiateCommand($className);
        $reflection = new \ReflectionClass($className);

        $signature = $this->normalizeWhitespace($this->readProtectedProperty($reflection, $command, 'name'));
        $description = trim((string) $this->readProtectedProperty($reflection, $command, 'description'));

        $this->assertNotSame('', $signature, $className . ' should define a command signature');
        $this->assertNotSame('', $description, $className . ' should define a description');
        $this->assertTrue($reflection->hasMethod('handle'), $className . ' should define handle()');
        $this->assertSame(strtok($signature, ' '), $command->getName());

        preg_match_all('/{([^}]+)}/', $signature, $matches);
        $tokens = $matches[1];

        foreach ($tokens as $token) {
            $token = trim($token);

            if (str_contains($token, ':')) {
                [$token] = explode(':', $token, 2);
                $token = trim($token);
            }

            if (preg_match('/^(?:-([a-zA-Z])\|)?--([\w-]+)(?:=(.*))?$/', $token, $optionMatches)) {
                $option = $command->getDefinition()->getOption($optionMatches[2]);

                $this->assertInstanceOf(InputOption::class, $option);
                if (isset($optionMatches[1]) && $optionMatches[1] !== '') {
                    $this->assertSame($optionMatches[1], $option->getShortcut());
                } else {
                    $this->assertEmpty($option->getShortcut());
                }
                $expectsValue = str_contains($token, '=');
                $this->assertSame($expectsValue, $option->acceptValue(), $className . ' option ' . $optionMatches[2]);
                continue;
            }

            if (preg_match('/^(\w+)(\?)?$/', $token, $argumentMatches)) {
                $argument = $command->getDefinition()->getArgument($argumentMatches[1]);

                $this->assertInstanceOf(InputArgument::class, $argument);
                $expectedRequired = !isset($argumentMatches[2]) || $argumentMatches[2] !== '?';
                $this->assertSame($expectedRequired, $argument->isRequired(), $className . ' argument ' . $argumentMatches[1]);
            }
        }
    }

    public static function commandClassProvider(): array
    {
        $classes = [];
        $root = realpath(__DIR__ . '/../../src/Phaseolies/Console/Commands');

        foreach (self::commandFiles($root ?: '') as $file) {
            $relative = substr($file, strlen($root) + 1);
            $class = 'Phaseolies\\Console\\Commands\\' . str_replace(
                ['/', '.php'],
                ['\\', ''],
                $relative
            );

            $classes[$class] = [$class];
        }

        ksort($classes);

        return $classes;
    }

    private static function commandFiles(string $root): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if (!$item->isFile() || $item->getExtension() !== 'php') {
                continue;
            }

            $files[] = str_replace('\\', '/', $item->getPathname());
        }

        sort($files);

        return $files;
    }

    private function instantiateCommand(string $className): object
    {
        if ($className === VendorPublishCommand::class) {
            Env::$appInstance = $this->createStub(Application::class);
        }

        $reflection = new \ReflectionClass($className);
        $constructor = $reflection->getConstructor();

        if (
            $constructor === null
            || $constructor->getDeclaringClass()->getName() !== $className
            || $constructor->getNumberOfParameters() === 0
        ) {
            return $reflection->newInstance();
        }

        $dependencies = [];

        foreach ($constructor->getParameters() as $parameter) {
            $typeName = $parameter->getType()?->getName();

            $dependencies[] = match ($typeName) {
                MigrationCreator::class => $this->createStub(MigrationCreator::class),
                default => throw new \RuntimeException('Unhandled constructor dependency for ' . $className . ': ' . $typeName),
            };
        }

        return $reflection->newInstanceArgs($dependencies);
    }

    private function readProtectedProperty(\ReflectionClass $reflection, object $instance, string $property): mixed
    {
        $prop = $reflection->getProperty($property);

        return $prop->getValue($instance);
    }

    private function normalizeWhitespace(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $value));
    }
}
