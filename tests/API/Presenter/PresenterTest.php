<?php

namespace Tests\Unit\API\Presenter;

use Phaseolies\Support\Presenter\Presenter;
use Phaseolies\Support\Collection;
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

    public function testValueMethod(): void
    {
        $presenter = new TestablePresenter([]);

        $this->assertSame('test', $presenter->exposeValue('test'));
        $this->assertSame('closure result', $presenter->exposeValue(fn() => 'closure result'));
    }

    public function testWhenMethod(): void
    {
        $presenter = new TestablePresenter([]);

        $this->assertSame('yes', $presenter->exposeWhen(true, 'yes'));
        $this->assertSame('yes', $presenter->exposeWhen(true, fn() => 'yes'));
        $this->assertNull($presenter->exposeWhen(false, 'yes'));
        $this->assertSame('no', $presenter->exposeWhen(false, 'yes', 'no'));
        $this->assertSame('no', $presenter->exposeWhen(false, 'yes', fn() => 'no'));
    }

    public function testMergeWhenMethod(): void
    {
        $presenter = new TestablePresenter([]);

        $this->assertSame(['key' => 'value'], $presenter->exposeMergeWhen(true, ['key' => 'value']));
        $this->assertSame([], $presenter->exposeMergeWhen(false, ['key' => 'value']));
    }

    public function testUnlessMethod(): void
    {
        $presenter = new TestablePresenter([]);

        $this->assertSame('yes', $presenter->exposeUnless(false, 'yes'));
        $this->assertSame('yes', $presenter->exposeUnless(false, fn() => 'yes'));
        $this->assertNull($presenter->exposeUnless(true, 'yes'));
        $this->assertSame('no', $presenter->exposeUnless(true, 'yes', 'no'));
    }

    public function testArrayBackedPresenterSupportsMagicPropertyAccess(): void
    {
        $presenter = new TestablePresenter(['name' => 'Test']);

        $this->assertSame('Test', $presenter->name);
        $this->assertNull($presenter->missing);
    }

    public function testConditionalHelpersEvaluateValuesAndDefaults(): void
    {
        $presenter = new TestablePresenter([]);

        $this->assertSame('yes', $presenter->exposeWhen(true, fn() => 'yes'));
        $this->assertSame('no', $presenter->exposeWhen(false, 'yes', fn() => 'no'));
        $this->assertNull($presenter->exposeWhen(false, 'yes'));
        $this->assertSame('yes', $presenter->exposeUnless(false, fn() => 'yes'));
        $this->assertSame(['key' => 'value'], $presenter->exposeMergeWhen(true, ['key' => 'value']));
        $this->assertSame([], $presenter->exposeMergeWhen(false, ['key' => 'value']));
    }

    public function testNestedJsonValuesAreNormalizedRecursively(): void
    {
        $jsonValue = new class implements \JsonSerializable {
            public function jsonSerialize(): array
            {
                return ['value' => 'nested'];
            }
        };

        $presenter = new class([$jsonValue]) extends Presenter {
            protected function toArray(): array
            {
                return [
                    'object' => $this->presenter[0],
                    'array' => [$this->presenter[0]],
                    'collection' => new Collection(static::class, [$this->presenter[0]]),
                ];
            }
        };

        $this->assertSame([
            'object' => ['value' => 'nested'],
            'array' => [['value' => 'nested']],
            'collection' => [['value' => 'nested']],
        ], $presenter->jsonSerialize());
    }

    public function testConditionalHelpersDoNotEvaluateUnusedClosures(): void
    {
        $presenter = new TestablePresenter([]);
        $called = false;
        $closure = function () use (&$called): string {
            $called = true;
            return 'unused';
        };

        $this->assertNull($presenter->exposeWhen(false, $closure));
        $this->assertFalse($called);
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
    public function exposeValue($value)
    {
        return $this->value($value);
    }

    public function exposeWhen(bool $condition, $value, $default = null)
    {
        return $this->when($condition, $value, $default);
    }

    public function exposeUnless(bool $condition, $value, $default = null)
    {
        return $this->unless($condition, $value, $default);
    }

    public function exposeMergeWhen(bool $condition, array $value): array
    {
        return $this->mergeWhen($condition, $value);
    }

    protected function toArray(): array
    {
        return [
            'id' => $this->presenter['id'] ?? null,
            'name' => $this->presenter['name'] ?? null,
            'email' => $this->presenter['email'] ?? null,
        ];
    }
}
