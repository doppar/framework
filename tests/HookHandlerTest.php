<?php

namespace Tests\Unit;

use RuntimeException;
use Phaseolies\Database\Eloquent\Model;
use Phaseolies\Database\Eloquent\Hooks\HookHandler;
use Phaseolies\DI\Container;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;
use function PHPUnit\Framework\assertTrue;

class HookHandlerTest extends TestCase
{
    protected function tearDown(): void
    {
        // Clear registered hooks after each test
        HookHandler::$hooks = [];
        parent::tearDown();
    }

    public function testRegisterCallableHandler()
    {
        $model = new class extends Model {};
        $callable = function ($model) {};

        HookHandler::register(get_class($model), [
            'before_created' => $callable
        ]);

        $this->assertCount(1, HookHandler::$hooks[get_class($model)]['before_created']);
        $this->assertEquals($callable, HookHandler::$hooks[get_class($model)]['before_created'][0]['handler']);
        $this->assertTrue(HookHandler::$hooks[get_class($model)]['before_created'][0]['when']);
    }

    public function testRegisterStringHandler()
    {
        $model = new class extends Model {};
        $handler = 'App\Handlers\TestHandler';

        HookHandler::register(get_class($model), [
            'before_created' => $handler
        ]);

        $this->assertCount(1, HookHandler::$hooks[get_class($model)]['before_created']);
        $this->assertEquals($handler, HookHandler::$hooks[get_class($model)]['before_created'][0]['handler']);
        $this->assertTrue(HookHandler::$hooks[get_class($model)]['before_created'][0]['when']);
    }

    public function testRegisterArrayHandler()
    {
        $model = new class extends Model {};
        $callable = function ($model) {};
        $condition = function ($model) {
            return true;
        };

        HookHandler::register(get_class($model), [
            'booting' => [
                'handler' => $callable,
                'when' => $condition
            ]
        ]);

        $this->assertCount(1, HookHandler::$hooks[get_class($model)]['booting']);
        $this->assertEquals($callable, HookHandler::$hooks[get_class($model)]['booting'][0]['handler']);
        $this->assertEquals($condition, HookHandler::$hooks[get_class($model)]['booting'][0]['when']);
    }

    public function testRegisterPreventsDuplicateHooks()
    {
        $model = new class extends Model {};
        $callable = function ($model) {};

        HookHandler::register(get_class($model), [
            'before_updated' => $callable
        ]);
        HookHandler::register(get_class($model), [
            'before_updated' => $callable
        ]);

        $this->assertCount(1, HookHandler::$hooks[get_class($model)]['before_updated']);
    }

    public function testRegisterThrowsExceptionForInvalidHandler()
    {
        $this->expectException(InvalidArgumentException::class);

        $model = new class extends Model {};
        HookHandler::register(get_class($model), [
            'saving' => 123 // invalid handler
        ]);
    }

    public function testExecuteWithCallableHandler()
    {
        $model = new class extends Model {};
        $called = false;
        $callable = function ($model) use (&$called) {
            $called = true;
        };

        HookHandler::register(get_class($model), [
            'booted' => $callable
        ]);

        HookHandler::execute('booted', $model);
        $this->assertTrue($called);
    }

    public function testExecuteWithStringHandler()
    {
        $model = new class extends Model {};
        $handler = new class {
            public $handled = false;
            public function handle($model)
            {
                $this->handled = true;
            }
        };

        // Get the container instance and binding handler
        $container = Container::getInstance();
        $container->bind(get_class($handler), function () use ($handler) {
            return $handler;
        });

        HookHandler::register(get_class($model), [
            'saving' => get_class($handler)
        ]);

        HookHandler::execute('saving', $model);
        $this->assertTrue($handler->handled);
    }

    public function testExecuteWithCondition()
    {
        $model = new class extends Model {};
        $called = false;
        $callable = function ($model) use (&$called) {
            $called = true;
        };
        $condition = function ($model) {
            return false;
        };

        HookHandler::register(get_class($model), [
            'saving' => [
                'handler' => $callable,
                'when' => $condition
            ]
        ]);

        HookHandler::execute('saving', $model);
        $this->assertFalse($called);
    }

    public function testShouldExecuteWithBooleanCondition()
    {
        $model = new class extends Model {};

        // shouldExecute protected method, tested by making it public
        $this->assertTrue(HookHandler::shouldExecuteForUnitTest($model, true));
        $this->assertFalse(HookHandler::shouldExecuteForUnitTest($model, false));
    }

    public function testShouldExecuteWithCallableCondition()
    {
        $model = new class extends Model {
            public $test = true;
        };

        $condition = function ($model) {
            return $model->test;
        };
        $this->assertTrue(HookHandler::shouldExecuteForUnitTest($model, $condition));
    }

    public function testShouldExecuteThrowsForNonBooleanConditionResult()
    {
        $this->expectException(RuntimeException::class);

        $model = new class extends Model {};
        $condition = function ($model) {
            return 'not boolean';
        };

        HookHandler::shouldExecuteForUnitTest($model, $condition);
    }

    // public function testExecuteHandlerThrowsForInvalidHandler()
    // {
    //     $this->expectException(RuntimeException::class);

    //     $model = new class extends Model {};

    //     // protected method executeHandler
    //     // tested by making it public 
    //     // HookHandler::executeHandler(123, $model);
    // }
}
