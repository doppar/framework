<?php

namespace Tests\Unit\Mail;

use Phaseolies\Support\Mail\Mailable;
use PHPUnit\Framework\TestCase;

class MailableTest extends TestCase
{
    public function testDefaultToIsNull()
    {
        $mailable = new Mailable();

        $this->assertNull($mailable->to);
    }

    public function testDefaultFromIsNull()
    {
        $mailable = new Mailable();

        $this->assertNull($mailable->from);
    }

    public function testDefaultCcIsEmptyArray()
    {
        $mailable = new Mailable();

        $this->assertEquals([], $mailable->cc);
    }

    public function testDefaultBccIsEmptyArray()
    {
        $mailable = new Mailable();

        $this->assertEquals([], $mailable->bcc);
    }

    public function testDefaultAttachmentsIsEmptyArray()
    {
        $mailable = new Mailable();

        $this->assertEquals([], $mailable->attachments);
    }

    public function testDefaultSubjectIsNull()
    {
        $mailable = new Mailable();

        $this->assertNull($mailable->subject);
    }

    public function testDefaultBodyIsNull()
    {
        $mailable = new Mailable();

        $this->assertNull($mailable->body);
    }

    public function testToCanBeAssigned()
    {
        $mailable     = new Mailable();
        $mailable->to = ['address' => 'user@example.com', 'name' => 'User'];

        $this->assertEquals('user@example.com', $mailable->to['address']);
        $this->assertEquals('User', $mailable->to['name']);
    }

    public function testFromCanBeAssigned()
    {
        $mailable       = new Mailable();
        $mailable->from = ['address' => 'no-reply@app.com', 'name' => 'App'];

        $this->assertEquals('no-reply@app.com', $mailable->from['address']);
    }

    public function testCcCanBeAssigned()
    {
        $mailable     = new Mailable();
        $mailable->cc = ['cc@example.com'];

        $this->assertCount(1, $mailable->cc);
        $this->assertEquals('cc@example.com', $mailable->cc[0]);
    }

    public function testBccCanBeAssigned()
    {
        $mailable      = new Mailable();
        $mailable->bcc = ['bcc@example.com', 'bcc2@example.com'];

        $this->assertCount(2, $mailable->bcc);
    }

    public function testSubjectCanBeAssigned()
    {
        $mailable          = new Mailable();
        $mailable->subject = 'Welcome to Doppar';

        $this->assertEquals('Welcome to Doppar', $mailable->subject);
    }

    public function testBodyCanBeAssigned()
    {
        $mailable       = new Mailable();
        $mailable->body = '<h1>Hello</h1>';

        $this->assertEquals('<h1>Hello</h1>', $mailable->body);
    }

    public function testAttachmentsCanBeAssigned()
    {
        $mailable              = new Mailable();
        $mailable->attachments = [
            ['path' => '/tmp/file.pdf', 'name' => 'file.pdf', 'mime' => 'application/pdf'],
        ];

        $this->assertCount(1, $mailable->attachments);
        $this->assertEquals('/tmp/file.pdf', $mailable->attachments[0]['path']);
    }
}
