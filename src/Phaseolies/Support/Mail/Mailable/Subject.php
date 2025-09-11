<?php

namespace Phaseolies\Support\Mail\Mailable;

class Subject
{
    /**
     * The subject of the email.
     *
     * @var string
     */
    public string $subject;

    /**
     * Constructor for the Subject class.
     *
     * @param string $subject The subject of the email.
     */
    public function __construct(string $subject)
    {
        $this->subject = $subject;
    }

    /**
     * Returns a new instance of the Subject class with the same subject.
     *
     * @return self
     */
    public function subject(): self
    {
        return new self(
            subject: $this->subject
        );
    }
}
