<?php

namespace Tests\Unit;

use Tests\Support\MockContainer;
use Phaseolies\Http\Controllers\Controller;
use Phaseolies\DI\Container;
use PHPUnit\Framework\TestCase;

class ControllerTest extends TestCase
{
    private Controller $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $container = new Container();
        Container::setInstance(new MockContainer());
        $container->bind('view', \Phaseolies\Support\View\Factory::class);
        $this->controller = new Controller();
    }

    public function testConstructorInitialization(): void
    {
        $this->assertInstanceOf(Controller::class, $this->controller);

        // Test that file extension is set
        $reflection = new \ReflectionClass(Controller::class);
        $fileExtensionProperty = $reflection->getProperty('fileExtension');

        $this->assertEquals('.odo.php', $fileExtensionProperty->getValue($this->controller));
    }

    public function testSetFileExtension(): void
    {
        $this->controller->setFileExtension('.php');

        $reflection = new \ReflectionClass(Controller::class);
        $fileExtensionProperty = $reflection->getProperty('fileExtension');

        $this->assertEquals('.php', $fileExtensionProperty->getValue($this->controller));
    }

    public function testSetViewFolder(): void
    {
        $this->controller->setViewFolder('custom/views');

        $reflection = new \ReflectionClass(Controller::class);
        $viewFolderProperty = $reflection->getProperty('viewFolder');

        $this->assertEquals('custom' . DIRECTORY_SEPARATOR . 'views', $viewFolderProperty->getValue($this->controller));
    }

    public function testSetEchoFormat(): void
    {
        $this->controller->setEchoFormat('custom_format(%s)');

        $reflection = new \ReflectionClass(Controller::class);
        $echoFormatProperty = $reflection->getProperty('echoFormat');

        $this->assertEquals('custom_format(%s)', $echoFormatProperty->getValue($this->controller));
    }

    public function testAddLoop(): void
    {
        $data = ['item1', 'item2', 'item3'];
        $this->controller->addLoop($data);

        $reflection = new \ReflectionClass(Controller::class);
        $loopStacksProperty = $reflection->getProperty('loopStacks');
        $loopStacks = $loopStacksProperty->getValue($this->controller);

        $this->assertCount(1, $loopStacks);
        $this->assertEquals(3, $loopStacks[0]['count']);
        $this->assertEquals(0, $loopStacks[0]['iteration']);
        $this->assertTrue($loopStacks[0]['first']);
    }

    public function testIncrementLoopIndices(): void
    {
        $data = ['item1', 'item2'];
        $this->controller->addLoop($data);
        $this->controller->incrementLoopIndices();

        $reflection = new \ReflectionClass(Controller::class);
        $loopStacksProperty = $reflection->getProperty('loopStacks');
        $loopStacks = $loopStacksProperty->getValue($this->controller);

        $this->assertEquals(1, $loopStacks[0]['iteration']);
        $this->assertEquals(0, $loopStacks[0]['index']);
        $this->assertTrue($loopStacks[0]['first']);
    }

    public function testGetFirstLoop(): void
    {
        $data = ['item1', 'item2'];
        $this->controller->addLoop($data);

        $firstLoop = $this->controller->getFirstLoop();

        $this->assertInstanceOf(\stdClass::class, $firstLoop);
        $this->assertEquals(0, $firstLoop->iteration);
        $this->assertTrue($firstLoop->first);
    }

    public function testGetFirstLoopReturnsNullWhenEmpty(): void
    {
        $this->assertNull($this->controller->getFirstLoop());
    }

    public function testCompileIf(): void
    {
        $reflection = new \ReflectionClass(Controller::class);
        $method = $reflection->getMethod('compileIf');

        $result = $method->invoke($this->controller, '($condition)');
        $this->assertEquals('<?php if($condition): ?>', $result);
    }

    public function testCompileElseif(): void
    {
        $reflection = new \ReflectionClass(Controller::class);
        $method = $reflection->getMethod('compileElseif');

        $result = $method->invoke($this->controller, '($condition)');
        $this->assertEquals('<?php elseif($condition): ?>', $result);
    }

    public function testCompileElse(): void
    {
        $reflection = new \ReflectionClass(Controller::class);
        $method = $reflection->getMethod('compileElse');

        $result = $method->invoke($this->controller);
        $this->assertEquals('<?php else: ?>', $result);
    }

    public function testCompileEndif(): void
    {
        $reflection = new \ReflectionClass(Controller::class);
        $method = $reflection->getMethod('compileEndif');

        $result = $method->invoke($this->controller);
        $this->assertEquals('<?php endif; ?>', $result);
    }

    public function testCompileUnless(): void
    {
        $reflection = new \ReflectionClass(Controller::class);
        $method = $reflection->getMethod('compileUnless');

        $result = $method->invoke($this->controller, '$condition');
        $this->assertEquals('<?php if (! $condition): ?>', $result);
    }

    public function testCompileEndunless(): void
    {
        $reflection = new \ReflectionClass(Controller::class);
        $method = $reflection->getMethod('compileEndunless');

        $result = $method->invoke($this->controller);
        $this->assertEquals('<?php endif; ?>', $result);
    }

    public function testCompileIsset(): void
    {
        $reflection = new \ReflectionClass(Controller::class);
        $method = $reflection->getMethod('compileIsset');

        $result = $method->invoke($this->controller, '($var)');
        $this->assertEquals('<?php if (isset($var)): ?>', $result);
    }

    public function testCompileEndisset(): void
    {
        $reflection = new \ReflectionClass(Controller::class);
        $method = $reflection->getMethod('compileEndisset');

        $result = $method->invoke($this->controller);
        $this->assertEquals('<?php endif; ?>', $result);
    }

    public function testCompileSwitch(): void
    {
        $reflection = new \ReflectionClass(Controller::class);
        $method = $reflection->getMethod('compileSwitch');

        $result = $method->invoke($this->controller, '($value)');
        $this->assertEquals('<?php switch($value):', $result);
    }

    public function testCompileDefault(): void
    {
        $reflection = new \ReflectionClass(Controller::class);
        $method = $reflection->getMethod('compileDefault');

        $result = $method->invoke($this->controller);
        $this->assertEquals('<?php default: ?>', $result);
    }

    public function testCompileBreakWithoutCondition(): void
    {
        $reflection = new \ReflectionClass(Controller::class);
        $method = $reflection->getMethod('compileBreak');

        $result = $method->invoke($this->controller, '');
        $this->assertEquals('<?php break; ?>', $result);
    }

    public function testCompileBreakWithCondition(): void
    {
        $reflection = new \ReflectionClass(Controller::class);
        $method = $reflection->getMethod('compileBreak');

        $result = $method->invoke($this->controller, '($condition)');
        $this->assertEquals('<?php if($condition) break; ?>', $result);
    }

    public function testCompileEndswitch(): void
    {
        $reflection = new \ReflectionClass(Controller::class);
        $method = $reflection->getMethod('compileEndswitch');

        $result = $method->invoke($this->controller);
        $this->assertEquals('<?php endswitch; ?>', $result);
    }

    public function testCompileContinueWithoutCondition(): void
    {
        $reflection = new \ReflectionClass(Controller::class);
        $method = $reflection->getMethod('compileContinue');

        $result = $method->invoke($this->controller, '');
        $this->assertEquals('<?php continue; ?>', $result);
    }

    public function testCompileContinueWithCondition(): void
    {
        $reflection = new \ReflectionClass(Controller::class);
        $method = $reflection->getMethod('compileContinue');

        $result = $method->invoke($this->controller, '($condition)');
        $this->assertEquals('<?php if($condition) continue; ?>', $result);
    }

    public function testCompilePhp(): void
    {
        $reflection = new \ReflectionClass(Controller::class);
        $method = $reflection->getMethod('compilePhp');

        $result = $method->invoke($this->controller, '$var = "value"');
        $this->assertEquals('<?php $var = "value"; ?>', $result);
    }

    public function testCompileJson(): void
    {
        $reflection = new \ReflectionClass(Controller::class);
        $method = $reflection->getMethod('compileJson');

        $result = $method->invoke($this->controller, '($data)');
        $this->assertStringContainsString('echo json_encode($data,', $result);
    }

    public function testCompileUnset(): void
    {
        $reflection = new \ReflectionClass(Controller::class);
        $method = $reflection->getMethod('compileUnset');

        $result = $method->invoke($this->controller, '($var)');
        $this->assertEquals('<?php unset($var); ?>', $result);
    }

    public function testCompileFor(): void
    {
        $reflection = new \ReflectionClass(Controller::class);
        $method = $reflection->getMethod('compileFor');

        $result = $method->invoke($this->controller, '($i = 0; $i < 10; $i++)');
        $this->assertEquals('<?php for($i = 0; $i < 10; $i++): ?>', $result);
    }

    public function testCompileEndfor(): void
    {
        $reflection = new \ReflectionClass(Controller::class);
        $method = $reflection->getMethod('compileEndfor');

        $result = $method->invoke($this->controller);
        $this->assertEquals('<?php endfor; ?>', $result);
    }

    public function testCompileForeach(): void
    {
        $reflection = new \ReflectionClass(Controller::class);
        $method = $reflection->getMethod('compileForeach');

        $result = $method->invoke($this->controller, '($items as $item)');
        $this->assertStringContainsString('foreach', $result);
        $this->assertStringContainsString('$this->addLoop', $result);
    }

    public function testCompileEndforeach(): void
    {
        $reflection = new \ReflectionClass(Controller::class);
        $method = $reflection->getMethod('compileEndforeach');

        $result = $method->invoke($this->controller);
        $this->assertEquals('<?php endforeach; $this->popLoop(); ?>', $result);
    }

    public function testCompileWhile(): void
    {
        $reflection = new \ReflectionClass(Controller::class);
        $method = $reflection->getMethod('compileWhile');

        $result = $method->invoke($this->controller, '($condition)');
        $this->assertEquals('<?php while($condition): ?>', $result);
    }

    public function testCompileEndwhile(): void
    {
        $reflection = new \ReflectionClass(Controller::class);
        $method = $reflection->getMethod('compileEndwhile');

        $result = $method->invoke($this->controller);
        $this->assertEquals('<?php endwhile; ?>', $result);
    }

    public function testCompileGuest(): void
    {
        $result = $this->controller->compileGuest();
        $this->assertStringContainsString('!\\Phaseolies\\Support\\Facades\\Auth::check()', $result);
    }

    public function testReplacePhpBlocks(): void
    {
        $content = '#php echo "test"; #endphp';
        $result = $this->controller->replacePhpBlocks($content);
        $this->assertEquals('<?php echo "test"; ?>', $result);
    }

    public function testCompileEchos(): void
    {
        $reflection = new \ReflectionClass(Controller::class);
        $method = $reflection->getMethod('compileEchos');

        $content = '[[ $variable ]]';
        $result = $method->invoke($this->controller, $content);
        $this->assertStringContainsString('echo', $result);
        $this->assertStringContainsString('$variable', $result);
    }
}
