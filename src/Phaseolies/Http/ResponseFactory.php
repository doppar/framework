<?php

namespace Phaseolies\Http;

use Phaseolies\Support\Collection;
use Phaseolies\Http\Response\Stream\StreamedResponse;
use Phaseolies\Http\Response\Stream\StreamedJsonResponse;
use Phaseolies\Http\Response\Stream\BinaryFileResponse;
use Phaseolies\Http\Response\JsonResponse;
use Phaseolies\Http\Exceptions\StreamedResponseException;
use Phaseolies\Database\Eloquent\Model;
use Phaseolies\Database\Eloquent\Builder;

class ResponseFactory
{
    /**
     * Create a new response instance.
     *
     * @param mixed $content
     * @param int $status
     * @param array $headers
     * @return \Phaseolies\Http\Response
     */
    public function make($content = '', $status = 200, array $headers = []): Response
    {
        $response = app(Response::class);
        $response->setStatusCode($status);
        if ($content instanceof Collection) {
            $content = json_encode($content->toArray());
            $response->headers->set('Content-Type', 'application/json');
        } elseif (is_array($content)) {
            $content = json_encode($content);
            $response->headers->set('Content-Type', 'application/json');
        } elseif ($content instanceof Model) {
            $content = json_encode($content);
            $response->headers->set('Content-Type', 'application/json');
        } elseif ($content instanceof Builder) {
            $content = json_encode($content);
            $response->headers->set('Content-Type', 'application/json');
        }

        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value);
        }
        $response->setBody($content);

        return $response;
    }

    /**
     * Return a JSON response.
     *
     * This method sets the appropriate headers for a JSON response and returns the JSON-encoded data.
     *
     * @param mixed $data The data to encode as JSON.
     * @param int $statusCode The HTTP status code (default: 200).
     * @param array<string, string> $headers Additional headers to include in the response.
     * @param array $headers
     * @return JsonResponse
     */
    public function json(mixed $data, int $statusCode = 200, array $headers = []): JsonResponse
    {
        return new JsonResponse($data, $statusCode, $headers);
    }

    /**
     * Return a plain text response.
     *
     * @param string $content The plain text content.
     * @param int $statusCode The HTTP status code (default: 200).
     * @param array<string, string> $headers Additional headers to include in the response.
     * @return Response
     */
    public function text(string $content, int $statusCode = 200, array $headers = []): Response
    {
        return app(Response::class)->text($content, $statusCode, $headers);
    }

    /**
     * Create a new streamed response instance.
     *
     * @param callable $callback
     * @param int $status
     * @param array $headers
     * @return \Phaseolies\Http\Response\Stream\StreamedResponse
     */
    public function stream($callback, $status = 200, array $headers = [])
    {
        return new StreamedResponse($callback, $status, $headers);
    }

    /**
     * Create a new streamed response instance.
     *
     * @param array $data
     * @param int $status
     * @param array $headers
     * @param int $encodingOptions
     * @return \Phaseolies\Http\Response\Stream\StreamedJsonResponse
     */
    public function streamJson(
        $data,
        $status = 200,
        $headers = [],
        $encodingOptions = JsonResponse::DEFAULT_ENCODING_OPTIONS
    ) {
        return new StreamedJsonResponse($data, $status, $headers, $encodingOptions);
    }

    /**
     * Create a new streamed response instance as a file download.
     *
     * @param callable $callback
     * @param string|null $name
     * @param array $headers
     * @param string|null $disposition
     * @return \Phaseolies\Http\Response\Stream\StreamedResponse
     * @throws \Phaseolies\Http\Exceptions\StreamedResponseException
     */
    public function streamDownload($callback, $name = null, array $headers = [], $disposition = 'attachment')
    {
        $withWrappedException = function () use ($callback) {
            try {
                $callback();
            } catch (\Throwable $e) {
                throw new StreamedResponseException($e);
            }
        };

        $response = new StreamedResponse($withWrappedException, 200, $headers);

        if (! is_null($name)) {
            $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
                $disposition,
                $name,
                $this->fallbackName($name)
            ));
        }

        return $response;
    }

    /**
     * Convert the string to ASCII characters that are equivalent to the given name.
     *
     * @param  string  $name
     * @return string
     */
    protected function fallbackName($name)
    {
        // Remove any non-ASCII characters and replace them with their closest ASCII equivalents
        $name = iconv('UTF-8', 'ASCII//TRANSLIT', $name);

        // Remove any remaining non-ASCII characters that couldn't be transliterated
        $name = preg_replace('/[^\x20-\x7E]/', '', $name);

        // Remove any '%' characters
        $name = str_replace('%', '', $name);

        return $name;
    }

    /**
     * Create a new file download response.
     *
     * @param \SplFileInfo|string $file
     * @param string|null $name
     * @param array $headers
     * @param string|null $disposition
     * @return \Phaseolies\Http\Response\Stream\BinaryFileResponse
     */
    public function download($file, $name = null, array $headers = [], $disposition = 'attachment')
    {
        $response = new BinaryFileResponse($file, 200, $headers, true, $disposition);

        if (! is_null($name)) {
            return $response->setContentDisposition($disposition, $name, $this->fallbackName($name));
        }

        return $response;
    }

    /**
     * Return the raw contents of a binary file.
     *
     * @param \SplFileInfo|string $file
     * @param array $headers
     * @return \Phaseolies\Http\Response\Stream\BinaryFileResponse
     */
    public function file($file, array $headers = [])
    {
        return new BinaryFileResponse($file, 200, $headers);
    }
}
