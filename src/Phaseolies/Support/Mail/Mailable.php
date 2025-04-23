<?php

namespace Phaseolies\Support\Mail;

/**
 * Represents an email message.
 *
 * This class encapsulates the details of an email, including recipients,
 * sender, subject, content, and attachments.
 */
class Mailable
{
    /**
     * The recipient(s) of the email.
     *
     * @var array
     */
    public $to;

    /**
     * The sender of the email.
     *
     * @var array
     */
    public $from;

    /**
     * The CC (carbon copy) recipient(s) of the email.
     *
     * @var array
     */
    public $cc = [];

    /**
     * The BCC (blind carbon copy) recipient(s) of the email.
     *
     * @var array
     */
    public $bcc = [];

    /**
     * The attachments for the email.
     *
     * @var array
     */
    public $attachments = [];
}
