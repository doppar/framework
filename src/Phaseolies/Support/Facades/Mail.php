<?php

namespace Phaseolies\Support\Facades;

use Phaseolies\Facade\BaseFacade;

/**
 * @method static \Phaseolies\Support\Mail\Contracts\MailInterface to(\Phaseolies\Database\Entity\Model|string|array $recipient, string|null $name = null)
 * @method static \Phaseolies\Support\Mail\Contracts\MailInterface cc(string|array $cc)
 * @method static \Phaseolies\Support\Mail\Contracts\MailInterface bcc(string|array $bcc)
 * @method static \Phaseolies\Support\Mail\Contracts\MailInterface driver(\Symfony\Component\Mailer\Transport\TransportInterface|string $transport)
 * @method static \Symfony\Component\Mailer\SentMessage send(\Phaseolies\Support\Mail\Mailable $mailable)
 * @method static \Symfony\Component\Mailer\SentMessage deliver(\Phaseolies\Support\Mail\Mailable $mailable)
 *
 * @see \Phaseolies\Support\Mail\Contracts\MailInterface
 */
class Mail extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'mail';
    }
}
