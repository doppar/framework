<?php

namespace Phaseolies\Support\Facades;

/**
 * @method static \Phaseolies\Support\Mail\MailService to(\Phaseolies\Database\Entity\Model|string|array $recipient, string|null $name = null)
 * @method static \Phaseolies\Support\Mail\MailService cc(string|array $cc)
 * @method static \Phaseolies\Support\Mail\MailService bcc(string|array $bcc)
 * @method static \Phaseolies\Support\Mail\MailService driver(\Symfony\Component\Mailer\Transport\TransportInterface|string $transport)
 * @method static \Symfony\Component\Mailer\SentMessage send(\Phaseolies\Support\Mail\Mailable $mailable)
 * @method static \Symfony\Component\Mailer\SentMessage deliver(\Phaseolies\Support\Mail\Mailable $mailable)
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
