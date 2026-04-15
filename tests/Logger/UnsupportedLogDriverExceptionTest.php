<?php

namespace Tests\Unit\Logger;

use Phaseolies\Logger\Exceptions\UnsupportedLogDriverException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class UnsupportedLogDriverExceptionTest extends TestCase
{
    public function testExceptionExtendsInvalidArgumentException()
    {
        $e = new UnsupportedLogDriverException('unsupported driver');

        $this->assertInstanceOf(InvalidArgumentException::class, $e);
    }

    public function testExceptionStoresMessage()
    {
        $e = new UnsupportedLogDriverException('Driver [syslog] is not supported.');

        $this->assertEquals('Driver [syslog] is not supported.', $e->getMessage());
    }

    public function testExceptionDefaultsToEmptyMessage()
    {
        $e = new UnsupportedLogDriverException();

        $this->assertEquals('', $e->getMessage());
    }

    public function testExceptionStoresCode()
    {
        $e = new UnsupportedLogDriverException('msg', 42);

        $this->assertEquals(42, $e->getCode());
    }

    public function testExceptionChainsPrevious()
    {
        $previous = new \RuntimeException('root cause');
        $e        = new UnsupportedLogDriverException('wrapped', 0, $previous);

        $this->assertSame($previous, $e->getPrevious());
    }

    public function testExceptionCanBeCaughtAsInvalidArgument()
    {
        $caught = false;

        try {
            throw new UnsupportedLogDriverException('bad driver');
        } catch (InvalidArgumentException $e) {
            $caught = true;
        }

        $this->assertTrue($caught);
    }

    public function testExceptionIsThrowable()
    {
        $e = new UnsupportedLogDriverException('test');

        $this->assertInstanceOf(\Throwable::class, $e);
    }
}
