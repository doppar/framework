<?php

namespace Phaseolies\Http\Response;

use Phaseolies\Http\Response;

class JsonResponse extends Response
{
    // Encode <, >, ', &, and " characters in the JSON, making it also safe to be embedded into HTML.
    // 15 === JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
    public const DEFAULT_ENCODING_OPTIONS = 15;

    /**
     * @param mixed $data The payload to encode as JSON.
     * @param int $status The HTTP status code (200 "OK" by default).
     * @param array<string, string|string[]> $headers An array of HTTP headers.
     * @param int $encodingOptions Flags for the json_encode() function.
     */
    public function __construct(
        private readonly mixed $data,
        int $status = 200,
        array $headers = [],
        private int $encodingOptions = self::DEFAULT_ENCODING_OPTIONS,
    ) {
        parent::__construct(null, $status, $headers);

        $this->setOriginal($data);
        $this->setBody($this->encodePayload($data));

        if (!$this->headers->get('Content-Type')) {
            $this->headers->set('Content-Type', 'application/json');
        }
    }

    /**
     * Get the original payload assigned to the response.
     *
     * @return mixed
     */
    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * Encode the JSON payload once so the stored body matches what gets sent.
     *
     * @param mixed $data
     * @return string
     */
    protected function encodePayload(mixed $data): string
    {
        if ($data === null) {
            return json_encode(new \stdClass(), \JSON_THROW_ON_ERROR | $this->encodingOptions);
        }

        return $this->encodeJsonContent($data, \JSON_THROW_ON_ERROR | $this->encodingOptions);
    }
}
