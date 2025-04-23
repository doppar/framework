<?php

namespace Phaseolies\Support\Facades;

/**
 * @method static \Phaseolies\Support\Mail\MailService to($user)
 * @method static \Phaseolies\Support\Mail\MailService send(\Phaseolies\Support\Mail\Mailable $mailable)
 * @method static \Phaseolies\Support\Mail\MailService cc(string|array $cc)
 * @method static \Phaseolies\Support\Mail\MailService bcc(string|array $bcc)
 * @see \Phaseolies\Support\Mail\MailService
 */

use Phaseolies\Facade\BaseFacade;

class Mail extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'mail';
    }
}
