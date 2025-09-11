<?php

namespace Phaseolies\Support\Mail;

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
