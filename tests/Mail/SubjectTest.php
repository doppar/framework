<?php

namespace Tests\Unit\Mail;

use Phaseolies\Support\Mail\Mailable\Subject;
use PHPUnit\Framework\TestCase;

class SubjectTest extends TestCase
{
    public function testConstructorSetsSubject()
    {
        $subject = new Subject('Hello World');

        $this->assertEquals('Hello World', $subject->subject);
    }

    public function testSubjectMethodReturnsNewInstance()
    {
        $original = new Subject('Test Subject');
        $returned = $original->subject();

        $this->assertInstanceOf(Subject::class, $returned);
        $this->assertNotSame($original, $returned);
    }

    public function testSubjectMethodPreservesValue()
    {
        $original = new Subject('Welcome Email');
        $returned = $original->subject();

        $this->assertEquals('Welcome Email', $returned->subject);
    }

    public function testEmptySubjectIsAccepted()
    {
        $subject = new Subject('');

        $this->assertEquals('', $subject->subject);
    }

    public function testSubjectWithSpecialCharacters()
    {
        $text    = 'Réservation confirmée – Votre commande #12345';
        $subject = new Subject($text);

        $this->assertEquals($text, $subject->subject);
    }

    public function testSubjectImmutabilityOnChainedCall()
    {
        $a = new Subject('Original');
        $b = $a->subject();
        $b->subject = 'Mutated';

        $this->assertEquals('Original', $a->subject);
    }
}
