<?php

namespace Phaseolies\Support\Mail;

use Phaseolies\Support\Mail\Mailable\View;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class Mailable
{
    /**
     * The recipient(s) of the email.
     *
     * @var array|null
     */
    public $to;

    /**
     * The sender of the email.
     *
     * @var array|null
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

    /**
     * The subject of the mail.
     *
     * @var string|null
     */
    public $subject;

    /**
     * The rendered HTML body of the mail.
     *
     * @var string|null
     */
    public $body;

    /**
     * The plain text alternative body of the mail.
     *
     * @var string|null
     */
    public ?string $textBody = null;

    /**
     * The "Reply-To" recipient(s) of the email.
     *
     * @var array
     */
    public array $replyTo = [];

    /**
     * The address bounces should be delivered to.
     *
     * @var string|null
     */
    public ?string $returnPath = null;

    /**
     * The actual sender, when different from the "From" address.
     *
     * @var string|null
     */
    public ?string $sender = null;

    /**
     * Custom headers to attach to the email.
     *
     * @var array
     */
    public array $headers = [];

    /**
     * Symfony Mailer tags, sent as `X-Doppar-Tag` headers.
     *
     * @var array
     */
    public array $tags = [];

    /**
     * Symfony Mailer metadata, sent as `X-Doppar-Metadata-*` headers.
     *
     * @var array
     */
    public array $metadata = [];

    /**
     * The email priority, one of `Email::PRIORITY_*`.
     *
     * @var int|null
     */
    public ?int $priority = null;

    /**
     * Inline (CID) embeds for the email, e.g. images referenced from the view.
     *
     * @var array
     */
    public array $embeds = [];

    /**
     * Build the underlying Symfony `Email` instance from this Mailable.
     *
     * @param Email|null $email
     * @return Email
     */
    public function toEmail(?Email $email = null): Email
    {
        $email ??= new Email();

        $this->applySubject($email);
        $this->applyBody($email);
        $this->applyRecipients($email);
        $this->applyEnvelope($email);
        $this->applyHeaders($email);
        $this->applyAttachments($email);
        $this->applyEmbeds($email);

        return $this->build($email);
    }

    /**
     * Hook for subclasses to further customize the built email.
     *
     * @param Email $email
     * @return Email
     */
    public function build(Email $email): Email
    {
        return $email;
    }

    /**
     * Set the HTML body of the email.
     *
     * @param string $html
     * @return static
     */
    public function html(string $html): static
    {
        $this->body = $html;

        return $this;
    }

    /**
     * Set the plain text alternative body of the email.
     *
     * @param string $text
     * @return static
     */
    public function text(string $text): static
    {
        $this->textBody = $text;

        return $this;
    }

    /**
     * Add a "Reply-To" recipient to the email.
     *
     * @param string|array $address
     * @param string|null $name
     * @return static
     */
    public function replyTo(string|array $address, ?string $name = null): static
    {
        $this->replyTo[] = is_array($address)
            ? $address
            : ['address' => $address, 'name' => $name];

        return $this;
    }

    /**
     * Add a custom header to the email.
     *
     * @param string $name
     * @param string $value
     * @return static
     */
    public function header(string $name, string $value): static
    {
        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * Tag the email, useful for filtering in your mail provider's dashboard.
     *
     * @param string $tag
     * @return static
     */
    public function tag(string $tag): static
    {
        $this->tags[] = $tag;

        return $this;
    }

    /**
     * Attach a piece of metadata to the email.
     *
     * @param string $name
     * @param string|int|float|bool $value
     * @return static
     */
    public function metadata(string $name, string|int|float|bool $value): static
    {
        $this->metadata[$name] = $value;

        return $this;
    }

    /**
     * Set the priority of the email.
     *
     * @param int $priority
     * @return static
     */
    public function priority(int $priority): static
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * Attach a file to the email from a filesystem path.
     *
     * @param string $path
     * @param string|null $name
     * @param string|null $mime
     * @return static
     */
    public function attach(string $path, ?string $name = null, ?string $mime = null): static
    {
        $this->attachments[] = compact('path', 'name', 'mime');

        return $this;
    }

    /**
     * Embed a file inline (CID) so it can be referenced from the HTML body.
     *
     * @param string $path
     * @param string|null $name
     * @param string|null $mime
     * @return static
     */
    public function embed(string $path, ?string $name = null, ?string $mime = null): static
    {
        $this->embeds[] = compact('path', 'name', 'mime');

        return $this;
    }

    /**
     * Resolve the email subject, preferring an explicit `subject()` definition.
     *
     * @param Email $email
     * @return void
     */
    protected function applySubject(Email $email): void
    {
        if ($this->subject !== null) {
            $email->subject((string) $this->subject);

            return;
        }

        if (method_exists($this, 'subject')) {
            $subject = $this->subject();

            if ($subject?->subject !== null) {
                $email->subject($subject->subject);
            }
        }
    }

    /**
     * Resolve the email body, preferring an explicit `content()` definition.
     *
     * @param Email $email
     * @return void
     */
    protected function applyBody(Email $email): void
    {
        if ($this->body !== null) {
            $email->html((string) $this->body);
        } elseif (method_exists($this, 'content')) {
            $content = $this->content();

            if ($content?->view) {
                $email->html(View::render($this));
            } elseif ($content?->data !== null && $content?->data !== '') {
                $email->text((string) $content->data);
            }
        }

        if ($this->textBody !== null) {
            $email->text($this->textBody);
        }
    }

    /**
     * Apply CC, BCC and Reply-To recipients to the email.
     *
     * @param Email $email
     * @return void
     */
    protected function applyRecipients(Email $email): void
    {
        foreach ($this->cc as $recipient) {
            $email->addCc($this->address($recipient));
        }

        foreach ($this->bcc as $recipient) {
            $email->addBcc($this->address($recipient));
        }

        foreach ($this->replyTo as $recipient) {
            $email->addReplyTo($this->address($recipient));
        }
    }

    /**
     * Apply envelope-level settings such as return path, sender and priority.
     *
     * @param Email $email
     * @return void
     */
    protected function applyEnvelope(Email $email): void
    {
        if ($this->returnPath) {
            $email->returnPath($this->returnPath);
        }

        if ($this->sender) {
            $email->sender($this->sender);
        }

        if ($this->priority !== null) {
            $email->priority($this->priority);
        }
    }

    /**
     * Apply custom headers to the email.
     *
     * @param Email $email
     * @return void
     */
    protected function applyHeaders(Email $email): void
    {
        foreach ($this->headers as $name => $value) {
            $email->getHeaders()->addTextHeader($name, (string) $value);
        }
    }

    /**
     * Attach files, preferring explicitly attached files over `attachment()`.
     *
     * @param Email $email
     * @return void
     */
    protected function applyAttachments(Email $email): void
    {
        foreach ($this->attachments ?: $this->attachmentDefinition() as $attachment) {
            $email->attachFromPath(
                $attachment['path'],
                $attachment['name'] ?? basename($attachment['path']),
                $attachment['mime'] ?? null
            );
        }
    }

    /**
     * Embed inline (CID) files into the email.
     *
     * @param Email $email
     * @return void
     */
    protected function applyEmbeds(Email $email): void
    {
        foreach ($this->embeds as $embed) {
            $email->embedFromPath(
                $embed['path'],
                $embed['name'] ?? basename($embed['path']),
                $embed['mime'] ?? null
            );
        }
    }

    /**
     * Normalize the array/string attachments returned by `attachment()`.
     *
     * @return array
     */
    protected function attachmentDefinition(): array
    {
        if (!method_exists($this, 'attachment')) {
            return [];
        }

        $defined = $this->attachment() ?? [];
        $result = [];

        foreach ($defined as $path => $details) {
            if (is_int($path)) {
                $path = $details;
                $details = [];
            }

            if (!is_file($path)) {
                throw new \RuntimeException("{$path} not found");
            }

            $result[] = [
                'path' => $path,
                'name' => $details['as'] ?? basename($path),
                'mime' => $details['mime'] ?? (mime_content_type($path) ?: null),
            ];
        }

        return $result;
    }

    /**
     * Convert a string or `['address' => ..., 'name' => ...]` array into an `Address`.
     *
     * @param string|array $recipient
     * @return Address
     */
    protected function address(string|array $recipient): Address
    {
        return is_array($recipient)
            ? new Address($recipient['address'], $recipient['name'] ?? '')
            : Address::create($recipient);
    }
}
