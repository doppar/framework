<?php

namespace Phaseolies\Support\Mail;

interface MailDriverInterface
{
    /**
     * Sends an email using the provided Mailable object.
     *
     * @param Mailable $mailable
     * @return mixed
     */
    public function send(Mailable $mailable);
}
