<?php

namespace Phaseolies\Support\Mail\Mailable;

use Phaseolies\Support\Mail\Mailable;

class View
{
    /**
     * Renders the email view into a string.
     *
     * @param Mailable $mailable The Mailable object containing the view and data.
     * @return string The rendered view content as a string.
     */
    public static function render(Mailable $mailable): string
    {
        $viewPath = str_replace('.', DIRECTORY_SEPARATOR, $mailable->content()?->view);
        $data = $mailable->content()?->data;

        if (!is_array($data)) {
            $data = [$data];
        }

        if (empty($mailable->content()?->view)) {
            if (is_array($data)) {
                return json_encode($data);
            } else {
                return $data;
            }
        }

        return view($viewPath, $data)->render();
    }
}
