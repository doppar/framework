<?php

namespace Tests\Unit;

use Phaseolies\Utilities\RealLens;
use PHPUnit\Framework\TestCase;

class RealLensTest extends TestCase
{
    protected RealLens $lens;

    protected function setUp(): void
    {
        $this->lens = new RealLens();
    }

    public function testGrab()
    {
        $array = ['user' => ['name' => 'John', 'age' => 30]];

        $this->assertEquals('John', $this->lens->grab($array, 'user.name'));
        $this->assertEquals(30, $this->lens->grab($array, 'user.age'));
        $this->assertNull($this->lens->grab($array, 'user.email'));
        $this->assertEquals('default', $this->lens->grab($array, 'user.email', 'default'));
    }

    public function testPut()
    {
        $array = [];
        $this->lens->put($array, 'user.name', 'John');
        $this->assertEquals(['user' => ['name' => 'John']], $array);

        $this->lens->put($array, 'user.age', 30);
        $this->assertEquals(['user' => ['name' => 'John', 'age' => 30]], $array);
    }

    public function testGot()
    {
        $array = ['user' => ['name' => 'John', 'age' => 30]];

        $this->assertTrue($this->lens->got($array, 'user.name'));
        $this->assertTrue($this->lens->got($array, ['user.name', 'user.age']));
        $this->assertFalse($this->lens->got($array, 'user.email'));
        $this->assertFalse($this->lens->got($array, ['user.name', 'user.email']));
    }

    public function testSome()
    {
        $array = ['user' => ['name' => 'John', 'age' => 30]];

        $this->assertTrue($this->lens->some($array, 'user.name'));
        $this->assertTrue($this->lens->some($array, ['user.email', 'user.age']));
        $this->assertFalse($this->lens->some($array, 'user.email'));
        $this->assertFalse($this->lens->some($array, ['user.email', 'user.address']));
    }

    public function testZap()
    {
        $array = ['user' => ['name' => 'John', 'age' => 30, 'email' => 'john@example.com']];
        $this->lens->zap($array, 'user.email');
        $this->assertEquals(['user' => ['name' => 'John', 'age' => 30]], $array);

        $array = ['users' => [1 => ['name' => 'John'], 2 => ['name' => 'Jane']]];
        $this->lens->zap($array, 'users.1');
        $this->assertEquals(['users' => [2 => ['name' => 'Jane']]], $array);
    }

    public function testPick()
    {
        $users = [
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane']
        ];

        $names = $this->lens->pick($users, 'name');
        $this->assertEquals(['John', 'Jane'], $names);

        $keyedNames = $this->lens->pick($users, 'name', 'id');
        $this->assertEquals([1 => 'John', 2 => 'Jane'], $keyedNames);
    }

    public function testFlat()
    {
        $array = [1, [2, [3, [4, 5]]]];

        $this->assertEquals([1, 2, 3, 4, 5], $this->lens->flat($array));
        $this->assertEquals([1, 2, [3, [4, 5]]], $this->lens->flat($array, 1));
        $this->assertEquals([1, 2, 3, [4, 5]], $this->lens->flat($array, 2));
    }

    public function testHead()
    {
        $array = [1, 2, 3];

        $this->assertEquals(1, $this->lens->head($array));
        $this->assertEquals(2, $this->lens->head($array, fn($v) => $v > 1));
        $this->assertNull($this->lens->head($array, fn($v) => $v > 3));
    }

    public function testTail()
    {
        $array = [1, 2, 3];

        $this->assertEquals(3, $this->lens->tail($array));
        $this->assertEquals(2, $this->lens->tail($array, fn($v) => $v < 3));
        $this->assertNull($this->lens->tail($array, fn($v) => $v > 3));
    }

    public function testSquash()
    {
        $array = [[1, 2], [3, 4], 5];
        $this->assertEquals([1, 2, 3, 4], $this->lens->squash($array));
    }

    public function testKeep()
    {
        $array = ['name' => 'John', 'age' => 30, 'email' => 'john@example.com'];
        $this->assertEquals(['name' => 'John', 'age' => 30], $this->lens->keep($array, ['name', 'age']));
    }

    public function testDrop()
    {
        $array = ['name' => 'John', 'age' => 30, 'email' => 'john@example.com'];
        $this->assertEquals(['name' => 'John'], $this->lens->drop($array, ['age', 'email']));
    }

    public function testAssoc()
    {
        $this->assertTrue($this->lens->assoc(['name' => 'John', 'age' => 30]));
        $this->assertFalse($this->lens->assoc([1, 2, 3]));
        $this->assertFalse($this->lens->assoc([]));
    }

    public function testWhr()
    {
        $array = [1, 2, 3, 4];
        $filtered = $this->lens->whr($array, fn($v) => $v % 2 === 0);
        $this->assertEquals([1 => 2, 3 => 4], $filtered);
    }

    public function testWrap()
    {
        $this->assertEquals([1], $this->lens->wrap(1));
        $this->assertEquals(['name'], $this->lens->wrap('name'));
        $this->assertEquals([1, 2], $this->lens->wrap([1, 2]));
        $this->assertEquals([], $this->lens->wrap(null));
    }

    public function testDot()
    {
        $array = ['user' => ['name' => 'John', 'age' => 30]];
        $this->assertEquals([
            'user.name' => 'John',
            'user.age' => 30
        ], $this->lens->dot($array));
    }

    public function testUndot()
    {
        $array = [
            'user.name' => 'John',
            'user.age' => 30
        ];
        $this->assertEquals([
            'user' => ['name' => 'John', 'age' => 30]
        ], $this->lens->undot($array));
    }

    public function testRand()
    {
        $array = [1, 2, 3, 4, 5];
        $shuffled = $this->lens->rand($array);

        $this->assertCount(5, $shuffled);
        $this->assertEqualsCanonicalizing($array, $shuffled);
    }
}
