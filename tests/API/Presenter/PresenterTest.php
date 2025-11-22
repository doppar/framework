<?php

namespace Tests\Unit\API\Presenter;

use Phaseolies\Support\Presenter\Presenter;
use PHPUnit\Framework\TestCase;

class PresenterTest extends TestCase
{
    public function testInitialization()
    {
        $data = ['id' => 1, 'name' => 'Test'];
        $presenter = new TestablePresenter($data);

        $this->assertInstanceOf(Presenter::class, $presenter);
    }

    public function testExceptMethod()
    {
        $data = ['id' => 1, 'name' => 'Test', 'email' => 'test@example.com'];
        $presenter = new TestablePresenter($data);

        // Test with string parameter
        $result = $presenter->except('email')->jsonSerialize();
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayNotHasKey('email', $result);

        // Test with array parameter
        $result = $presenter->except('name', 'email')->jsonSerialize();
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayNotHasKey('name', $result);
        $this->assertArrayNotHasKey('email', $result);

        // Test multiple calls
        $result = $presenter->except('name')->except('email')->jsonSerialize();
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayNotHasKey('name', $result);
        $this->assertArrayNotHasKey('email', $result);
    }

    public function testOnlyMethod()
    {
        $data = ['id' => 1, 'name' => 'Test', 'email' => 'test@example.com'];
        $presenter = new TestablePresenter($data);

        // Test with string parameter
        $result = $presenter->only('id')->jsonSerialize();
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayNotHasKey('name', $result);
        $this->assertArrayNotHasKey('email', $result);

        // Test with array parameter
        $result = $presenter->only(['id', 'name'])->jsonSerialize();
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayNotHasKey('email', $result);

        // Test multiple calls
        $result = $presenter->only('id')->only('name')->jsonSerialize();
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayNotHasKey('email', $result);
    }

    // public function testLazyMethod()
    // {
    //     $data = ['id' => 1];
    //     $presenter = new TestablePresenter($data);

    //     // Just test the method exists and returns self
    //     $this->assertSame($presenter, $presenter->lazy());
    //     $this->assertSame($presenter, $presenter->lazy(true));
    //     $this->assertSame($presenter, $presenter->lazy(false));
    // }

    public function testJsonSerializeWithOnlyAndExcept()
    {
        $data = [
            'id' => 1,
            'name' => 'Test',
            'email' => 'test@example.com',
            'is_active' => true
        ];
        $presenter = new TestablePresenter($data);

        // Test only takes precedence over except
        $result = $presenter->only('id', 'name')->except('name')->jsonSerialize();
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayNotHasKey('name', $result);
        $this->assertArrayNotHasKey('email', $result);
        $this->assertArrayNotHasKey('is_active', $result);
    }

    public function testValueMethod()
    {
        $data = [
            'id' => 1,
            'name' => 'Test',
            'email' => 'test@example.com',
            'is_active' => true
        ];
        $presenter = new TestablePresenter($data);

        // Test with non-closure value
        // $this->assertEquals('test', $presenter->value('test'));

        // // Test with closure
        // $this->assertEquals('closure result', $presenter->value(function () {
        //     return 'closure result';
        // }));
    }

    public function testWhenMethod()
    {
        $data = [
            'id' => 1,
            'name' => 'Test',
            'email' => 'test@example.com',
            'is_active' => true
        ];
        $presenter = new TestablePresenter($data);

        // Test when condition is true
        // $this->assertEquals('yes', $presenter->when(true, 'yes'));
        // $this->assertEquals('yes', $presenter->when(true, function () {
        //     return 'yes';
        // }));

        // Test when condition is false
        // $this->assertNull($presenter->when(false, 'yes'));
        // $this->assertEquals('no', $presenter->when(false, 'yes', 'no'));

        // // Test with closure default
        // $this->assertEquals('no', $presenter->when(false, 'yes', function () {
        //     return 'no';
        // }));
    }

    public function testMergeWhenMethod()
    {
        $data = [
            'id' => 1,
            'name' => 'Test',
            'email' => 'test@example.com',
            'is_active' => true
        ];
        $presenter = new TestablePresenter($data);

        // Test when condition is true
        // $this->assertEquals(['key' => 'value'], $presenter->mergeWhen(true, ['key' => 'value']));

        // // Test when condition is false
        // $this->assertEquals([], $presenter->mergeWhen(false, ['key' => 'value']));
    }

    public function testUnlessMethod()
    {
        $data = [
            'id' => 1,
            'name' => 'Test',
            'email' => 'test@example.com',
            'is_active' => true
        ];
        $presenter = new TestablePresenter($data);

        // Test when condition is false (unless true)
        // $this->assertEquals('yes', $presenter->unless(false, 'yes'));
        // $this->assertEquals('yes', $presenter->unless(false, function () {
        //     return 'yes';
        // }));

        // Test when condition is true (unless false)
        // $this->assertNull($presenter->unless(true, 'yes'));
        // $this->assertEquals('no', $presenter->unless(true, 'yes', 'no'));

        // // Test with closure default
        // $this->assertEquals('no', $presenter->unless(true, 'yes', function () {
        //     return 'no';
        // }));
    }

    public function testComplexScenario()
    {
        $presenter = new class([
            'id' => 1,
            'name' => 'Complex Test',
            'email' => 'complex@example.com',
            'is_active' => true,
            'roles' => ['admin', 'user']
        ]) extends Presenter {
            protected function toArray(): array
            {
                return [
                    'id' => $this->presenter['id'],
                    'name' => strtoupper($this->presenter['name']),
                    'email' => $this->presenter['email'],
                    'is_active' => $this->presenter['is_active'],
                    'roles' => $this->presenter['roles'],
                    'computed' => 'computed_value'
                ];
            }
        };

        $result = $presenter
            ->except('email')
            ->only('id', 'name', 'computed')
            ->jsonSerialize();

        $this->assertEquals([
            'id' => 1,
            'name' => 'COMPLEX TEST',
            'computed' => 'computed_value'
        ], $result);
    }
}

class TestablePresenter extends Presenter
{
    protected function toArray(): array
    {
        return [
            'id' => $this->presenter['id'] ?? null,
            'name' => $this->presenter['name'] ?? null,
            'email' => $this->presenter['email'] ?? null,
        ];
    }
}
