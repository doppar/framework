<?php

namespace Tests\Unit\Mail;

use Phaseolies\Support\Mail\Mailable\Content;
use PHPUnit\Framework\TestCase;

class ContentTest extends TestCase
{
    public function testConstructorSetsViewAndData()
    {
        $content = new Content('emails.welcome', ['name' => 'Mahedi']);

        $this->assertEquals('emails.welcome', $content->view);
        $this->assertEquals(['name' => 'Mahedi'], $content->data);
    }

    public function testDefaultViewIsEmptyString()
    {
        $content = new Content();

        $this->assertEquals('', $content->view);
    }

    public function testDefaultDataIsEmptyString()
    {
        $content = new Content();

        $this->assertEquals('', $content->data);
    }

    public function testContentMethodReturnsNewInstance()
    {
        $original = new Content('emails.reset', ['user' => 'test']);
        $returned = $original->content();

        $this->assertInstanceOf(Content::class, $returned);
        $this->assertNotSame($original, $returned);
    }

    public function testContentMethodPreservesViewAndData()
    {
        $original = new Content('emails.invoice', ['amount' => 99.99]);
        $returned = $original->content();

        $this->assertEquals('emails.invoice', $returned->view);
        $this->assertEquals(['amount' => 99.99], $returned->data);
    }

    public function testDataCanBeNullExplicitly()
    {
        $content = new Content('emails.test', null);

        $this->assertNull($content->data);
    }

    public function testContentWithScalarData()
    {
        $content = new Content('emails.plain', 'plain string data');

        $this->assertEquals('plain string data', $content->data);
    }

    public function testContentWithArrayData()
    {
        $data    = ['key1' => 'val1', 'key2' => 'val2'];
        $content = new Content('emails.multi', $data);

        $this->assertIsArray($content->data);
        $this->assertEquals($data, $content->data);
    }

    public function testContentImmutabilityOnChain()
    {
        $a = new Content('emails.original', ['x' => 1]);
        $b = $a->content();
        $b->view = 'emails.mutated';

        $this->assertEquals('emails.original', $a->view);
    }
}
