<?php

namespace Phaseolies\Http\Response;

use Phaseolies\Http\Response\Stream\StreamedResponse;

class JsonResponse extends StreamedResponse
{
    private const PLACEHOLDER = '__symfony_json__';

    /**
     * @param mixed[]                        $data            JSON Data containing PHP generators which will be streamed as list of data or a Generator
     * @param int                            $status          The HTTP status code (200 "OK" by default)
     * @param array<string, string|string[]> $headers         An array of HTTP headers
     * @param int                            $encodingOptions Flags for the json_encode() function
     */
    public function __construct(
        private readonly mixed $data,
        int $status = 200,
        array $headers = [],
        private int $encodingOptions = self::DEFAULT_ENCODING_OPTIONS,
    ) {
        parent::__construct($this->stream(...), $status, $headers);

        if (!$this->headers->get('Content-Type')) {
            $this->headers->set('Content-Type', 'application/json');
        }
    }

    private function stream(): void
    {
        $jsonEncodingOptions = \JSON_THROW_ON_ERROR | $this->encodingOptions;
        $keyEncodingOptions = $jsonEncodingOptions & ~\JSON_NUMERIC_CHECK;

        $this->streamData($this->data, $jsonEncodingOptions, $keyEncodingOptions);
    }

    private function streamData(mixed $data, int $jsonEncodingOptions, int $keyEncodingOptions): void
    {
        if (\is_array($data)) {
            $this->streamArray($data, $jsonEncodingOptions, $keyEncodingOptions);

            return;
        }

        if (is_iterable($data) && !$data instanceof \JsonSerializable) {
            $this->streamIterable($data, $jsonEncodingOptions, $keyEncodingOptions);

            return;
        }

        echo json_encode($data, $jsonEncodingOptions);
    }

    private function streamArray(array $data, int $jsonEncodingOptions, int $keyEncodingOptions): void
    {
        $generators = [];

        array_walk_recursive($data, function (&$item, $key) use (&$generators) {
            if (self::PLACEHOLDER === $key) {
                // if the placeholder is already in the structure it should be replaced with a new one that explode
                // works like expected for the structure
                $generators[] = $key;
            }

            // generators should be used but for better DX all kind of Traversable and objects are supported
            if (\is_object($item)) {
                $generators[] = $item;
                $item = self::PLACEHOLDER;
            } elseif (self::PLACEHOLDER === $item) {
                // if the placeholder is already in the structure it should be replaced with a new one that explode
                // works like expected for the structure
                $generators[] = $item;
            }
        });

        $jsonParts = explode('"' . self::PLACEHOLDER . '"', json_encode($data, $jsonEncodingOptions));

        foreach ($generators as $index => $generator) {
            // send first and between parts of the structure
            echo $jsonParts[$index];

            $this->streamData($generator, $jsonEncodingOptions, $keyEncodingOptions);
        }

        // send last part of the structure
        echo $jsonParts[array_key_last($jsonParts)];
    }

    private function streamIterable(iterable $iterable, int $jsonEncodingOptions, int $keyEncodingOptions): void
    {
        $isFirstItem = true;
        $startTag = '[';

        foreach ($iterable as $key => $item) {
            if ($isFirstItem) {
                $isFirstItem = false;
                // depending on the first elements key the generator is detected as a list or map
                // we can not check for a whole list or map because that would hurt the performance
                // of the streamed response which is the main goal of this response class
                if (0 !== $key) {
                    $startTag = '{';
                }

                echo $startTag;
            } else {
                // if not first element of the generic, a separator is required between the elements
                echo ',';
            }

            if ('{' === $startTag) {
                echo json_encode((string) $key, $keyEncodingOptions) . ':';
            }

            $this->streamData($item, $jsonEncodingOptions, $keyEncodingOptions);
        }

        if ($isFirstItem) { // indicates that the generator was empty
            echo '[';
        }

        echo '[' === $startTag ? ']' : '}';
    }
}
