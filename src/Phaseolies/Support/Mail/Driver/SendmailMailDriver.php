<?php

namespace Phaseolies\Support\Mail\Driver;

use Phaseolies\Support\Mail\Mailable;
use Phaseolies\Support\Mail\MailDriverInterface;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class SendmailMailDriver implements MailDriverInterface
{
    /**
     * @var array Configuration array containing sendmail path.
     */
    private $config;

    /**
     * @param array $config Configuration array.
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Sends an email using the sendmail program.
     *
     * @param Mailable $message The Mailable object containing email details.
     * @return bool Returns true if the email is sent successfully, false otherwise.
     * @throws \Exception Throws an exception if the email could not be sent.
     */
    public function send(Mailable $message)
    {
        // Enable exceptions
        $mail = new PHPMailer(true);

        try {
            // Configure sendmail settings
            $mail->isSendmail();

            // Set custom sendmail path if provided in config
            if (!empty($this->config['sendmail_path'])) {
                $mail->Sendmail = $this->config['sendmail_path'];
            }

            // Set email sender and recipient
            $mail->setFrom($message->from['address'], $message->from['name']);
            $mail->addAddress($message->to['address'], $message->to['name']);

            // Add CC recipients
            if (!empty($message->cc)) {
                foreach ($message->cc as $cc) {
                    if (is_string($cc)) {
                        $mail->addCC($cc);
                    } else {
                        $mail->addCC($cc['address'], $cc['name']);
                    }
                }
            }

            // Add BCC recipients
            if (!empty($message->bcc)) {
                foreach ($message->bcc as $bcc) {
                    if (is_string($bcc)) {
                        $mail->addBCC($bcc);
                    } else {
                        $mail->addBCC($bcc['address'], $bcc['name']);
                    }
                }
            }

            // Add attachments
            if (!empty($message->attachments)) {
                foreach ($message->attachments as $attachment) {
                    $mail->addAttachment(
                        $attachment['path'],
                        $attachment['name'],
                        'base64',
                        $attachment['mime']
                    );
                }
            }

            // Set email content
            $mail->isHTML(true);
            $mail->Subject = $message->subject;
            $mail->Body = $message->body;

            // Send the email
            $mail->send();

            return true;
        } catch (Exception $e) {
            throw new \Exception("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        }
    }
}
