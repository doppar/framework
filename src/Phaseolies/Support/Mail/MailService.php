<?php

namespace Phaseolies\Support\Mail;

use Phaseolies\Support\Mail\MailDriverInterface;
use Phaseolies\Support\Mail\Driver\SmtpMailDriver;
use Phaseolies\Support\Mail\Mailable\View;
use App\Models\User;

/**
 * The Mail class is responsible for sending emails using a specified mail driver.
 * It provides a fluent interface for setting up email details (e.g., recipients, CC, BCC, attachments)
 * and sending the email using the configured driver.
 */
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

    /**
     * Constructor for the Mail class.
     *
     * @param MailDriverInterface $driver The mail driver to use for sending emails.
     */
    public function __construct(?MailDriverInterface $driver = null)
    {
        $this->driver = $driver;
        $this->message = new Mailable();
    }

    /**
     * Creates a new Mail instance with the specified driver.
     *
     * This method is useful for explicitly setting a custom mail driver.
     *
     * @param MailDriverInterface $driver The mail driver to use.
     * @return self A new instance of the Mail class.
     */
    public function driver(MailDriverInterface $driver)
    {
        return new self($driver);
    }

    /**
     * Creates a new Mail instance and sets the primary recipient.
     *
     * This method initializes the Mail instance with the default driver and sets the "to" address
     * and "from" address based on the provided user and configuration.
     *
     * @param User|string $recipient The user to send the email to.
     * @param string|null $name
     * @return self A new instance of the Mail class.
     */
    public function to(User|string $recipient, ?string $name = null)
    {
        $mail = new self(self::resolveDriver());

        if ($recipient instanceof User) {
            $mail->message->to = [
                'address' => $recipient->email,
                'name' => $recipient->name ?? null
            ];
        } else {

            if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('Invalid email address provided');
            }

            $mail->message->to = [
                'address' => $recipient,
                'name' => $name
            ];
        }

        $mail->message->from = [
            'address' => config('mail.from.address'),
            'name' => config('mail.from.name'),
        ];

        return $mail;
    }

    /**
     * Sends the email using the provided Mailable object.
     *
     * This method sets the email subject, body, CC, BCC, and attachments based on the Mailable object
     * and delegates the actual sending to the mail driver.
     *
     * @param Mailable $mailable The Mailable object containing email details.
     * @return mixed The result of the email sending operation.
     * @throws \Exception If an attachment file is not found.
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
     * Adds CC (carbon copy) recipients to the email.
     *
     * This method accepts a single email address (string) or an array of email addresses.
     *
     * @param string|array $cc The CC recipient(s).
     * @return self The current Mail instance for method chaining.
     */
    public function cc($cc)
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
     * This method accepts a single email address (string) or an array of email addresses.
     *
     * @param string|array $bcc The BCC recipient(s).
     * @return self The current Mail instance for method chaining.
     */
    public function bcc($bcc)
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
     * This method reads the mail configuration and instantiates the appropriate mail driver.
     *
     * @return MailDriverInterface The resolved mail driver.
     * @throws \Exception If the mailer is not supported.
     */
    private static function resolveDriver()
    {
        $mailer = config('mail.default');
        $config = config('mail.mailers.' . $mailer);

        switch ($mailer) {
            case 'smtp':
                return new SmtpMailDriver($config);
            case 'log':
                // TODO: Implement log mail driver
                break;
            default:
                throw new \Exception("Unsupported mailer: $mailer");
        }
    }
}
