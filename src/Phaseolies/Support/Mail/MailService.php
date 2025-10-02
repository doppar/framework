<?php

namespace Phaseolies\Support\Mail;

use Phaseolies\Support\Mail\Mailable\View;
use Phaseolies\Support\Mail\MailDriverInterface;
use Phaseolies\Support\Mail\Driver\SmtpMailDriver;
use Phaseolies\Support\Mail\Driver\SendmailMailDriver;
use Phaseolies\Support\Mail\Driver\QmailMailDriver;
use Phaseolies\Support\Mail\Driver\MailMailDriver;
use App\Models\User;

class MailService
{
    /**
     * The mail driver used to send emails.
     *
     * @var MailDriverInterface
     */
    private $driver;

    /**
     * The Mailable object representing the email message.
     *
     * @var Mailable
     */
    private $message;

    public function __construct()
    {
        $this->driver = self::resolveDriver();
        $this->message = new Mailable();
    }

    /**
     * Creates a new Mail instance with the specified driver.
     *
     * @param MailDriverInterface $driver
     * @return self
     */
    public function driver(MailDriverInterface $driver)
    {
        return new self($driver);
    }

    /**
     * Creates a new Mail instance and sets the primary recipient.
     *
     * @param User|string $recipient
     * @param string|null $name
     * @return self
     */
    public function to(User|string $recipient, ?string $name = null): self
    {
        if ($recipient instanceof User) {
            $this->message->to = [
                'address' => $recipient->email,
                'name' => $recipient->name ?? null
            ];
        } else {

            if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('Invalid email address provided');
            }

            $this->message->to = [
                'address' => $recipient,
                'name' => $name
            ];
        }

        $this->message->from = [
            'address' => config('mail.from.address'),
            'name' => config('mail.from.name'),
        ];

        return $this;
    }

    /**
     * Sends the email using the provided Mailable object.
     *
     * @param Mailable $mailable
     * @return mixed
     * @throws \Exception
     */
    public function send(Mailable $mailable)
    {
        $this->message->subject = $mailable->subject()?->subject;
        $this->message->body =  View::render($mailable);
        $this->message->cc = array_merge($this->message->cc, $mailable->cc);
        $this->message->bcc = array_merge($this->message->bcc, $mailable->bcc);

        $attachment = $mailable->attachment() ?? [];

        if (isset($attachment[0])) {
            foreach ($attachment as $filePath) {
                if (!file_exists($filePath)) {
                    throw new \Exception("$filePath not found");
                }
                $this->message->attachments[] = [
                    'path' => $filePath,
                    'name' => basename($filePath),
                    'mime' => mime_content_type($filePath),
                ];
            }
        } else {
            foreach ($attachment as $filePath => $fileDetails) {
                if (!file_exists($filePath)) {
                    throw new \Exception("$filePath not found");
                }
                $this->message->attachments[] = [
                    'path' => $filePath,
                    'name' => $fileDetails['as'] ?? basename($filePath),
                    'mime' => $fileDetails['mime'] ?? mime_content_type($filePath),
                ];
            }
        }

        return $this->driver->send($this->message);
    }

    /**
     * Adds CC (carbon copy) recipients to the email
     *
     * @param string|array $cc
     * @return self
     */
    public function cc($cc): self
    {
        if (is_string($cc)) {
            $this->message->cc[] = $cc;
        } else {
            $this->message->cc = array_merge($this->message->cc, is_array($cc) ? $cc : [$cc]);
        }
        return $this;
    }

    /**
     * Adds BCC (blind carbon copy) recipients to the email.
     *
     * @param string|array $bcc
     * @return self
     */
    public function bcc($bcc): self
    {
        if (is_string($bcc)) {
            $this->message->bcc[] = $bcc;
        } else {
            $this->message->bcc = array_merge($this->message->bcc, is_array($bcc) ? $bcc : [$bcc]);
        }
        return $this;
    }

    /**
     * Resolves the mail driver based on the application configuration.
     *
     * @return MailDriverInterface
     * @throws \Exception
     */
    private static function resolveDriver(): MailDriverInterface
    {
        $mailer = config('mail.default');
        $config = config('mail.mailers.' . $mailer);

        switch ($mailer) {
            case 'smtp':
                return new SmtpMailDriver($config);
            case 'sendmail':
                return new SendmailMailDriver($config);
            case 'qmail':
                return new QmailMailDriver($config);
            case 'mail':
                return new MailMailDriver($config);
            default:
                throw new \Exception("Unsupported mailer: $mailer");
        }
    }
}
