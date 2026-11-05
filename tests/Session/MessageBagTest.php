<?php

namespace Tests\Unit\Session;

require_once __DIR__ . '/../Support/MockContainer.php';

use Phaseolies\DI\Container;
use Phaseolies\Session\MessageBag;
use Phaseolies\Session\Stores\ArrayStore;
use Phaseolies\Support\Session;
use PHPUnit\Framework\TestCase;
use Tests\Support\MockContainer;

class MessageBagTest extends TestCase
{
    private Session $session;

    protected function setUp(): void
    {
        $this->session = new Session(new ArrayStore(), ['cookie' => 't', 'lifetime' => 120, 'stateless' => [], 'lottery' => [0, 100]]);

        $container = new MockContainer();
        Container::setInstance($container);
        $container->bind('session', fn() => $this->session);
    }

    protected function tearDown(): void
    {
        Container::forgetInstance();
    }

    public function testEveryFieldCanBeRestored(): void
    {
        $this->session->put('input', ['name' => 'Ada', 'email' => 'ada@example.com']);

        $this->assertSame('Ada', old('name'));
        $this->assertSame('ada@example.com', old('email'));
        $this->assertSame('Ada', old('name'));
    }

    public function testArrayFieldsAndAllInputCanBeRead(): void
    {
        $this->session->put('input', ['tags' => ['a', 'b']]);

        $this->assertSame(['a', 'b'], old('tags'));
        $this->assertSame(['tags' => ['a', 'b']], MessageBag::old());
    }

    public function testInputIsNotKeptForTheNextRequest(): void
    {
        $this->session->put('input', ['name' => 'Ada']);

        old('name');

        $this->assertFalse($this->session->has('input'));
    }

    public function testMissingFieldIsNull(): void
    {
        $this->assertNull(old('name'));
    }

    public function testInputFlashedInThisRequestIsPreferred(): void
    {
        $this->session->putPeek('input', ['name' => 'Previous']);
        $this->session->put('input', ['name' => 'Current']);

        $this->assertSame('Current', old('name'));
    }
}
