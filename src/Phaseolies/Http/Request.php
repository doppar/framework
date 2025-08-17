<?php

namespace Phaseolies\Http;

use Phaseolies\Utilities\IpUtils;
use Phaseolies\Support\Session;
use Phaseolies\Support\File;
use Phaseolies\Support\Facades\Str;
use Phaseolies\Support\Facades\App;
use Phaseolies\Http\Validation\Rule;
use Phaseolies\Http\Support\RequestParser;
use Phaseolies\Http\Support\RequestHelper;
use Phaseolies\Http\Support\InteractsWithContentTypes;
use Phaseolies\Http\ServerBag;
use Phaseolies\Http\Response\HeaderUtils;
use Phaseolies\Http\Response\AcceptHeader;
use Phaseolies\Http\ParameterBag;
use Phaseolies\Http\InputBag;
use Phaseolies\Http\HeaderBag;

class Request
{
    use RequestParser, RequestHelper, Rule, InteractsWithContentTypes;

    // The following methods are derived from code of the PHP Symfony Framework
    public const HEADER_FORWARDED = 0b000001;
    public const HEADER_X_FORWARDED_FOR = 0b000010;
    public const HEADER_X_FORWARDED_HOST = 0b000100;
    public const HEADER_X_FORWARDED_PROTO = 0b001000;
    public const HEADER_X_FORWARDED_PORT = 0b010000;
    public const HEADER_X_FORWARDED_PREFIX = 0b100000;

    // The following methods are derived from code of the PHP Symfony Framework
    public const HEADER_X_FORWARDED_AWS_ELB = 0b0011010;
    public const HEADER_X_FORWARDED_TRAEFIK = 0b0111110;

    // The following methods are derived from code of the PHP Symfony Framework
    public const METHOD_HEAD = "HEAD";
    public const METHOD_GET = "GET";
    public const METHOD_POST = "POST";
    public const METHOD_PUT = "PUT";
    public const METHOD_PATCH = "PATCH";
    public const METHOD_DELETE = "DELETE";
    public const METHOD_PURGE = "PURGE";
    public const METHOD_OPTIONS = "OPTIONS";
    public const METHOD_TRACE = "TRACE";
    public const METHOD_CONNECT = "CONNECT";
    public const METHOD_ANY = "ANY";

    // The following methods are derived from code of the PHP Symfony Framework
    private const FORWARDED_PARAMS = [
        self::HEADER_X_FORWARDED_FOR => "for",
        self::HEADER_X_FORWARDED_HOST => "host",
        self::HEADER_X_FORWARDED_PROTO => "proto",
        self::HEADER_X_FORWARDED_PORT => "port",
    ];

    // The following methods are derived from code of the PHP Symfony Framework
    private bool $isHostValid = true;
    private bool $isForwardedValid = true;
    private bool $isSafeContentPreferred;
    private array $trustedValuesCache = [];
    private static int $trustedHeaderSet = -1;

    /**
     * @var string[]|null
     */
    protected ?array $acceptableContentTypes = null;

    /**
     * @var string[]
     */
    protected static array $trustedProxies = [];

    // The following methods are derived from code of the PHP Symfony Framework
    private const TRUSTED_HEADERS = [
        self::HEADER_FORWARDED => "FORWARDED",
        self::HEADER_X_FORWARDED_FOR => "X_FORWARDED_FOR",
        self::HEADER_X_FORWARDED_HOST => "X_FORWARDED_HOST",
        self::HEADER_X_FORWARDED_PROTO => "X_FORWARDED_PROTO",
        self::HEADER_X_FORWARDED_PORT => "X_FORWARDED_PORT",
        self::HEADER_X_FORWARDED_PREFIX => "X_FORWARDED_PREFIX",
    ];

    private const VALID_HTTP_METHODS = [
        self::METHOD_GET,
        self::METHOD_HEAD,
        self::METHOD_POST,
        self::METHOD_PUT,
        self::METHOD_DELETE,
        self::METHOD_CONNECT,
        self::METHOD_OPTIONS,
        self::METHOD_PATCH,
        self::METHOD_PURGE,
        self::METHOD_TRACE,
        self::METHOD_ANY,
    ];

    /**
     * @var string[]
     */
    protected static array $trustedHostPatterns = [];

    /**
     * @var string[]
     */
    protected static array $trustedHosts = [];

    public InputBag $request;
    public InputBag $query;
    public ParameterBag $attributes;
    public InputBag $cookies;
    public ServerBag $server;
    public HeaderBag $headers;
    public Session $session;
    public array $files;
    protected $content;
    protected static bool $httpMethodParameterOverride = false;
    protected ?string $pathInfo = null;
    protected ?string $requestUri = null;
    protected ?string $baseUrl = null;
    protected ?string $method = null;
    protected ?string $locale = null;
    protected string $defaultLocale = "en";
    protected ?string $format = null;
    protected static ?array $formats = null;
    protected ?array $languages = null;
    protected ?array $charsets = null;
    protected ?array $encodings = null;
    private bool $isIisRewrite = false;
    protected array $routeParams = [];

    /**
     * Constructor: Initializes request data from PHP superglobals.
     */
    public function __construct()
    {
        $this->server = new ServerBag($_SERVER);
        $this->headers = new HeaderBag($this->server->getHeaders());
        $this->request = new InputBag($this->createFromGlobals());
        $this->query = new InputBag($_GET);
        $this->attributes = new ParameterBag($_SERVER);
        $this->cookies = new InputBag($_COOKIE);
        $this->session = new Session($_SESSION);
        $this->files = $_FILES;
        $this->content = $this->content();
        $this->requestUri = $this->getPath();
        $this->baseUrl = base_url();
        $this->method = $this->method();
        $this->format = null;
        $this->languages = null;
        $this->charsets = null;
        $this->encodings = null;
    }

    /**
     * Initializes headers from the $_SERVER superglobal.
     */
    protected function initializeHeaders(): void
    {
        foreach ($this->server->all() as $key => $value) {
            if (str_starts_with($key, "HTTP_")) {
                $header = str_replace("_", "-", substr($key, 5));
                $this->headers->set($header, $value);
            }
        }
    }

    /**
     * Magic method to allow dynamic access to input or file data.
     *
     * @param string $name The name of the input or file.
     * @return mixed The input value or File object, or null if not found.
     */
    public function __get(string $name): mixed
    {
        return $this->input($name) ?? $this->file($name);
    }

    /**
     * Creates request data from PHP superglobals.
     *
     * @return array The request data.
     */
    public function createFromGlobals(): array
    {
        $request = $_POST + $_GET;

        $contentType = $this->server->get("CONTENT_TYPE", "");
        $requestMethod = strtoupper($this->server->get("REQUEST_METHOD", "GET"));

        if (
            str_starts_with($contentType, "application/json") &&
            in_array($requestMethod, ["PUT", "DELETE", "PATCH", "POST", "ANY", "HEAD", "OPTIONS"], true)
        ) {
            $rawContent = $this->getContent();

            if ($rawContent !== false) {
                $jsonData = json_decode($rawContent, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $request = array_merge($request, $jsonData);
                } else {
                    throw new \RuntimeException("Invalid JSON data in request body.");
                }
            } else {
                throw new \RuntimeException("Unable to retrieve request content.");
            }
        } elseif (
            str_starts_with($contentType, "application/x-www-form-urlencoded") &&
            in_array($requestMethod, ["PUT", "DELETE", "PATCH", "POST", "ANY", "HEAD", "OPTIONS"], true)
        ) {
            $rawContent = $this->getContent();
            if ($rawContent !== false) {
                parse_str($rawContent, $data);
                $request = $data;
            } else {
                throw new \RuntimeException("Unable to retrieve request content.");
            }
        } elseif (
            str_starts_with($contentType, "multipart/form-data") &&
            in_array($requestMethod, ["PUT", "DELETE", "PATCH", "POST", "ANY", "HEAD", "OPTIONS"], true)
        ) {
            $rawContent = $this->getContent();
            if ($rawContent !== false) {
                $request = array_merge($request, $this->parseMultipartFormData());
            } else {
                throw new \RuntimeException("Unable to retrieve request content.");
            }
        }

        if (!$this->isValidRequest()) {
            throw new \RuntimeException("Invalid request.");
        }

        return $request;
    }

    /**
     * Parses a raw multipart/form-data request body into an associative array.
     *
     * This method extracts the boundary from the Content-Type header, splits the body
     * into individual parts, parses each part's headers, and collects the form field
     * values.
     *
     * @return array Associative array of form fields and their values
     */
    protected function parseMultipartFormData(): array
    {
        $rawContent = $this->getContent();
        if ($rawContent === false || empty($rawContent)) {
            return [];
        }

        $data = [];
        $boundary = $this->extractBoundary($this->headers->get('CONTENT_TYPE'));

        if (!$boundary) {
            return [];
        }

        $parts = preg_split('/\R?-+' . preg_quote($boundary, '/') . '/', $rawContent);
        array_pop($parts);

        foreach ($parts as $part) {
            if (empty(trim($part))) {
                continue;
            }

            [$headers, $value] = explode("\r\n\r\n", $part, 2);
            $headers = $this->parsePartHeaders($headers);

            if (isset($headers['content-disposition']['name'])) {
                $name = $headers['content-disposition']['name'];
                $data[$name] = trim($value);
            }
        }

        return $data;
    }

    /**
     * Parses a block of headers (as a string) into an associative array.
     *
     * @param string $headers The raw headers string, each header separated by CRLF (\r\n)
     * @return array An associative array of headers. If a header is "Content-Disposition", 
     *               its value is parsed into an array with type and parameters.
     */
    protected function parsePartHeaders(string $headers): array
    {
        $result = [];
        $headerLines = explode("\r\n", $headers);

        foreach ($headerLines as $headerLine) {
            if (preg_match('/^([^:]+):\s*(.*)$/', $headerLine, $matches)) {
                $name = strtolower($matches[1]);
                $value = $matches[2];

                if ($name === 'content-disposition') {
                    $result[$name] = $this->parseContentDisposition($value);
                } else {
                    $result[$name] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * Parses a Content-Disposition header value into its type and parameters.
     *
     * Example input: 'form-data; name="file"; filename="example.txt"'
     * Output:
     * [
     *     'type' => 'form-data',
     *     'name' => 'file',
     *     'filename' => 'example.txt'
     * ]
     *
     * @param string $value The Content-Disposition header value
     * @return array Associative array with 'type' and any additional parameters
     */
    protected function parseContentDisposition(string $value): array
    {
        $result = [];
        $parts = explode(';', $value);
        $result['type'] = trim(array_shift($parts));

        foreach ($parts as $part) {
            if (preg_match('/^\s*([^=]+)="?([^"]+)"?$/', trim($part), $matches)) {
                $result[$matches[1]] = $matches[2];
            }
        }

        return $result;
    }

    /**
     * Extracts the boundary string from a Content-Type header.
     *
     * Example input: 'multipart/form-data; boundary="----WebKitFormBoundaryxyz"'
     * Output: '----WebKitFormBoundaryxyz'
     *
     * @param string $contentType The Content-Type header value
     * @return string|null The boundary string if found, null otherwise
     */
    protected function extractBoundary(string $contentType): ?string
    {
        if (preg_match('/boundary=(.*)$/', $contentType, $matches)) {
            return trim($matches[1], '"');
        }

        return null;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Retrieves the request body content.
     *
     * @param bool $asResource Whether to return a resource instead of a string.
     * @return string|resource The request content.
     */
    public function getContent(bool $asResource = false)
    {
        $currentContentIsResource = \is_resource($this->content);

        if (true === $asResource) {
            if ($currentContentIsResource) {
                rewind($this->content);
                return $this->content;
            }

            if (\is_string($this->content)) {
                $resource = fopen("php://temp", "r+");
                fwrite($resource, $this->content);
                rewind($resource);
                return $resource;
            }

            $this->content = false;
            return fopen("php://input", "r");
        }

        if ($currentContentIsResource) {
            rewind($this->content);
            return stream_get_contents($this->content);
        }

        if (null === $this->content || false === $this->content) {
            $this->content = file_get_contents("php://input");
        }

        return $this->content;
    }

    /**
     * Checks if the request method is valid.
     *
     * @return bool True if valid, false otherwise.
     */
    public function isValidMethod(): bool
    {
        return in_array(
            strtoupper($this->method),
            [
                self::METHOD_GET,
                self::METHOD_POST,
                self::METHOD_PUT,
                self::METHOD_PATCH,
                self::METHOD_DELETE,
                self::METHOD_OPTIONS,
                self::METHOD_HEAD,
                self::METHOD_TRACE,
                self::METHOD_CONNECT,
                self::METHOD_PURGE,
            ],
            true
        );
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Validates the request based on trusted proxies and headers.
     *
     * @return bool True if the request is valid, false otherwise.
     */
    public function isValidRequest(): bool
    {
        if (empty($this->trustedProxies)) {
            // No trusted proxies, all requests are valid.
            return true;
        }

        $remoteAddress = $this->server->get("REMOTE_ADDR");

        if (!in_array($remoteAddress, $this->trustedProxies, true)) {
            // Request not from a trusted proxy.
            return true;
        }

        if ($this->trustedHeaderSet === 0) {
            // No trusted headers set, all requests are valid.
            return true;
        }

        foreach (self::TRUSTED_HEADERS as $headerBit => $headerName) {
            if (($this->trustedHeaderSet & $headerBit) === $headerBit) {
                if (!$this->server->has("HTTP_" . $headerName)) {
                    // Required trusted header is missing.
                    return false;
                }
            }
        }

        return true;
    }

    public function __toString(): string
    {
        $content = $this->getContent();

        $cookieHeader = '';
        $cookies = [];

        foreach ($this->cookies as $k => $v) {
            $cookies[] = \is_array($v) ? http_build_query([$k => $v], '', '; ', \PHP_QUERY_RFC3986) : "$k=$v";
        }

        if ($cookies) {
            $cookieHeader = 'Cookie: ' . implode('; ', $cookies) . "\r\n";
        }

        return
            \sprintf('%s %s %s', $this->getMethod(), $this->getRequestUri(), $this->server->get('SERVER_PROTOCOL')) . "\r\n" .
            $this->headers .
            $cookieHeader . "\r\n" .
            $content;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Sets a list of trusted host patterns.
     *
     * You should only list the hosts you manage using regexs.
     *
     * @param array $hostPatterns A list of trusted host patterns
     */
    public static function setTrustedHosts(array $hostPatterns): void
    {
        self::$trustedHostPatterns = array_map(fn($hostPattern) => \sprintf('{%s}i', $hostPattern), $hostPatterns);
        // we need to reset trusted hosts on trusted host patterns change
        self::$trustedHosts = [];
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Gets the list of trusted host patterns.
     *
     * @return string[]
     */
    public static function getTrustedHosts(): array
    {
        return self::$trustedHostPatterns;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Indicates whether this request originated from a trusted proxy.
     *
     * This can be useful to determine whether or not to trust the
     * contents of a proxy-specific header.
     */
    public function isFromTrustedProxy(): bool
    {
        return self::$trustedProxies && IpUtils::checkIp($this->server->get('REMOTE_ADDR', ''), self::$trustedProxies);
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Enables support for the _method request parameter to determine the intended HTTP method.
     *
     * Be warned that enabling this feature might lead to CSRF issues in your code.
     * Check that you are using CSRF tokens when required.
     * If the HTTP method parameter override is enabled, an html-form with method "POST" can be altered
     * and used to send a "PUT" or "DELETE" request via the _method request parameter.
     * If these methods are not protected against CSRF, this presents a possible vulnerability.
     *
     * The HTTP method can only be overridden when the real HTTP method is POST.
     */
    public static function enableHttpMethodParameterOverride(): void
    {
        self::$httpMethodParameterOverride = true;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Checks whether support for the _method request parameter is enabled.
     */
    public static function getHttpMethodParameterOverride(): bool
    {
        return self::$httpMethodParameterOverride;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Retrieves the value of a trusted header from the request.
     *
     * This method checks if a given header (identified by its constant)
     * is present in the request's headers. If found, it returns the header's value;
     * otherwise, it returns null.
     *
     * @param int $headerConstant The constant representing the trusted header.
     * @return string|null The value of the header if it exists, or null if not found.
     */
    public function getTrustedHeaderValue(int $headerConstant): ?string
    {
        $headerName = self::TRUSTED_HEADERS[$headerConstant] ?? null;

        return $headerName ? $this->headers->get($headerName) : null;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Normalizes a query string.
     *
     * It builds a normalized query string, where keys/value pairs are alphabetized,
     * have consistent escaping and unneeded delimiters are removed.
     */
    public static function normalizeQueryString(?string $qs): string
    {
        if ('' === ($qs ?? '')) {
            return '';
        }

        $qs = HeaderUtils::parseQuery($qs);
        ksort($qs);

        return http_build_query($qs, '', '&', \PHP_QUERY_RFC3986);
    }

    /**
     * Retrieves all input data from the request.
     *
     * @return array<string, mixed> The input data.
     */
    public function all(): ?array
    {
        return $this->request->all();
    }

    /**
     * Merges new input data into the request.
     *
     * @param array<string, mixed> $input The input data to merge.
     * @return self The current instance.
     */
    public function merge(array $input): self
    {
        $this->request->replace(array_merge($this->request->all(), $input));

        return $this;
    }

    /**
     * Retrieves the current request URI path.
     *
     * @return string The decoded URI path.
     */
    public function getPath(): string
    {
        return urldecode(
            parse_url($this->server->get("REQUEST_URI", "/"), PHP_URL_PATH)
        );
    }

    /**
     * Gets the request "intended" method.
     *
     * If the X-HTTP-Method-Override header is set, and if the method is a POST,
     * then it is used to determine the "real" intended HTTP method.
     *
     * The _method request parameter can also be used to determine the HTTP method,
     * but only if enableHttpMethodParameterOverride() has been called.
     *
     * The method is always an uppercased string.
     * @return string
     * @@throws \InvalidArgumentException
     */
    public function getMethod(): string
    {
        $this->method = strtoupper($this->server->get("REQUEST_METHOD", "GET"));

        if ($this->headers->has("X-HTTP-METHOD-OVERRIDE")) {
            $method = strtoupper($this->headers->get("X-HTTP-METHOD-OVERRIDE"));
            $this->headers->set("X-HTTP-METHOD-OVERRIDE", $method);
            self::$httpMethodParameterOverride = true;
        } elseif ($this->request->has("_method")) {
            $method = strtoupper($this->request->get("_method"));
            $this->headers->set("X-HTTP-METHOD-OVERRIDE", $method);
            self::$httpMethodParameterOverride = true;
        } else {
            $method = $this->method;
        }

        if (in_array($method, self::VALID_HTTP_METHODS, true)) {
            $this->method = $method;
            return $this->method;
        }

        throw new \InvalidArgumentException("Invalid HTTP method override: $method.");
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Gets the "real" request method.
     *
     * @see getMethod()
     */
    public function getRealMethod(): string
    {
        return strtoupper($this->server->get('REQUEST_METHOD', 'GET'));
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Associates a format with mime types.
     *
     * @param string|string[] $mimeTypes The associated mime types (the preferred one must be the first as it will be used as the content type)
     */
    public function setFormat(?string $format, string|array $mimeTypes): void
    {
        if (null === static::$formats) {
            static::initializeFormats();
        }

        static::$formats[$format] = \is_array($mimeTypes) ? $mimeTypes : [$mimeTypes];
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Gets the format associated with the mime type.
     */
    public function getFormat(?string $mimeType): ?string
    {
        $canonicalMimeType = null;
        if ($mimeType && false !== $pos = strpos($mimeType, ';')) {
            $canonicalMimeType = trim(substr($mimeType, 0, $pos));
        }

        if (null === static::$formats) {
            static::initializeFormats();
        }

        foreach (static::$formats as $format => $mimeTypes) {
            if (\in_array($mimeType, (array) $mimeTypes, true)) {
                return $format;
            }
            if (null !== $canonicalMimeType && \in_array($canonicalMimeType, (array) $mimeTypes, true)) {
                return $format;
            }
        }

        return null;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Sets the request format.
     */
    public function setRequestFormat(?string $format): void
    {
        $this->format = $format;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Gets the usual name of the format associated with the request's media type (provided in the Content-Type header).
     */
    public function getContentTypeFormat(): ?string
    {
        return $this->getFormat($this->headers->get('CONTENT_TYPE', ''));
    }

    /**
     * Sets the locale.
     */
    public function setLocale(string $locale): void
    {
        App::setLocale($this->locale = $locale);
    }

    /**
     * Get the locale.
     */
    public function getLocale(): string
    {
        return $this->locale ?? $this->defaultLocale;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Returns the path as relative reference from the current Request path.
     *
     * Only the URIs path component (no schema, host etc.) is relevant and must be given.
     * Both paths must be absolute and not contain relative parts.
     * Relative URLs from one resource to another are useful when generating self-contained downloadable document archives.
     * Furthermore, they can be used to reduce the link size in documents.
     *
     * Example target paths, given a base path of "/a/b/c/d":
     * - "/a/b/c/d"     -> ""
     * - "/a/b/c/"      -> "./"
     * - "/a/b/"        -> "../"
     * - "/a/b/c/other" -> "other"
     * - "/a/x/y"       -> "../../x/y"
     */
    public function getRelativeUriForPath(string $path): string
    {
        // be sure that we are dealing with an absolute path
        if (!isset($path[0]) || '/' !== $path[0]) {
            return $path;
        }

        if ($path === $basePath = $this->getPathInfo()) {
            return '';
        }

        $sourceDirs = explode('/', isset($basePath[0]) && '/' === $basePath[0] ? substr($basePath, 1) : $basePath);
        $targetDirs = explode('/', substr($path, 1));
        array_pop($sourceDirs);
        $targetFile = array_pop($targetDirs);

        foreach ($sourceDirs as $i => $dir) {
            if (isset($targetDirs[$i]) && $dir === $targetDirs[$i]) {
                unset($sourceDirs[$i], $targetDirs[$i]);
            } else {
                break;
            }
        }

        $targetDirs[] = $targetFile;
        $path = str_repeat('../', \count($sourceDirs)) . implode('/', $targetDirs);

        // A reference to the same base directory or an empty subdirectory must be prefixed with "./".
        // This also applies to a segment with a colon character (e.g., "file:colon") that cannot be used
        // as the first segment of a relative-path reference, as it would be mistaken for a scheme name
        // (see https://tools.ietf.org/html/rfc3986#section-4.2).
        return !isset($path[0]) || '/' === $path[0]
            || false !== ($colonPos = strpos($path, ':')) && ($colonPos < ($slashPos = strpos($path, '/')) || false === $slashPos)
            ? "./$path" : $path;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Generates the normalized query string for the Request.
     *
     * It builds a normalized query string, where keys/value pairs are alphabetized
     * and have consistent escaping.
     */
    public function getQueryString(): ?string
    {
        $qs = static::normalizeQueryString($this->server->get('QUERY_STRING'));

        return '' === $qs ? null : $qs;
    }

    /**
     * Checks if the request method is GET.
     *
     * @return bool True if the method is GET, false otherwise.
     */
    public function isGet(): bool
    {
        return $this->getMethod() === "GET";
    }

    /**
     * Checks if the request method is POST.
     *
     * @return bool True if the method is POST, false otherwise.
     */
    public function isPost(): bool
    {
        return $this->getMethod() === "POST";
    }

    /**
     * Checks if the request method is PUT.
     *
     * @return bool True if the method is PUT, false otherwise.
     */
    public function isPut(): bool
    {
        return $this->getMethod() === "PUT";
    }

    /**
     * Checks if the request method is PATCH.
     *
     * @return bool True if the method is PATCH, false otherwise.
     */
    public function isPatch(): bool
    {
        return $this->getMethod() === "PATCH";
    }

    /**
     * Checks if the request method is DELETE.
     *
     * @return bool True if the method is DELETE, false otherwise.
     */
    public function isDelete(): bool
    {
        return $this->getMethod() === "DELETE";
    }

    /**
     * Check if the request method is HEAD.
     *
     * @return bool
     */
    public function isHead(): bool
    {
        return $this->getMethod() === 'HEAD';
    }

    /**
     * Check if the request method is ANY.
     *
     * @return bool
     */
    public function isAny(): bool
    {
        return true;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Gets a "parameter" value from any bag.
     *
     * This method is mainly useful for libraries that want to provide some flexibility. If you don't need the
     * flexibility in controllers, it is better to explicitly get request parameters from the appropriate
     * public property instead (attributes, query, request).
     *
     * Order of precedence: PATH (routing placeholders or custom attributes), GET, POST
     *
     * @internal use explicit input sources instead
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if ($this !== $result = $this->attributes->get($key, $this)) {
            return $result;
        }

        if ($this->query->has($key)) {
            return $this->query->all()[$key];
        }

        if ($this->request->has($key)) {
            return $this->request->all()[$key];
        }

        return $default;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Returns the client IP addresses.
     *
     * In the returned array the most trusted IP address is first, and the
     * least trusted one last. The "real" client IP address is the last one,
     * but this is also the least trusted one. Trusted proxies are stripped.
     *
     * Use this method carefully; you should use getClientIp() instead.
     *
     * @see getClientIp()
     */
    public function getClientIps(): array
    {
        $ip = $this->server->get('REMOTE_ADDR');

        if (!$this->isFromTrustedProxy()) {
            return [$ip];
        }

        return $this->getTrustedValues(self::HEADER_X_FORWARDED_FOR, $ip) ?: [$ip];
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Returns the client IP address.
     *
     * This method can read the client IP address from the "X-Forwarded-For" header
     * when trusted proxies were set via "setTrustedProxies()". The "X-Forwarded-For"
     * header value is a comma+space separated list of IP addresses, the left-most
     * being the original client, and each successive proxy that passed the request
     * adding the IP address where it received the request from.
     *
     * If your reverse proxy uses a different header name than "X-Forwarded-For",
     * ("Client-Ip" for instance), configure it via the $trustedHeaderSet
     * argument of the Request::setTrustedProxies() method instead.
     *
     * @see getClientIps()
     * @see https://wikipedia.org/wiki/X-Forwarded-For
     */
    public function getClientIp(): ?string
    {
        return $this->getClientIps()[0];
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Returns current script name.
     */
    public function getScriptName(): string
    {
        return $this->server->get('SCRIPT_NAME', $this->server->get('ORIG_SCRIPT_NAME', ''));
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Gets the request's scheme.
     */
    public function getScheme(): string
    {
        return $this->isSecure() ? 'https' : 'http';
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Returns the protocol version.
     *
     * If the application is behind a proxy, the protocol version used in the
     * requests between the client and the proxy and between the proxy and the
     * server might be different. This returns the former (from the "Via" header)
     * if the proxy is trusted (see "setTrustedProxies()"), otherwise it returns
     * the latter (from the "SERVER_PROTOCOL" server parameter).
     */
    public function getProtocolVersion(): ?string
    {
        if ($this->isFromTrustedProxy()) {
            preg_match('~^(HTTP/)?([1-9]\.[0-9]) ~', $this->headers->get('Via') ?? '', $matches);

            if ($matches) {
                return 'HTTP/' . $matches[2];
            }
        }

        return $this->server->get('SERVER_PROTOCOL');
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Returns the HTTP host being requested.
     *
     * The port name will be appended to the host if it's non-standard.
     */
    public function host(): string
    {
        $scheme = $this->getScheme();
        $port = $this->port();

        if (('http' === $scheme && 80 == $port) || ('https' === $scheme && 443 == $port)) {
            return $this->getHost();
        }

        return $this->getHost() . ':' . $port;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Returns the host name.
     *
     * This method can read the client host name from the "X-Forwarded-Host" header
     * when trusted proxies were set via "setTrustedProxies()".
     *
     * The "X-Forwarded-Host" header must contain the client host name.
     *
     * @throws SuspiciousOperationException when the host name is invalid or not trusted
     */
    public function getHost(): string
    {
        if ($this->isFromTrustedProxy() && $host = $this->getTrustedValues(self::HEADER_X_FORWARDED_HOST)) {
            $host = $host[0];
        } elseif (!$host = $this->headers->get('HOST')) {
            if (!$host = $this->server->get('SERVER_NAME')) {
                $host = $this->server->get('SERVER_ADDR', '');
            }
        }

        // trim and remove port number from host
        // host is lowercase as per RFC 952/2181
        $host = strtolower(preg_replace('/:\d+$/', '', trim($host)));

        // as the host can come from the user (HTTP_HOST and depending on the configuration, SERVER_NAME too can come from the user)
        // check that it does not contain forbidden characters (see RFC 952 and RFC 2181)
        // use preg_replace() instead of preg_match() to prevent DoS attacks with long host names
        if ($host && '' !== preg_replace('/(?:^\[)?[a-zA-Z0-9-:\]_]+\.?/', '', $host)) {
            if (!$this->isHostValid) {
                return '';
            }
            $this->isHostValid = false;

            throw new \Exception(\sprintf('Invalid Host "%s".', $host));
        }

        if (\count(self::$trustedHostPatterns) > 0) {
            // to avoid host header injection attacks, you should provide a list of trusted host patterns

            if (\in_array($host, self::$trustedHosts, true)) {
                return $host;
            }

            foreach (self::$trustedHostPatterns as $pattern) {
                if (preg_match($pattern, $host)) {
                    self::$trustedHosts[] = $host;

                    return $host;
                }
            }

            if (!$this->isHostValid) {
                return '';
            }
            $this->isHostValid = false;

            throw new \Exception(\sprintf('Untrusted Host "%s".', $host));
        }

        return $host;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Returns the port on which the request is made.
     *
     * This method can read the client port from the "X-Forwarded-Port" header
     * when trusted proxies were set via "setTrustedProxies()".
     *
     * The "X-Forwarded-Port" header must contain the client port.
     *
     * @return int|string|null Can be a string if fetched from the server bag
     */
    public function port(): int|string|null
    {
        if ($this->isFromTrustedProxy() && $host = $this->getTrustedValues(self::HEADER_X_FORWARDED_PORT)) {
            $host = $host[0];
        } elseif ($this->isFromTrustedProxy() && $host = $this->getTrustedValues(self::HEADER_X_FORWARDED_HOST)) {
            $host = $host[0];
        } elseif (!$host = $this->headers->get('HOST')) {
            return $this->server->get('SERVER_PORT');
        }

        if ('[' === $host[0]) {
            $pos = strpos($host, ':', strrpos($host, ']'));
        } else {
            $pos = strrpos($host, ':');
        }

        if (false !== $pos && $port = substr($host, $pos + 1)) {
            return (int) $port;
        }

        return 'https' === $this->getScheme() ? 443 : 80;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Checks whether or not the method is safe.
     *
     * @see https://tools.ietf.org/html/rfc7231#section-4.2.1
     */
    public function isMethodSafe(): bool
    {
        return \in_array($this->getMethod(), ['GET', 'HEAD', 'OPTIONS', 'TRACE']);
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Checks whether or not the method is idempotent.
     */
    public function isMethodIdempotent(): bool
    {
        return \in_array($this->getMethod(), ['HEAD', 'GET', 'PUT', 'DELETE', 'TRACE', 'OPTIONS', 'PURGE']);
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * The following methods are derived from code of the Zend Framework (1.10dev - 2010-01-24)
     *
     * Code subject to the new BSD license (https://framework.zend.com/license).
     *
     * Copyright (c) 2005-2010 Zend Technologies USA Inc. (https://www.zend.com/)
     */
    protected function prepareRequestUri(): string
    {
        $requestUri = '';

        if ($this->isIisRewrite() && '' != $this->server->get('UNENCODED_URL')) {
            // IIS7 with URL Rewrite: make sure we get the unencoded URL (double slash problem)
            $requestUri = $this->server->get('UNENCODED_URL');
            $this->server->remove('UNENCODED_URL');
        } elseif ($this->server->has('REQUEST_URI')) {
            $requestUri = $this->server->get('REQUEST_URI');

            if ('' !== $requestUri && '/' === $requestUri[0]) {
                // To only use path and query remove the fragment.
                if (false !== $pos = strpos($requestUri, '#')) {
                    $requestUri = substr($requestUri, 0, $pos);
                }
            } else {
                // HTTP proxy reqs setup request URI with scheme and host [and port] + the URL path,
                // only use URL path.
                $uriComponents = parse_url($requestUri);

                if (isset($uriComponents['path'])) {
                    $requestUri = $uriComponents['path'];
                }

                if (isset($uriComponents['query'])) {
                    $requestUri .= '?' . $uriComponents['query'];
                }
            }
        } elseif ($this->server->has('ORIG_PATH_INFO')) {
            // IIS 5.0, PHP as CGI
            $requestUri = $this->server->get('ORIG_PATH_INFO');
            if ('' != $this->server->get('QUERY_STRING')) {
                $requestUri .= '?' . $this->server->get('QUERY_STRING');
            }
            $this->server->remove('ORIG_PATH_INFO');
        }

        // normalize the request URI to ease creating sub-requests from this request
        $this->server->set('REQUEST_URI', $requestUri);

        return $requestUri;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Is this IIS with UrlRewriteModule?
     *
     * This method consumes, caches and removed the IIS_WasUrlRewritten env var,
     * so we don't inherit it to sub-requests.
     */
    private function isIisRewrite(): bool
    {
        if (1 === $this->server->getInt('IIS_WasUrlRewritten')) {
            $this->isIisRewrite = true;
            $this->server->remove('IIS_WasUrlRewritten');
        }

        return $this->isIisRewrite;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Returns the requested URI (path and query string).
     *
     * @return string The raw URI (i.e. not URI decoded)
     */
    public function getRequestUri(): string
    {
        return $this->requestUri ??= $this->prepareRequestUri();
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Prepares the base URL.
     */
    protected function prepareBaseUrl(): string
    {
        $filename = basename($this->server->get('SCRIPT_FILENAME', ''));

        if (basename($this->server->get('SCRIPT_NAME', '')) === $filename) {
            $baseUrl = $this->server->get('SCRIPT_NAME');
        } elseif (basename($this->server->get('PHP_SELF', '')) === $filename) {
            $baseUrl = $this->server->get('PHP_SELF');
        } elseif (basename($this->server->get('ORIG_SCRIPT_NAME', '')) === $filename) {
            $baseUrl = $this->server->get('ORIG_SCRIPT_NAME'); // 1and1 shared hosting compatibility
        } else {
            // Backtrack up the script_filename to find the portion matching
            // php_self
            $path = $this->server->get('PHP_SELF', '');
            $file = $this->server->get('SCRIPT_FILENAME', '');
            $segs = explode('/', trim($file, '/'));
            $segs = array_reverse($segs);
            $index = 0;
            $last = \count($segs);
            $baseUrl = '';
            do {
                $seg = $segs[$index];
                $baseUrl = '/' . $seg . $baseUrl;
                ++$index;
            } while ($last > $index && (false !== $pos = strpos($path, $baseUrl)) && 0 != $pos);
        }

        // Does the baseUrl have anything in common with the request_uri?
        $requestUri = $this->getRequestUri();
        if ('' !== $requestUri && '/' !== $requestUri[0]) {
            $requestUri = '/' . $requestUri;
        }

        if ($baseUrl && null !== $prefix = $this->getUrlencodedPrefix($requestUri, $baseUrl)) {
            // full $baseUrl matches
            return $prefix;
        }

        if ($baseUrl && null !== $prefix = $this->getUrlencodedPrefix($requestUri, rtrim(\dirname($baseUrl), '/' . \DIRECTORY_SEPARATOR) . '/')) {
            // directory portion of $baseUrl matches
            return rtrim($prefix, '/' . \DIRECTORY_SEPARATOR);
        }

        $truncatedRequestUri = $requestUri;
        if (false !== $pos = strpos($requestUri, '?')) {
            $truncatedRequestUri = substr($requestUri, 0, $pos);
        }

        $basename = basename($baseUrl ?? '');
        if (!$basename || !strpos(rawurldecode($truncatedRequestUri), $basename)) {
            // no match whatsoever; set it blank
            return '';
        }

        // If using mod_rewrite or ISAPI_Rewrite strip the script filename
        // out of baseUrl. $pos !== 0 makes sure it is not matching a value
        // from PATH_INFO or QUERY_STRING
        if (\strlen($requestUri) >= \strlen($baseUrl) && (false !== $pos = strpos($requestUri, $baseUrl)) && 0 !== $pos) {
            $baseUrl = substr($requestUri, 0, $pos + \strlen($baseUrl));
        }

        return rtrim($baseUrl, '/' . \DIRECTORY_SEPARATOR);
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Returns the path being requested relative to the executed script.
     *
     * The path info always starts with a /.
     *
     * Suppose this request is instantiated from /mysite on localhost:
     *
     *  * http://localhost/mysite              returns an empty string
     *  * http://localhost/mysite/about        returns '/about'
     *  * http://localhost/mysite/enco%20ded   returns '/enco%20ded'
     *  * http://localhost/mysite/about?var=1  returns '/about'
     *
     * @return string The raw path (i.e. not urldecoded)
     */
    public function getPathInfo(): string
    {
        return $this->pathInfo ??= $this->getPath();
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Prepares the path info.
     */
    protected function preparePathInfo(): string
    {
        if (null === ($requestUri = $this->getRequestUri())) {
            return '/';
        }

        // Remove the query string from REQUEST_URI
        if (false !== $pos = strpos($requestUri, '?')) {
            $requestUri = substr($requestUri, 0, $pos);
        }
        if ('' !== $requestUri && '/' !== $requestUri[0]) {
            $requestUri = '/' . $requestUri;
        }

        if (null === ($baseUrl = $this->getBaseUrlReal())) {
            return $requestUri;
        }

        $pathInfo = substr($requestUri, \strlen($baseUrl));
        if ('' === $pathInfo) {
            // If substr() returns false then PATH_INFO is set to an empty string
            return '/';
        }

        return $pathInfo;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Returns the root URL from which this request is executed.
     *
     * The base URL never ends with a /.
     *
     * This is similar to getBasePath(), except that it also includes the
     * script filename (e.g. index.php) if one exists.
     *
     * @return string The raw URL (i.e. not urldecoded)
     */
    public function getBaseUrl(): string
    {
        $trustedPrefix = '';

        // the proxy prefix must be prepended to any prefix being needed at the webserver level
        if ($this->isFromTrustedProxy() && $trustedPrefixValues = $this->getTrustedValues(self::HEADER_X_FORWARDED_PREFIX)) {
            $trustedPrefix = rtrim($trustedPrefixValues[0], '/');
        }

        return $trustedPrefix . $this->getBaseUrlReal();
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * This method is rather heavy because it splits and merges headers, and it's called by many other methods such as
     * getPort(), isSecure(), getHost(), getClientIps(), getBaseUrl() etc. Thus, we try to cache the results for
     * best performance.
     */
    private function getTrustedValues(int $type, ?string $ip = null): array
    {
        $cacheKey = $type . "\0" . ((self::$trustedHeaderSet & $type) ? $this->headers->get(self::TRUSTED_HEADERS[$type]) : '');
        $cacheKey .= "\0" . $ip . "\0" . $this->headers->get(self::TRUSTED_HEADERS[self::HEADER_FORWARDED]);

        if (isset($this->trustedValuesCache[$cacheKey])) {
            return $this->trustedValuesCache[$cacheKey];
        }

        $clientValues = [];
        $forwardedValues = [];

        if ((self::$trustedHeaderSet & $type) && $this->headers->has(self::TRUSTED_HEADERS[$type])) {
            foreach (explode(',', $this->headers->get(self::TRUSTED_HEADERS[$type])) as $v) {
                $clientValues[] = (self::HEADER_X_FORWARDED_PORT === $type ? '0.0.0.0:' : '') . trim($v);
            }
        }

        if ((self::$trustedHeaderSet & self::HEADER_FORWARDED) && (isset(self::FORWARDED_PARAMS[$type])) && $this->headers->has(self::TRUSTED_HEADERS[self::HEADER_FORWARDED])) {
            $forwarded = $this->headers->get(self::TRUSTED_HEADERS[self::HEADER_FORWARDED]);
            $parts = HeaderUtils::split($forwarded, ',;=');
            $param = self::FORWARDED_PARAMS[$type];
            foreach ($parts as $subParts) {
                if (null === $v = HeaderUtils::combine($subParts)[$param] ?? null) {
                    continue;
                }
                if (self::HEADER_X_FORWARDED_PORT === $type) {
                    if (str_ends_with($v, ']') || false === $v = strrchr($v, ':')) {
                        $v = $this->isSecure() ? ':443' : ':80';
                    }
                    $v = '0.0.0.0' . $v;
                }
                $forwardedValues[] = $v;
            }
        }

        if (null !== $ip) {
            $clientValues = $this->normalizeAndFilterClientIps($clientValues, $ip);
            $forwardedValues = $this->normalizeAndFilterClientIps($forwardedValues, $ip);
        }

        if ($forwardedValues === $clientValues || !$clientValues) {
            return $this->trustedValuesCache[$cacheKey] = $forwardedValues;
        }

        if (!$forwardedValues) {
            return $this->trustedValuesCache[$cacheKey] = $clientValues;
        }

        if (!$this->isForwardedValid) {
            return $this->trustedValuesCache[$cacheKey] = null !== $ip ? ['0.0.0.0', $ip] : [];
        }
        $this->isForwardedValid = false;

        throw new \Exception(\sprintf('The request has both a trusted "%s" header and a trusted "%s" header, conflicting with each other. You should either configure your proxy to remove one of them, or configure your project to distrust the offending one.', self::TRUSTED_HEADERS[self::HEADER_FORWARDED], self::TRUSTED_HEADERS[$type]));
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * @param array $clientIps
     * @param string $ip
     *
     * @return array
     */
    private function normalizeAndFilterClientIps(array $clientIps, string $ip): array
    {
        if (!$clientIps) {
            return [];
        }
        $clientIps[] = $ip; // Complete the IP chain with the IP the request actually came from
        $firstTrustedIp = null;

        foreach ($clientIps as $key => $clientIp) {
            if (strpos($clientIp, '.')) {
                // Strip :port from IPv4 addresses. This is allowed in Forwarded
                // and may occur in X-Forwarded-For.
                $i = strpos($clientIp, ':');
                if ($i) {
                    $clientIps[$key] = $clientIp = substr($clientIp, 0, $i);
                }
            } elseif (str_starts_with($clientIp, '[')) {
                // Strip brackets and :port from IPv6 addresses.
                $i = strpos($clientIp, ']', 1);
                $clientIps[$key] = $clientIp = substr($clientIp, 1, $i - 1);
            }

            if (!filter_var($clientIp, \FILTER_VALIDATE_IP)) {
                unset($clientIps[$key]);

                continue;
            }

            if (IpUtils::checkIp($clientIp, self::$trustedProxies)) {
                unset($clientIps[$key]);

                // Fallback to this when the client IP falls into the range of trusted proxies
                $firstTrustedIp ??= $clientIp;
            }
        }

        // Now the IP chain contains only untrusted proxies and the client IP
        return $clientIps ? array_reverse($clientIps) : [$firstTrustedIp];
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Sets a list of trusted proxies.
     *
     * You should only list the reverse proxies that you manage directly.
     *
     * @param array                          $proxies          A list of trusted proxies, the string 'REMOTE_ADDR' will be replaced with $_SERVER['REMOTE_ADDR'] and 'PRIVATE_SUBNETS' by IpUtils::PRIVATE_SUBNETS
     * @param int-mask-of<Request::HEADER_*> $trustedHeaderSet A bit field to set which headers to trust from your proxies
     */
    public static function setTrustedProxies(array $proxies, int $trustedHeaderSet): void
    {
        if (false !== $i = array_search('REMOTE_ADDR', $proxies, true)) {
            if (isset($_SERVER['REMOTE_ADDR'])) {
                $proxies[$i] = $_SERVER['REMOTE_ADDR'];
            } else {
                unset($proxies[$i]);
                $proxies = array_values($proxies);
            }
        }

        if (false !== ($i = array_search('PRIVATE_SUBNETS', $proxies, true)) || false !== ($i = array_search('private_ranges', $proxies, true))) {
            unset($proxies[$i]);
            $proxies = array_merge($proxies, IpUtils::PRIVATE_SUBNETS);
        }

        self::$trustedProxies = $proxies;
        self::$trustedHeaderSet = $trustedHeaderSet;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Gets the list of trusted proxies.
     *
     * @return string[]
     */
    public static function getTrustedProxies(): array
    {
        return self::$trustedProxies;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Returns the real base URL received by the webserver from which this request is executed.
     * The URL does not include trusted reverse proxy prefix.
     *
     * @return string The raw URL (i.e. not urldecoded)
     */
    private function getBaseUrlReal(): string
    {
        return $this->baseUrl ??= $this->prepareBaseUrl();
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Returns the prefix as encoded in the string when the string starts with
     * the given prefix, null otherwise.
     */
    private function getUrlencodedPrefix(string $string, string $prefix): ?string
    {
        if ($this->isIisRewrite()) {
            // ISS with UrlRewriteModule might report SCRIPT_NAME/PHP_SELF with wrong case
            // see https://github.com/php/php-src/issues/11981
            if (0 !== stripos(rawurldecode($string), $prefix)) {
                return null;
            }
        } elseif (!str_starts_with(rawurldecode($string), $prefix)) {
            return null;
        }

        $len = \strlen($prefix);

        if (preg_match(\sprintf('#^(%%[[:xdigit:]]{2}|.){%d}#', $len), $string, $match)) {
            return $match[0];
        }

        return null;
    }

    /**
     * Sets route parameters for the request.
     *
     * @param array<string, mixed> $params The route parameters.
     * @return self The current instance.
     */
    public function setRouteParams(array $params): self
    {
        $this->routeParams = $params;

        return $this;
    }

    /**
     * Retrieves the route parameters.
     *
     * @return array<string, mixed> The route parameters.
     */
    public function getRouteParams(): array
    {
        return $this->routeParams;
    }

    /**
     * Retrieves a specific route parameter with an optional default value.
     *
     * @param string $param The route parameter to retrieve.
     * @param mixed $default The default value if the parameter does not exist.
     * @return mixed The route parameter value or the default value.
     */
    public function getRouteParam(string $param, mixed $default = null): mixed
    {
        return $this->attributes[$param] ?? $default;
    }

    /**
     * Retrieves a specific file's information from the request.
     *
     * @param string $param The file parameter name.
     * @return File|null The File object or null if the file doesn't exist.
     */
    public function file(string $param): ?File
    {
        if (isset($this->files[$param])) {
            return new File($this->files[$param]);
        }
        return null;
    }

    /**
     * Get the session instance.
     *
     * @return Session
     */
    public function session(): Session
    {
        return $this->session;
    }

    /**
     * @return Request
     */
    public static function capture()
    {
        static::enableHttpMethodParameterOverride();

        return app(Request::class);
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Gets the request format.
     *
     * Here is the process to determine the format:
     *
     *  * format defined by the user (with setRequestFormat())
     *  * _format request attribute
     *  * $default
     *
     * @see getPreferredFormat
     */
    public function getRequestFormat(?string $default = 'html'): ?string
    {
        $this->format ??= $this->attributes->get('_format');

        return $this->format ?? $default;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Gets the mime type associated with the format.
     */
    public function getMimeType(string $format): ?string
    {
        if (null === static::$formats) {
            static::initializeFormats();
        }

        return isset(static::$formats[$format]) ? static::$formats[$format][0] : null;
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Initializes HTTP request formats.
     */
    protected static function initializeFormats(): void
    {
        static::$formats = [
            'html' => ['text/html', 'application/xhtml+xml'],
            'txt' => ['text/plain'],
            'js' => ['application/javascript', 'application/x-javascript', 'text/javascript'],
            'css' => ['text/css'],
            'json' => ['application/json', 'application/x-json'],
            'jsonld' => ['application/ld+json'],
            'xml' => ['text/xml', 'application/xml', 'application/x-xml'],
            'rdf' => ['application/rdf+xml'],
            'atom' => ['application/atom+xml'],
            'rss' => ['application/rss+xml'],
            'form' => ['application/x-www-form-urlencoded', 'multipart/form-data'],
        ];
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Checks whether the method is cacheable or not.
     *
     * @see https://tools.ietf.org/html/rfc7231#section-4.2.3
     */
    public function isMethodCacheable(): bool
    {
        return \in_array($this->getMethod(), ['GET', 'HEAD']);
    }

    /**
     * The following methods are derived from code of the PHP Symfony Framework
     * Gets the Etags.
     */
    public function getETags(): array
    {
        return preg_split('/\s*,\s*/', $this->headers->get('If-None-Match', ''), -1, \PREG_SPLIT_NO_EMPTY);
    }

    /**
     * Checks if a header exists in the request.
     *
     * @param string $header The header name to check
     * @return bool True if the header exists, false otherwise
     */
    public function hasHeader(string $header): bool
    {
        return $this->headers->has($header);
    }

    /**
     * Get the bearer token from the request headers.
     *
     * @return string|null
     */
    public function bearerToken()
    {
        $bearerToken = $this->header('Authorization', '');

        if (is_null($bearerToken)) {
            return null;
        }

        if (Str::startsWith($bearerToken, 'Bearer ')) {
            return Str::substr($bearerToken, 7);
        }

        return null;
    }

    /**
     * Gets a list of content types acceptable by the client browser in preferable order.
     *
     * @return string[]
     */
    public function getAcceptableContentTypes(): array
    {
        return $this->acceptableContentTypes ??= array_map('strval', array_keys(AcceptHeader::fromString($this->headers->get('Accept'))->all()));
    }

    /**
     * Determine if a cookie is set on the request.
     *
     * @param  string  $key
     * @return bool
     */
    public function hasCookie($key)
    {
        return ! is_null($this->cookies->get($key));
    }
}
