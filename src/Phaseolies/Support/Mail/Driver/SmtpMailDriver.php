<?php

namespace Phaseolies\Support\Mail\Driver;

use Phaseolies\Support\Mail\Mailable;
use Phaseolies\Support\Mail\MailDriverInterface;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * SmtpMailDriver class implements the MailDriverInterface for sending emails via SMTP.
 *
 * This class uses the PHPMailer library to handle SMTP connections and email sending.
 * It takes a configuration array containing SMTP server details and sends emails
 * based on the provided Mailable object.
 */
class SmtpMailDriver implements MailDriverInterface
{
    /**
     * @var array Configuration array containing SMTP server details.
     */
    private $config;

    /**
     * Constructor.
     *
     * Initializes a new SmtpMailDriver instance with the provided configuration.
     *
     * @param array $config Configuration array containing SMTP server details.
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Sends an email using the SMTP protocol.
     *
     * This method creates a PHPMailer instance, configures it with the provided SMTP
     * settings, and sends the email based on the information in the Mailable object.
     *
     * @param Mailable $message The Mailable object containing email details.
     * @return bool Returns true if the email is sent successfully, false otherwise.
     * @throws \Exception Throws an exception if the email could not be sent.
     */
    public function send(Mailable $message)
    {
        $mail = new PHPMailer(true); // Enable exceptions

        try {
            // Configure SMTP settings
            $mail->isSMTP();
            $mail->Host = $this->config['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['username'];
            $mail->Password = $this->config['password'];
            $mail->SMTPSecure = $this->config['encryption'];
            $mail->Port = $this->config['port'];

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
                        'base64', // Encoding
                        $attachment['mime'] // MIME type
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
            // Handle sending errors
            throw new \Exception("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
            return false;
        }
    }
}
