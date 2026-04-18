<?php

namespace Tests\Unit\Error;

use Phaseolies\Error\Traces\Frame;
use PHPUnit\Framework\TestCase;

class FrameTest extends TestCase
{
    private function makeTrace(array $overrides = []): array
    {
        return array_merge([
            'file'     => __FILE__,
            'line'     => 42,
            'function' => 'testMethod',
            'class'    => 'Tests\Unit\Error\SomeClass',
            'type'     => '->',
            'args'     => ['arg1', 'arg2'],
        ], $overrides);
    }

    public function testGetFileReturnsFilePath()
    {
        $frame = new Frame($this->makeTrace());

        $this->assertEquals(__FILE__, $frame->getFile());
    }

    public function testGetLineReturnsLineNumber()
    {
        $frame = new Frame($this->makeTrace(['line' => 99]));

        $this->assertEquals(99, $frame->getLine());
    }

    public function testGetFunctionReturnsFunctionName()
    {
        $frame = new Frame($this->makeTrace(['function' => 'myFunction']));

        $this->assertEquals('myFunction', $frame->getFunction());
    }

    public function testGetClassReturnsClassName()
    {
        $frame = new Frame($this->makeTrace(['class' => 'App\Http\Controller']));

        $this->assertEquals('App\Http\Controller', $frame->getClass());
    }

    public function testGetTypeReturnsCallType()
    {
        $frame = new Frame($this->makeTrace(['type' => '::']));

        $this->assertEquals('::', $frame->getType());
    }

    public function testGetArgsReturnsArguments()
    {
        $frame = new Frame($this->makeTrace(['args' => ['foo', 'bar']]));

        $this->assertEquals(['foo', 'bar'], $frame->getArgs());
    }

    public function testDefaultsOnEmptyTrace()
    {
        $frame = new Frame([]);

        $this->assertEquals('', $frame->getFile());
        $this->assertEquals(0, $frame->getLine());
        $this->assertEquals('', $frame->getFunction());
        $this->assertEquals('', $frame->getClass());
        $this->assertEquals('', $frame->getType());
        $this->assertEquals([], $frame->getArgs());
    }

    public function testHasFileReturnsTrueWhenFileIsSet()
    {
        $frame = new Frame($this->makeTrace());

        $this->assertTrue($frame->hasFile());
    }

    public function testHasFileReturnsFalseWhenFileIsEmpty()
    {
        $frame = new Frame(['file' => '']);

        $this->assertFalse($frame->hasFile());
    }

    public function testFileExistsReturnsTrueForRealFile()
    {
        $frame = new Frame($this->makeTrace(['file' => __FILE__]));

        $this->assertTrue($frame->fileExists());
    }

    public function testFileExistsReturnsFalseForNonexistentFile()
    {
        $frame = new Frame($this->makeTrace(['file' => '/nonexistent/path/fake.php']));

        $this->assertFalse($frame->fileExists());
    }

    public function testFileExistsReturnsFalseWhenNoFile()
    {
        $frame = new Frame([]);

        $this->assertFalse($frame->fileExists());
    }

    public function testIsVendorReturnsTrueForFrameworkFile()
    {
        $frame = new Frame($this->makeTrace(['file' => '/path/to/doppar/framework/src/SomeClass.php']));

        $this->assertTrue($frame->isVendor());
    }

    public function testIsVendorReturnsFalseForAppFile()
    {
        $frame = new Frame($this->makeTrace(['file' => '/app/Http/Controllers/HomeController.php']));

        $this->assertFalse($frame->isVendor());
    }

    public function testGetCallSignatureWithClassAndType()
    {
        $frame = new Frame($this->makeTrace([
            'class'    => 'App\Controller',
            'type'     => '->',
            'function' => 'index',
        ]));

        $this->assertEquals('App\Controller->index()', $frame->getCallSignature());
    }

    public function testGetCallSignatureWithStaticCall()
    {
        $frame = new Frame($this->makeTrace([
            'class'    => 'App\Service',
            'type'     => '::',
            'function' => 'create',
        ]));

        $this->assertEquals('App\Service::create()', $frame->getCallSignature());
    }

    public function testGetCallSignatureWithFunctionOnly()
    {
        $frame = new Frame([
            'function' => 'myGlobalFunction',
            'class'    => '',
            'type'     => '',
            'args'     => [],
        ]);

        $this->assertEquals('myGlobalFunction()', $frame->getCallSignature());
    }

    public function testFromTraceCreatesFrameInstance()
    {
        $frame = Frame::fromTrace($this->makeTrace());

        $this->assertInstanceOf(Frame::class, $frame);
        $this->assertEquals(__FILE__, $frame->getFile());
    }

    public function testExtractFramesCollectionSkipsEntriesWithoutFile()
    {
        $traces = [
            $this->makeTrace(['file' => __FILE__, 'line' => 10]),
            ['function' => 'orphan_function'],
            $this->makeTrace(['file' => __FILE__, 'line' => 20]),
        ];

        $frames = Frame::extractFramesCollectionFromEngine($traces);

        $this->assertCount(2, $frames);
        $this->assertContainsOnlyInstancesOf(Frame::class, $frames);
    }

    public function testExtractFramesCollectionReturnsEmptyArrayForNoValidFrames()
    {
        $traces = [
            ['function' => 'a'],
            ['function' => 'b'],
        ];

        $frames = Frame::extractFramesCollectionFromEngine($traces);

        $this->assertEmpty($frames);
    }

    public function testGetCodeLinesReturnsEmptyArrayWhenFileDoesNotExist()
    {
        $frame = new Frame($this->makeTrace(['file' => '/nonexistent/file.php', 'line' => 5]));

        $this->assertEquals([], $frame->getCodeLines());
    }

    public function testGetCodeLinesReturnsEmptyArrayWhenLineIsZero()
    {
        $frame = new Frame($this->makeTrace(['file' => __FILE__, 'line' => 0]));

        $this->assertEquals([], $frame->getCodeLines());
    }

    public function testGetCodeLinesReturnsLinesForRealFile()
    {
        $frame = new Frame($this->makeTrace(['file' => __FILE__, 'line' => 10]));

        $lines = $frame->getCodeLines();

        $this->assertIsArray($lines);
        $this->assertNotEmpty($lines);
    }

    public function testGetShortFileRequiresBootstrappedApp()
    {
        // Define BASE_PATH to simulate bootstrapped app
        if (!defined('BASE_PATH')) {
            define('BASE_PATH', '/var/www/html');
        }

        $frame = new Frame($this->makeTrace(['file' => '/var/www/html/app/Http/Controllers/TestController.php']));

        // Test that getShortFile() works (the actual result depends on base_path() implementation)
        $result = $frame->getShortFile();
        $this->assertIsString($result);
        $this->assertNotEmpty($result);

        // Verify it contains the expected path parts
        $this->assertStringContainsString('app/Http/Controllers/TestController.php', $result);
    }

    public function testSetStateRecreatesFrame()
    {
        $data = [
            'file'     => __FILE__,
            'line'     => 55,
            'function' => 'foo',
            'class'    => 'Bar',
            'type'     => '->',
            'args'     => [],
        ];

        $frame = Frame::__set_state($data);

        $this->assertInstanceOf(Frame::class, $frame);
        $this->assertEquals(__FILE__, $frame->getFile());
        $this->assertEquals(55, $frame->getLine());
    }
}
