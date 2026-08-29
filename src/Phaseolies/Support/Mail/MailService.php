<?php

namespace Phaseolies\Support\Mail;

use Phaseolies\Database\Entity\Model;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Crypto\DkimSigner;
use Symfony\Component\Mime\Crypto\SMimeSigner;
use Symfony\Component\Mime\Message;

class MailService
{
    /**
     * The address the next email will be delivered to.
     *
     * @var string|null
     */
    private ?string $recipient = null;

    /**
     * The display name of the recipient.
     *
     * @var string|null
     */
    private ?string $recipientName = null;

    /**
     * The CC (carbon copy) recipient(s) for the next email.
     *
     * @var array
     */
    private array $cc = [];

    /**
     * The BCC (blind carbon copy) recipient(s) for the next email.
     *
     * @var array
     */
    private array $bcc = [];

    /**
     * A one-off Symfony transport to use instead of the configured mailer.
     *
     * @var TransportInterface|null
     */
    private ?TransportInterface $transport = null;

    /**
     * The lazily built DKIM signer, cached for the lifetime of this service.
     *
     * @var DkimSigner|null
     */
    private ?DkimSigner $dkimSigner = null;

    /**
     * The lazily built S/MIME signer, cached for the lifetime of this service.
     *
     * @var SMimeSigner|null
     */
    private ?SMimeSigner $smimeSigner = null;

    /**
     * Set the primary recipient of the next email.
     *
     * @param Model|string|array $recipient
     * @param string|null $name
     * @return self
     */
    public function to(Model|string|array $recipient, ?string $name = null): self
    {
        if ($recipient instanceof Model) {
            $recipient = ['address' => $recipient->email, 'name' => $recipient->name ?? null];
        }

        if (is_array($recipient)) {
            $this->recipient = $recipient['address'];
            $this->recipientName = $recipient['name'] ?? null;

            return $this;
        }

        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email address provided');
        }

        $this->recipient = $recipient;
        $this->recipientName = $name;

        return $this;
    }

    /**
     * Add CC (carbon copy) recipients to the next email.
     *
     * @param string|array $cc
     * @return self
     */
    public function cc(string|array $cc): self
    {
        $this->cc = array_merge($this->cc, $this->normalize($cc));

        return $this;
    }

    /**
     * Add BCC (blind carbon copy) recipients to the next email.
     *
     * @param string|array $bcc
     * @return self
     */
    public function bcc(string|array $bcc): self
    {
        $this->bcc = array_merge($this->bcc, $this->normalize($bcc));

        return $this;
    }

    /**
     * Send the next email through a specific Symfony Mailer transport
     * instead of the one configured in `mail.php`.
     *
     * @param TransportInterface|string $transport A transport instance, or a Mailer DSN string.
     * @return self
     */
    public function driver(TransportInterface|string $transport): self
    {
        $this->transport = is_string($transport) ? Transport::fromDsn($transport) : $transport;

        return $this;
    }

    /**
     * Alias for `send()`.
     *
     * @param Mailable $mailable
     * @return SentMessage
     */
    public function deliver(Mailable $mailable): SentMessage
    {
        return $this->send($mailable);
    }

    /**
     * Send the given Mailable to the recipient set via `to()`.
     *
     * @param Mailable $mailable
     * @return SentMessage
     */
    public function send(Mailable $mailable): SentMessage
    {
        if (!$this->recipient) {
            throw new \LogicException('A recipient is required before sending mail.');
        }

        $from = config('mail.from', []);
        $fromAddress = new Address(
            $mailable->from['address'] ?? $from['address'],
            $mailable->from['name'] ?? $from['name'] ?? ''
        );
        $toAddress = new Address($this->recipient, $this->recipientName ?? '');

        $email = $mailable->toEmail();
        $email->from($fromAddress)->to($toAddress);

        foreach ($this->cc as $recipient) {
            $email->addCc($this->address($recipient));
        }

        foreach ($this->bcc as $recipient) {
            $email->addBcc($this->address($recipient));
        }

        foreach ($mailable->tags as $tag) {
            $email->getHeaders()->addTextHeader('X-Doppar-Tag', $tag);
        }

        foreach ($mailable->metadata as $key => $value) {
            $email->getHeaders()->addTextHeader('X-Doppar-Metadata-' . $key, (string) $value);
        }

        $message = $this->applySigning($email);
        $sent = $this->resolveTransport()->send($message, Envelope::create($message));

        $this->recipient = $this->recipientName = $this->transport = null;
        $this->cc = $this->bcc = [];

        return $sent;
    }

    /**
     * Resolve the Symfony Mailer transport for the current send.
     *
     * @return TransportInterface
     */
    private function resolveTransport(): TransportInterface
    {
        if ($this->transport) {
            return $this->transport;
        }

        $config = config('mail.mailers.' . config('mail.default'), []);
        $transport = Transport::fromDsn($this->resolveDsn($config));

        if ($transport instanceof SmtpTransport && !empty($config['timeout'])) {
            $stream = $transport->getStream();

            if (method_exists($stream, 'setTimeout')) {
                $stream->setTimeout((float) $config['timeout']);
            }
        }

        return $transport;
    }

    /**
     * Resolve the Symfony Mailer DSN for a mailer config entry, either from
     * an explicit `dsn` string or assembled from its individual smtp parts.
     *
     * @param array $config
     * @return string
     */
    private function resolveDsn(array $config): string
    {
        if (!empty($config['dsn'])) {
            return $config['dsn'];
        }

        if (empty($config['host'])) {
            throw new \RuntimeException('Mail DSN is not configured.');
        }

        $scheme = ($config['encryption'] ?? null) === 'ssl' ? 'smtps' : 'smtp';

        $dsn = sprintf(
            '%s://%s:%s@%s:%s',
            $scheme,
            rawurlencode((string) ($config['username'] ?? '')),
            rawurlencode((string) ($config['password'] ?? '')),
            $config['host'],
            $config['port'] ?? 25
        );

        $query = array_filter([
            'local_domain' => $config['local_domain'] ?? null,
            'auto_tls' => empty($config['encryption']) ? 'false' : null,
        ], fn($value) => $value !== null && $value !== '');

        return $query ? $dsn . '?' . http_build_query($query) : $dsn;
    }

    /**
     * Sign the message with DKIM and/or S/MIME when configured in `mail.php`.
     *
     * @param Message $message
     * @return Message
     */
    private function applySigning(Message $message): Message
    {
        $dkim = config('mail.signing.dkim', []);

        if (!empty($dkim['enabled'])) {
            $this->dkimSigner ??= new DkimSigner(
                $dkim['private_key'],
                $dkim['domain'],
                $dkim['selector'],
                [],
                $dkim['passphrase'] ?? ''
            );

            $message = $this->dkimSigner->sign($message);
        }

        $smime = config('mail.signing.smime', []);

        if (!empty($smime['enabled'])) {
            $this->smimeSigner ??= new SMimeSigner(
                $smime['certificate'],
                $smime['private_key'],
                $smime['passphrase'] ?? null
            );

            $message = $this->smimeSigner->sign($message);
        }

        return $message;
    }

    /**
     * Normalize a CC/BCC value into a flat list of recipients.
     *
     * @param string|array $recipients
     * @return array
     */
    private function normalize(string|array $recipients): array
    {
        return isset($recipients['address']) ? [$recipients] : (is_array($recipients) ? $recipients : [$recipients]);
    }

    /**
     * Convert a string or `['address' => ..., 'name' => ...]` array into an `Address`.
     *
     * @param string|array $recipient
     * @return Address
     */
    private function address(string|array $recipient): Address
    {
        return is_array($recipient)
            ? new Address($recipient['address'], $recipient['name'] ?? '')
            : Address::create($recipient);
    }
}
