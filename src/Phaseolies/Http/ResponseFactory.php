<?php

namespace Phaseolies\Http;

use Phaseolies\Http\Response\Stream\StreamedResponse;
use Phaseolies\Http\Response\Stream\StreamedJsonResponse;
use Phaseolies\Http\Response\Stream\BinaryFileResponse;
use Phaseolies\Http\Response\JsonResponse;
use Phaseolies\Http\Response\RedirectResponse;
use Phaseolies\Http\Exceptions\StreamedResponseException;

class ResponseFactory extends Response
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
        if ($content instanceof Response) {
            if ((int) $status !== 200) {
                $content->setStatusCode((int) $status);
            }

            return $content->withHeaders($headers);
        }

        if (static::shouldBeJson($content)) {
            return $this->json($content, $status, $headers);
        }

        return new Response($content, $status, $headers);
    }

    /**
     * Return an empty response.
     *
     * @param int $status
     * @param array $headers
     * @return Response
     */
    public function noContent(int $status = 204, array $headers = []): Response
    {
        return new Response('', $status, $headers);
    }

    /**
     * Return a JSON response.
     *
     * @param mixed $data
     * @param int $status
     * @param array<string, string> $headers
     * @return JsonResponse
     */
    public function json(mixed $data, int $status = 200, array $headers = []): JsonResponse
    {
        return new JsonResponse($data, $status, $headers);
    }

    /**
     * Return a plain text response.
     *
     * @param string $content
     * @param int $status
     * @param array<string, string> $headers
     * @return Response
     */
    public function text(string $content, int $status = 200, array $headers = []): Response
    {
        return (new Response())->text($content, $status, $headers);
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

    /**
     * Create a redirect response.
     *
     * @param string $url
     * @param int $status
     * @param array $headers
     * @return RedirectResponse
     */
    public function redirect(string $url, int $status = 302, array $headers = []): RedirectResponse
    {
        return (new RedirectResponse())->to($url, $status, $headers);
    }

    /**
     * Convert the string to ASCII characters that are equivalent to the given name.
     *
     * @param  string  $name
     * @return string
     */
    protected function fallbackName($name)
    {
        $name = iconv('UTF-8', 'ASCII//TRANSLIT', $name);

        $name = preg_replace('/[^\x20-\x7E]/', '', $name);

        $name = str_replace('%', '', $name);

        return $name;
    }
}
