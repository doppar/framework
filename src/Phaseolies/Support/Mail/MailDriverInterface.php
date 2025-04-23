<?php

namespace Phaseolies\Support\Mail;

/**
 * Interface for mail drivers.
 *
 * This interface defines the contract for mail drivers, ensuring that any
 * mail driver implementation must provide a `send` method to send an email.
 */
interface MailDriverInterface
{
    /**
     * Sends an email using the provided Mailable object.
     *
     * @param Mailable $message The Mailable object containing email details.
     * @return mixed The result of the email sending operation.
     */
    public function send(Mailable $message);
}
