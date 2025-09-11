<?php

namespace Phaseolies\Support\Mail\Mailable;

class Content
{
    /**
     * Constructor for the Content class.
     *
     * @param string $view The view file for the email content.
     * @param mixed $data The data to pass to the view.
     */
    public function __construct(public string $view = '', public mixed $data = '') {}

    /**
     * Returns a new instance of the Content class with the same view and data.
     *
     * @return self
     */
    public function content(): self
    {
        return new self(
            view: $this->view,
            data: $this->data ?? null
        );
    }
}
