<?php

namespace Phaseolies\Http;

use Throwable;
use Phaseolies\Http\Response\ResponseHeaderBag;
use Phaseolies\Http\Response\JsonResponse;
use Phaseolies\Http\Response\HttpStatus;
use Phaseolies\Http\Exceptions\HttpException;

class Response implements HttpStatus
{
    /**
     * The response body content.
     *
     * @var string|null
     */
    public ?string $body;

    /**
     * The exception that triggered the error response (if applicable).
     *
     * @var \Throwable|null
     */
    public $exception;

    /**
     * The original content of the response.
     *
     * @var mixed
     */
    public $original;

    /**
     * The HTTP status code for the response.
     *
     * @var int
     */
    public int $statusCode;

    /**
     * The HTTP headers for the response.
     *
     * @var ResponseHeaderBag
     */
    public ResponseHeaderBag $headers;

    /**
     * @var string|null
     */
    protected ?string $charset = null;

    /**
     * @var string
     */
    protected string $version;

    /**
     * @var string
     */
    protected string $statusText;


    /**
     * Tracks headers already sent in informational responses.
     */
    private array $sentHeaders;

    /**
     * Constructor for the Response class.
     *
     * @param string|null $body The response body content.
     * @param int $statusCode The HTTP status code (default: 200).
     * @param array<string, string> $headers The HTTP headers (default: empty array).
     */
    public function __construct(?string $body = null, int $statusCode = 200, array $headers = [])
    {
        $this->headers = new ResponseHeaderBag($headers);
        $this->setBody($body);
        $this->setStatusCode($statusCode);
        $this->setProtocolVersion('1.0');
    }

    /**
     * Returns the Response as an HTTP string.
     *
     * The string representation of the Response is the same as the
     * one that will be sent to the client only if the prepare() method
     * has been called before.
     *
     * @see prepare()
     */
    public function __toString(): string
    {
        return
            sprintf('HTTP/%s %s %s', $this->version, $this->statusCode, $this->statusText) . "\r\n" .
            $this->headers . "\r\n" .
            $this->getBody();
    }

    /**
     * Clones the current headers instance.
     */
    public function __clone()
    {
        $this->headers = clone $this->headers;
    }

    /**
     * Set the response body content.
     *
     * @param string|null $body The new response body.
     * @return self
     */
    public function setBody(?string $body): static
    {
        $this->body = $body;

        return $this;
    }

    /**
     * Get the response body content.
     *
     * @return string
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * Set the original content.
     *
     * @param mixed $original
     * @return self
     */
    public function setOriginal($original): static
    {
        $this->original = $original;

        return $this;
    }

    /**
     * Get the original content.
     *
     * @return mixed
     */
    public function getOriginal()
    {
        return $this->original;
    }

    /**
     * Set the response charset.
     *
     * @param string $charset
     * @return self
     */
    public function setCharset(string $charset): static
    {
        $this->charset = $charset;

        return $this;
    }

    /**
     * Get the response charset.
     *
     * @return string|null
     */
    public function getCharset(): ?string
    {
        return $this->charset;
    }

    /**
     * Set the response exception.
     *
     * @param $exception
     * @return self
     */
    public function setException($exception): static
    {
        $this->exception = $exception;

        return $this;
    }

    /**
     * Set the HTTP status code for the response.
     *
     * @param int $statusCode The HTTP status code.
     * @return int The updated status code.
     */
    public function setStatusCode(int $statusCode, ?string $text = null): static
    {
        $this->statusCode = $statusCode;

        if (null === $text) {
            $this->statusText = $this->getStatusCodeText($statusCode) ?? 'unknown status';

            return $this;
        }

        $this->statusText = $text;

        return $this;
    }

    /**
     * Get the HTTP status code for the response.
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Set a specific HTTP header for the response.
     *
     * @param string $name The header name.
     * @param string $value The header value.
     * @param bool $replace
     * @return Response Returns the current Response instance.
     */
    public function setHeader(string $name, string $value, $replace = true): Response
    {
        $this->headers->set($name, $value, $replace);

        return $this;
    }

    /**
     * Set multiple HTTP headers for the response.
     *
     * @param array<string, string> $headers An associative array of headers.
     * @return Response Returns the current Response instance.
     */
    public function withHeaders(array $headers): Response
    {
        if ($headers instanceof HeaderBag) {
            $headers = $headers->all();
        }

        foreach ($headers as $name => $value) {
            $this->headers->set($name, $value);
        }
        return $this;
    }

    /**
     * Set the exception to attach to the response.
     *
     * @param \Throwable $e
     * @return $this
     */
    public function withException(Throwable $e)
    {
        $this->exception = $e;

        return $this;
    }

    /**
     * Is response informative?
     *
     * @final
     */
    public function isInformational(): bool
    {
        return $this->statusCode >= 100 && $this->statusCode < 200;
    }

    /**
     * Is the response empty?
     *
     * @final
     */
    public function isEmpty(): bool
    {
        return in_array($this->statusCode, [204, 304]);
    }

    /**
     * Sets the HTTP protocol version (1.0 or 1.1).
     *
     * @return $this
     *
     * @final
     */
    public function setProtocolVersion(string $version): static
    {
        $this->version = $version;

        return $this;
    }

    /**
     * Gets the HTTP protocol version.
     *
     * @final
     */
    public function getProtocolVersion(): string
    {
        return $this->version;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Prepares the Response before it is sent to the client.
     *
     * This method tweaks the Response to ensure that it is
     * compliant with RFC 2616. Most of the changes are based on
     * the Request that is "associated" with this Response.
     *
     * @return $this
     */
    public function prepare(Request $request): static
    {
        $headers = $this->headers;

        if ($this->isInformational() || $this->isEmpty()) {
            $this->setBody(null);
            $headers->remove('Content-Type');
            $headers->remove('Content-Length');
            // prevent PHP from sending the Content-Type header based on default_mimetype
            ini_set('default_mimetype', '');
        } else {
            // Content-type based on the Request
            if (!$headers->has('Content-Type')) {
                $format = $request->getRequestFormat(null);
                if (null !== $format && $mimeType = $request->getMimeType($format)) {
                    $headers->set('Content-Type', $mimeType);
                }
            }

            // Fix Content-Type
            $charset = $this->charset ?: 'UTF-8';
            if (!$headers->has('Content-Type')) {
                $headers->set('Content-Type', 'text/html; charset=' . $charset);
            } elseif (0 === stripos($headers->get('Content-Type') ?? '', 'text/') && false === stripos($headers->get('Content-Type') ?? '', 'charset')) {
                // add the charset
                $headers->set('Content-Type', $headers->get('Content-Type') . '; charset=' . $charset);
            }

            // Fix Content-Length
            if ($headers->has('Transfer-Encoding')) {
                $headers->remove('Content-Length');
            }

            if ($request->isHead('HEAD')) {
                // cf. RFC2616 14.13
                $length = $headers->get('Content-Length');
                $this->setBody(null);
                if ($length) {
                    $headers->set('Content-Length', $length);
                }
            }
        }

        // Fix protocol
        if ('HTTP/1.0' != $request->server->get('SERVER_PROTOCOL')) {
            $this->setProtocolVersion('1.1');
        }

        // Check if we need to send extra expire info headers
        if ('1.0' == $this->getProtocolVersion() && str_contains($headers->get('Cache-Control', ''), 'no-cache')) {
            $headers->set('pragma', 'no-cache');
            $headers->set('expires', -1);
        }

        $this->ensureIEOverSSLCompatibility($request);

        if ($request->isSecure()) {
            foreach ($headers->getCookies() as $cookie) {
                $cookie->setSecureDefault(true);
            }
        }

        return $this;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Sends HTTP headers.
     *
     * @param positive-int|null $statusCode The status code to use, override the statusCode property if set and not null
     *
     * @return $this
     */
    public function sendHeaders(?int $statusCode = null): static
    {
        // headers have already been sent by the developer
        if (headers_sent()) {
            return $this;
        }

        $informationalResponse = $statusCode >= 100 && $statusCode < 200;
        if ($informationalResponse && !\function_exists('headers_send')) {
            // skip informational responses if not supported by the SAPI
            return $this;
        }

        // headers
        foreach ($this->headers->allPreserveCaseWithoutCookies() as $name => $values) {
            // As recommended by RFC 8297, PHP automatically copies headers from previous 103 responses, we need to deal with that if headers changed
            $previousValues = $this->sentHeaders[$name] ?? null;
            if ($previousValues === $values) {
                // Header already sent in a previous response, it will be automatically copied in this response by PHP
                continue;
            }

            $replace = 0 === strcasecmp($name, 'Content-Type');

            if (null !== $previousValues && array_diff($previousValues, $values)) {
                header_remove($name);
                $previousValues = null;
            }

            $newValues = null === $previousValues ? $values : array_diff($values, $previousValues);

            foreach ($newValues as $value) {
                header($name . ': ' . $value, $replace, $this->statusCode);
            }

            if ($informationalResponse) {
                $this->sentHeaders[$name] = $values;
            }
        }

        // cookies
        foreach ($this->headers->getCookies() as $cookie) {
            header('Set-Cookie: ' . $cookie, false, $this->statusCode);
        }

        if ($informationalResponse) {
            $this->headers_send($statusCode);

            return $this;
        }

        $statusCode ??= $this->statusCode;

        // status
        header(sprintf('HTTP/%s %s %s', $this->version, $statusCode, $this->statusText), true, $statusCode);

        return $this;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Checks if we need to remove Cache-Control for SSL encrypted downloads when using IE < 9.
     *
     * @see http://support.microsoft.com/kb/323308
     *
     * @final
     */
    protected function ensureIEOverSSLCompatibility(Request $request): void
    {
        if (false !== stripos($this->headers->get('Content-Disposition') ?? '', 'attachment') && 1 == preg_match('/MSIE (.*?);/i', $request->server->get('HTTP_USER_AGENT') ?? '', $match) && true === $request->isSecure()) {
            if ((int) preg_replace('/(MSIE )(.*?);/', '$2', $match[0]) < 9) {
                $this->headers->remove('Cache-Control');
            }
        }
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * @param int $statusCode
     *
     * @return void
     */
    function headers_send(int $statusCode): void
    {
        if (headers_sent()) {
            return;
        }

        // Use the response's protocol version, not hardcoded 1.1
        header(sprintf(
            'HTTP/%s %d %s',
            $this->getProtocolVersion(),
            $statusCode,
            $this->getStatusCodeText($statusCode)
        ), true, $statusCode);
    }

    public function getStatusCodeText(int $statusCode): string
    {
        $statusTexts = [
            // 1xx: Informational
            100 => 'Continue',
            101 => 'Switching Protocols',
            102 => 'Processing',
            103 => 'Early Hints',

            // 2xx: Success
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            207 => 'Multi-Status',
            208 => 'Already Reported',
            226 => 'IM Used',

            // 3xx: Redirection
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            307 => 'Temporary Redirect',
            308 => 'Permanent Redirect',

            // 4xx: Client Errors
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Payload Too Large',
            414 => 'URI Too Long',
            415 => 'Unsupported Media Type',
            416 => 'Range Not Satisfiable',
            417 => 'Expectation Failed',
            418 => 'I\'m a Teapot',
            421 => 'Misdirected Request',
            422 => 'Unprocessable Entity',
            423 => 'Locked',
            424 => 'Failed Dependency',
            425 => 'Too Early',
            426 => 'Upgrade Required',
            428 => 'Precondition Required',
            429 => 'Too Many Requests',
            431 => 'Request Header Fields Too Large',
            451 => 'Unavailable For Legal Reasons',

            // 5xx: Server Errors
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            505 => 'HTTP Version Not Supported',
            506 => 'Variant Also Negotiates',
            507 => 'Insufficient Storage',
            508 => 'Loop Detected',
            510 => 'Not Extended',
            511 => 'Network Authentication Required',
        ];

        return $statusTexts[$statusCode] ?? 'Unknown Status';
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Sends content for the current web response.
     *
     * @return $this
     */
    public function sendContent(): static
    {
        echo $this->body;

        return $this;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Send the HTTP response to the client.
     *
     * This method sets the HTTP status code, sends the headers, and outputs the response body.
     * If the status code is a redirection (3xx), it exits after sending the headers.
     *
     * @return void
     */
    public function send(bool $flush = true): static
    {
        $this->sendHeaders();
        $this->sendContent();

        if (!$flush) {
            return $this;
        }

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (\function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        } elseif (!\in_array(\PHP_SAPI, ['cli', 'phpdbg', 'embed'], true)) {
            static::closeOutputBuffers(0, true);
            flush();
        }

        return $this;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Cleans or flushes output buffers up to target level.
     *
     * Resulting level can be greater than target level if a non-removable buffer has been encountered.
     *
     * @final
     */
    public static function closeOutputBuffers(int $targetLevel, bool $flush): void
    {
        $status = ob_get_status(true);
        $level = \count($status);
        $flags = \PHP_OUTPUT_HANDLER_REMOVABLE | ($flush ? \PHP_OUTPUT_HANDLER_FLUSHABLE : \PHP_OUTPUT_HANDLER_CLEANABLE);

        while ($level-- > $targetLevel && ($s = $status[$level]) && (!isset($s['del']) ? !isset($s['flags']) || ($s['flags'] & $flags) === $flags : $s['del'])) {
            if ($flush) {
                ob_end_flush();
            } else {
                ob_end_clean();
            }
        }
    }

    /**
     * Handle an HTTP exception and return a response.
     *
     * This method sets the appropriate HTTP status code and renders either a JSON response
     * (for API requests) or an HTML error page (for web requests).
     *
     * @param HttpException $exception The HTTP exception to handle.
     * @return void
     */
    public static function dispatchHttpException(HttpException $exception): void
    {
        $statusCode = $exception->getStatusCode();
        $message = $exception->getMessage() ?: 'An error occurred.';

        http_response_code($statusCode);

        self::renderErrorPage($statusCode, $message);
    }

    /**
     * Render an HTML error page.
     *
     * This method attempts to include an error page template corresponding to the status code.
     * If the template does not exist, it throws an HTTP exception.
     *
     * @param int $statusCode The HTTP status code.
     * @param string $message The error message.
     * @return void
     */
    protected static function renderErrorPage(int $statusCode, string $message): void
    {
        $customPath = base_path("resources/views/errors/{$statusCode}.blade.php");
        $errorPage = base_path("vendor/doppar/framework/src/Phaseolies/Support/View/errors/{$statusCode}.blade.php");

        if (file_exists($customPath)) {
            include $customPath;
        } elseif (file_exists($errorPage)) {
            include $errorPage;
        } else {
            abort($statusCode, $message);
        }
    }

    /**
     * Renders a view with the given data and returns the rendered content as a string.
     */
    public function render(): string
    {
        if (empty($this->body)) {
            throw new \RuntimeException('No view content to render.');
        }

        if (is_string($this->body)) {
            return $this->body;
        }

        if (is_callable($this->body)) {
            return call_user_func($this->body);
        }

        if (is_object($this->body) && method_exists($this->body, '__toString')) {
            return $this->body->__toString();
        }

        return (string) $this->body;
    }

    /**
     * Renders a view with the given data and returns a Response object.
     *
     * @param string $view The name of the view file to render.
     * @param array $data An associative array of data to pass to the view (default is an empty array).
     * @return Response A Response object containing the rendered view.
     */
    public function view(string $view, array $data = [], array $headers = []): Response
    {
        $content = $this->renderView($view, $data);

        $this->body = $content;
        foreach ($headers as $name => $value) {
            $this->headers->set($name, $value);
        }

        return $this;
    }

    /**
     * Renders a view with the given data and returns the rendered content as a string.
     *
     * @param string $view The name of the view file to render.
     * @param array $data An associative array of data to pass to the view.
     * @return string The rendered view content.
     */
    public function renderView(string $view, array $data = []): string
    {
        $viewPath = str_replace('.', '/', $view);

        extract($data);

        ob_start();

        include base_path("resources/views/{$viewPath}.blade.php");

        return ob_get_clean();
    }

    /**
     * Return a JSON response.
     *
     * This method sets the appropriate headers for a JSON response and returns the JSON-encoded data.
     *
     * @param mixed $data The data to encode as JSON.
     * @param int $statusCode The HTTP status code (default: 200).
     * @param array<string, string> $headers Additional headers to include in the response.
     * @return JsonResponse
     */
    public function json(mixed $data, int $statusCode = 200, array $headers = []): JsonResponse
    {
        return response()->json($data, $statusCode, $headers);
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
        $this->body = $content;
        $this->statusCode = $statusCode;
        foreach ($headers as $name => $value) {
            $this->headers->set($name, $value);
        }
        $this->headers->set('Content-Type', 'text/plain');

        return $this;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Returns true if the response may safely be kept in a shared (surrogate) cache.
     *
     * Responses marked "private" with an explicit Cache-Control directive are
     * considered uncacheable.
     *
     * Responses with neither a freshness lifetime (Expires, max-age) nor cache
     * validator (Last-Modified, ETag) are considered uncacheable because there is
     * no way to tell when or how to remove them from the cache.
     *
     * Note that RFC 7231 and RFC 7234 possibly allow for a more permissive implementation,
     * for example "status codes that are defined as cacheable by default [...]
     * can be reused by a cache with heuristic expiration unless otherwise indicated"
     * (https://tools.ietf.org/html/rfc7231#section-6.1)
     *
     * @final
     */
    public function isCacheable(): bool
    {
        if (!\in_array($this->statusCode, [200, 203, 300, 301, 302, 404, 410])) {
            return false;
        }

        if ($this->headers->hasCacheControlDirective('no-store') || $this->headers->getCacheControlDirective('private')) {
            return false;
        }

        return $this->isValidateable() || $this->isFresh();
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Returns true if the response is "fresh".
     *
     * Fresh responses may be served from cache without any interaction with the
     * origin. A response is considered fresh when it includes a Cache-Control/max-age
     * indicator or Expires header and the calculated age is less than the freshness lifetime.
     *
     * @final
     */
    public function isFresh(): bool
    {
        return $this->getTtl() > 0;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Returns true if the response includes headers that can be used to validate
     * the response with the origin server using a conditional GET request.
     *
     * @final
     */
    public function isValidateable(): bool
    {
        return $this->headers->has('Last-Modified') || $this->headers->has('ETag');
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Marks the response as "private".
     *
     * It makes the response ineligible for serving other clients.
     *
     * @return $this
     *
     * @final
     */
    public function setPrivate(): static
    {
        $this->headers->removeCacheControlDirective('public');
        $this->headers->addCacheControlDirective('private');

        return $this;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Marks the response as "public".
     *
     * It makes the response eligible for serving other clients.
     *
     * @return $this
     *
     * @final
     */
    public function setPublic(): static
    {
        $this->headers->addCacheControlDirective('public');
        $this->headers->removeCacheControlDirective('private');

        return $this;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Marks the response as "immutable".
     *
     * @return $this
     *
     * @final
     */
    public function setImmutable(bool $immutable = true): static
    {
        if ($immutable) {
            $this->headers->addCacheControlDirective('immutable');
        } else {
            $this->headers->removeCacheControlDirective('immutable');
        }

        return $this;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Returns true if the response is marked as "immutable".
     *
     * @final
     */
    public function isImmutable(): bool
    {
        return $this->headers->hasCacheControlDirective('immutable');
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Returns true if the response must be revalidated by shared caches once it has become stale.
     *
     * This method indicates that the response must not be served stale by a
     * cache in any circumstance without first revalidating with the origin.
     * When present, the TTL of the response should not be overridden to be
     * greater than the value provided by the origin.
     *
     * @final
     */
    public function mustRevalidate(): bool
    {
        return $this->headers->hasCacheControlDirective('must-revalidate') || $this->headers->hasCacheControlDirective('proxy-revalidate');
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Returns the Date header as a DateTime instance.
     *
     * @throws \RuntimeException When the header is not parseable
     *
     * @final
     */
    public function getDate(): ?\DateTimeImmutable
    {
        return $this->headers->getDate('Date');
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Sets the Date header.
     *
     * @return $this
     *
     * @final
     */
    public function setDate(\DateTimeInterface $date): static
    {
        $date = \DateTimeImmutable::createFromInterface($date);
        $date = $date->setTimezone(new \DateTimeZone('UTC'));
        $this->headers->set('Date', $date->format('D, d M Y H:i:s') . ' GMT');

        return $this;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Returns the age of the response in seconds.
     *
     * @final
     */
    public function getAge(): int
    {
        if (null !== $age = $this->headers->get('Age')) {
            return (int) $age;
        }

        return max(time() - (int) $this->getDate()->format('U'), 0);
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Marks the response stale by setting the Age header to be equal to the maximum age of the response.
     *
     * @return $this
     */
    public function expire(): static
    {
        if ($this->isFresh()) {
            $this->headers->set('Age', $this->getMaxAge());
            $this->headers->remove('Expires');
        }

        return $this;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Returns the value of the Expires header as a DateTime instance.
     *
     * @final
     */
    public function getExpires(): ?\DateTimeImmutable
    {
        try {
            return $this->headers->getDate('Expires');
        } catch (\RuntimeException) {
            // according to RFC 2616 invalid date formats (e.g. "0" and "-1") must be treated as in the past
            return \DateTimeImmutable::createFromFormat('U', time() - 172800);
        }
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Sets the Expires HTTP header with a DateTime instance.
     *
     * Passing null as value will remove the header.
     *
     * @return $this
     *
     * @final
     */
    public function setExpires(?\DateTimeInterface $date): static
    {
        if (null === $date) {
            $this->headers->remove('Expires');

            return $this;
        }

        $date = \DateTimeImmutable::createFromInterface($date);
        $date = $date->setTimezone(new \DateTimeZone('UTC'));
        $this->headers->set('Expires', $date->format('D, d M Y H:i:s') . ' GMT');

        return $this;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Returns the number of seconds after the time specified in the response's Date
     * header when the response should no longer be considered fresh.
     *
     * First, it checks for a s-maxage directive, then a max-age directive, and then it falls
     * back on an expires header. It returns null when no maximum age can be established.
     *
     * @final
     */
    public function getMaxAge(): ?int
    {
        if ($this->headers->hasCacheControlDirective('s-maxage')) {
            return (int) $this->headers->getCacheControlDirective('s-maxage');
        }

        if ($this->headers->hasCacheControlDirective('max-age')) {
            return (int) $this->headers->getCacheControlDirective('max-age');
        }

        if (null !== $expires = $this->getExpires()) {
            $maxAge = (int) $expires->format('U') - (int) $this->getDate()->format('U');

            return max($maxAge, 0);
        }

        return null;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Sets the number of seconds after which the response should no longer be considered fresh.
     *
     * This method sets the Cache-Control max-age directive.
     *
     * @return $this
     *
     * @final
     */
    public function setMaxAge(int $value): static
    {
        $this->headers->addCacheControlDirective('max-age', $value);

        return $this;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Sets the number of seconds after which the response should no longer be returned by shared caches when backend is down.
     *
     * This method sets the Cache-Control stale-if-error directive.
     *
     * @return $this
     *
     * @final
     */
    public function setStaleIfError(int $value): static
    {
        $this->headers->addCacheControlDirective('stale-if-error', $value);

        return $this;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Sets the number of seconds after which the response should no longer return stale content by shared caches.
     *
     * This method sets the Cache-Control stale-while-revalidate directive.
     *
     * @return $this
     *
     * @final
     */
    public function setStaleWhileRevalidate(int $value): static
    {
        $this->headers->addCacheControlDirective('stale-while-revalidate', $value);

        return $this;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Sets the number of seconds after which the response should no longer be considered fresh by shared caches.
     *
     * This method sets the Cache-Control s-maxage directive.
     *
     * @return $this
     *
     * @final
     */
    public function setSharedMaxAge(int $value): static
    {
        $this->setPublic();
        $this->headers->addCacheControlDirective('s-maxage', $value);

        return $this;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Returns the response's time-to-live in seconds.
     *
     * It returns null when no freshness information is present in the response.
     *
     * When the response's TTL is 0, the response may not be served from cache without first
     * revalidating with the origin.
     *
     * @final
     */
    public function getTtl(): ?int
    {
        $maxAge = $this->getMaxAge();

        return null !== $maxAge ? max($maxAge - $this->getAge(), 0) : null;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Sets the response's time-to-live for shared caches in seconds.
     *
     * This method adjusts the Cache-Control/s-maxage directive.
     *
     * @return $this
     *
     * @final
     */
    public function setTtl(int $seconds): static
    {
        $this->setSharedMaxAge($this->getAge() + $seconds);

        return $this;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Sets the response's time-to-live for private/client caches in seconds.
     *
     * This method adjusts the Cache-Control/max-age directive.
     *
     * @return $this
     *
     * @final
     */
    public function setClientTtl(int $seconds): static
    {
        $this->setMaxAge($this->getAge() + $seconds);

        return $this;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Returns the Last-Modified HTTP header as a DateTime instance.
     *
     * @throws \RuntimeException When the HTTP header is not parseable
     *
     * @final
     */
    public function getLastModified(): ?\DateTimeImmutable
    {
        return $this->headers->getDate('Last-Modified');
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Sets the Last-Modified HTTP header with a DateTime instance.
     *
     * Passing null as value will remove the header.
     *
     * @return $this
     *
     * @final
     */
    public function setLastModified(?\DateTimeInterface $date): static
    {
        if (null === $date) {
            $this->headers->remove('Last-Modified');

            return $this;
        }

        $date = \DateTimeImmutable::createFromInterface($date);
        $date = $date->setTimezone(new \DateTimeZone('UTC'));
        $this->headers->set('Last-Modified', $date->format('D, d M Y H:i:s') . ' GMT');

        return $this;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Returns the literal value of the ETag HTTP header.
     *
     * @final
     */
    public function getEtag(): ?string
    {
        return $this->headers->get('ETag');
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Sets the ETag value.
     *
     * @param string|null $etag The ETag unique identifier or null to remove the header
     * @param bool        $weak Whether you want a weak ETag or not
     *
     * @return $this
     *
     * @final
     */
    public function setEtag(?string $etag, bool $weak = false): static
    {
        if (null === $etag) {
            $this->headers->remove('Etag');
        } else {
            if (!str_starts_with($etag, '"')) {
                $etag = '"' . $etag . '"';
            }

            $this->headers->set('ETag', (true === $weak ? 'W/' : '') . $etag);
        }

        return $this;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Sets the response's cache headers (validation and/or expiration).
     *
     * Available options are: must_revalidate, no_cache, no_store, no_transform, public, private, proxy_revalidate, max_age, s_maxage, immutable, last_modified and etag.
     *
     * @return $this
     *
     * @throws \InvalidArgumentException
     *
     * @final
     */
    public function setCache(array $options): static
    {
        if ($diff = array_diff(array_keys($options), array_keys(self::HTTP_RESPONSE_CACHE_CONTROL_DIRECTIVES))) {
            throw new \InvalidArgumentException(sprintf('Response does not support the following options: "%s".', implode('", "', $diff)));
        }

        if (isset($options['etag'])) {
            $this->setEtag($options['etag']);
        }

        if (isset($options['last_modified'])) {
            $this->setLastModified($options['last_modified']);
        }

        if (isset($options['max_age'])) {
            $this->setMaxAge($options['max_age']);
        }

        if (isset($options['s_maxage'])) {
            $this->setSharedMaxAge($options['s_maxage']);
        }

        if (isset($options['stale_while_revalidate'])) {
            $this->setStaleWhileRevalidate($options['stale_while_revalidate']);
        }

        if (isset($options['stale_if_error'])) {
            $this->setStaleIfError($options['stale_if_error']);
        }

        foreach (self::HTTP_RESPONSE_CACHE_CONTROL_DIRECTIVES as $directive => $hasValue) {
            if (!$hasValue && isset($options[$directive])) {
                if ($options[$directive]) {
                    $this->headers->addCacheControlDirective(str_replace('_', '-', $directive));
                } else {
                    $this->headers->removeCacheControlDirective(str_replace('_', '-', $directive));
                }
            }
        }

        if (isset($options['public'])) {
            if ($options['public']) {
                $this->setPublic();
            } else {
                $this->setPrivate();
            }
        }

        if (isset($options['private'])) {
            if ($options['private']) {
                $this->setPrivate();
            } else {
                $this->setPublic();
            }
        }

        return $this;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Modifies the response so that it conforms to the rules defined for a 304 status code.
     *
     * This sets the status, removes the body, and discards any headers
     * that MUST NOT be included in 304 responses.
     *
     * @return $this
     *
     * @see https://tools.ietf.org/html/rfc2616#section-10.3.5
     *
     * @final
     */
    public function setNotModified(): static
    {
        $this->setStatusCode(304);
        $this->setBody(null);

        // remove headers that MUST NOT be included with 304 Not Modified responses
        foreach (['Allow', 'Content-Encoding', 'Content-Language', 'Content-Length', 'Content-MD5', 'Content-Type', 'Last-Modified'] as $header) {
            $this->headers->remove($header);
        }

        return $this;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Returns true if the response includes a Vary header.
     *
     * @final
     */
    public function hasVary(): bool
    {
        return null !== $this->headers->get('Vary');
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Returns an array of header names given in the Vary header.
     *
     * @final
     */
    public function getVary(): array
    {
        if (!$vary = $this->headers->all('Vary')) {
            return [];
        }

        $ret = [];
        foreach ($vary as $item) {
            $ret[] = preg_split('/[\s,]+/', $item);
        }

        return array_merge([], ...$ret);
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Sets the Vary header.
     *
     * @param bool $replace Whether to replace the actual value or not (true by default)
     *
     * @return $this
     *
     * @final
     */
    public function setVary(string|array $headers, bool $replace = true): static
    {
        $this->headers->set('Vary', $headers, $replace);

        return $this;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Determines if the Response validators (ETag, Last-Modified) match
     * a conditional value specified in the Request.
     *
     * If the Response is not modified, it sets the status code to 304 and
     * removes the actual content by calling the setNotModified() method.
     *
     * @final
     */
    public function isNotModified(Request $request): bool
    {
        if (!$request->isMethodCacheable()) {
            return false;
        }

        $notModified = false;
        $lastModified = $this->headers->get('Last-Modified');
        $modifiedSince = $request->headers->get('If-Modified-Since');

        if (($ifNoneMatchEtags = $request->getETags()) && (null !== $etag = $this->getEtag())) {
            if (0 == strncmp($etag, 'W/', 2)) {
                $etag = substr($etag, 2);
            }

            // Use weak comparison as per https://tools.ietf.org/html/rfc7232#section-3.2.
            foreach ($ifNoneMatchEtags as $ifNoneMatchEtag) {
                if (0 == strncmp($ifNoneMatchEtag, 'W/', 2)) {
                    $ifNoneMatchEtag = substr($ifNoneMatchEtag, 2);
                }

                if ($ifNoneMatchEtag === $etag || '*' === $ifNoneMatchEtag) {
                    $notModified = true;
                    break;
                }
            }
        }
        // Only do If-Modified-Since date comparison when If-None-Match is not present as per https://tools.ietf.org/html/rfc7232#section-3.3.
        elseif ($modifiedSince && $lastModified) {
            $notModified = strtotime($modifiedSince) >= strtotime($lastModified);
        }

        if ($notModified) {
            $this->setNotModified();
        }

        return $notModified;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Is response invalid?
     *
     * @see https://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html
     *
     * @final
     */
    public function isInvalid(): bool
    {
        return $this->statusCode < 100 || $this->statusCode >= 600;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Is response successful?
     *
     * @final
     */
    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Is the response a redirect?
     *
     * @final
     */
    public function isRedirection(): bool
    {
        return $this->statusCode >= 300 && $this->statusCode < 400;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Is there a client error?
     *
     * @final
     */
    public function isClientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Was there a server side error?
     *
     * @final
     */
    public function isServerError(): bool
    {
        return $this->statusCode >= 500 && $this->statusCode < 600;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Is the response OK?
     *
     * @final
     */
    public function isOk(): bool
    {
        return 200 === $this->statusCode;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Is the response forbidden?
     *
     * @final
     */
    public function isForbidden(): bool
    {
        return 403 === $this->statusCode;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Is the response a not found error?
     *
     * @final
     */
    public function isNotFound(): bool
    {
        return 404 === $this->statusCode;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Is the response a redirect of some form?
     *
     * @final
     */
    public function isRedirect(?string $location = null): bool
    {
        return in_array(
            $this->statusCode,
            [201, 301, 302, 303, 307, 308]
        ) &&
            (null === $location ?: $location == $this->headers->get('Location')
            );
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Marks a response as safe according to RFC8674.
     *
     * @see https://tools.ietf.org/html/rfc8674
     */
    public function setContentSafe(bool $safe = true): void
    {
        if ($safe) {
            $this->headers->set('Preference-Applied', 'safe');
        } elseif ('safe' === $this->headers->get('Preference-Applied')) {
            $this->headers->remove('Preference-Applied');
        }

        $this->setVary('Prefer', false);
    }

    /**
     * Get the json content type
     *
     * @param mixed $content
     * @return mixed
     */
    public function prepareBody(mixed $content)
    {
        $this->setOriginal($content);

        if (
            is_array($content) ||
            $content instanceof \JsonSerializable ||
            $content instanceof \Phaseolies\Database\Eloquent\Model ||
            $content instanceof \Phaseolies\Database\Eloquent\Builder ||
            $content instanceof \stdClass ||
            $content instanceof \ArrayObject
        ) {
            $content = json_encode($content);
        } elseif ($content instanceof \Phaseolies\Support\Collection || is_object($content)) {
            $content = json_encode($content->toArray());
        }

        $this->setBody($content);

        return $content;
    }
}
