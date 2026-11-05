<?php

namespace Phaseolies\Support\Mail\Contracts;

use Phaseolies\Database\Entity\Model;
use Phaseolies\Support\Mail\Mailable;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;

interface MailInterface
{
    /**
     * Set the primary recipient of the next email.
     *
     * @param Model|string|array $recipient
     * @param string|null $name
     * @return self
     */
    public function to(Model|string|array $recipient, ?string $name = null): self;

    /**
     * Add CC recipients to the next email.
     *
     * @param string|array $cc
     * @return self
     */
    public function cc(string|array $cc): self;

    /**
     * Add BCC recipients to the next email.
     *
     * @param string|array $bcc
     * @return self
     */
    public function bcc(string|array $bcc): self;

    /**
     * Select the Symfony Mailer transport for the next email.
     *
     * @param TransportInterface|string $transport
     * @return self
     */
    public function driver(TransportInterface|string $transport): self;

    /**
     * Send the given mailable.
     *
     * @param \Phaseolies\Support\Mail\Mailable $mailable
     * @return SentMessage
     */
    public function send(Mailable $mailable): SentMessage;

    /**
     * Send the given mailable as an alias of send().
     *
     * @param \Phaseolies\Support\Mail\Mailable $mailable
     * @return SentMessage
     */
    public function deliver(Mailable $mailable): SentMessage;
}
