<?php

namespace Tests\Unit\Requests;

use Tests\Support\MockContainer;
use Phaseolies\Support\StringService;
use Phaseolies\Support\Session;
use Phaseolies\Support\File;
use Phaseolies\Http\ServerBag;
use Phaseolies\Http\Request;
use Phaseolies\Http\ParameterBag;
use Phaseolies\Http\InputBag;
use Phaseolies\Http\HeaderBag;
use Phaseolies\DI\Container;
use PHPUnit\Framework\TestCase;

class AllRequestInputTest extends TestCase
{
    protected Request $request;
    protected array $defaultServerData;

    protected function setUp(): void
    {
        // Bind onto the single container instance we activate — bindings
        // are per-instance now (see [[ArchNotes]] in DI/Container.php), so
        // reassigning $container to a new Container() (as this setUp used
        // to do) silently discarded every earlier bind() call.
        $container = new MockContainer();
        Container::setInstance($container);
        $container->bind('request', fn() => Request::class);
        $container->bind('str', StringService::class);
        $this->request = Request::createFromGlobals();

        $this->defaultServerData = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/users',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'HTTP_HOST' => 'example.com',
            'HTTP_ACCEPT' => 'application/json,text/html;q=0.9',
            'HTTP_USER_AGENT' => 'Mozilla/5.0',
            'REMOTE_ADDR' => '192.168.1.100',
            'SCRIPT_NAME' => '/index.php',
            'CONTENT_TYPE' => 'application/json',
            'SERVER_NAME' => 'example.com',
            'SERVER_PORT' => '80',
            'HTTPS' => 'off',
        ];

        $this->resetGlobals();
        $_SERVER = $this->defaultServerData;
        $this->request = Request::createFromGlobals();
    }

    protected function tearDown(): void
    {
        $this->resetGlobals();
        Request::setTrustedProxies([], -1);
        Request::setTrustedHosts([]);
        Container::forgetInstance();
    }

    protected function resetGlobals(): void
    {
        $_SERVER = [];
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_SESSION = [];
        $_FILES = [];
    }

    public function testItMergesInputData()
    {
        $_POST = ['existing' => 'value1'];

        $request = Request::createFromGlobals();
        $request->merge(['new' => 'value2', 'another' => 'value3']);

        $this->assertEquals('value1', $request->input('existing'));
        $this->assertEquals('value2', $request->input('new'));
        $this->assertEquals('value3', $request->input('another'));
    }

    public function testItMergesIfMissing()
    {
        $_POST = ['existing' => 'original'];

        $this->request->mergeIfMissing([
            'existing' => 'should_not_override',
            'new' => 'should_add'
        ]);

        $this->assertEquals('should_not_override', $this->request->input('existing'));
        $this->assertEquals('should_add', $this->request->input('new'));
    }

    public function testItAccessesInputViaMagicGetter()
    {
        $_POST = ['username' => 'john_doe'];

        $request = Request::createFromGlobals();

        $this->assertEquals('john_doe', $request->username);
        $this->assertNull($request->nonexistent);
    }

    public function testItChecksIfRequestIsEmpty()
    {
        $emptyRequest = Request::createFromGlobals();

        $this->assertTrue($emptyRequest->isEmpty());

        $_POST = ['key' => 'value'];
        $filledRequest = Request::createFromGlobals();

        $this->assertFalse($filledRequest->isEmpty());
    }

    public function testItGetsQueryParameters()
    {
        $_SERVER['QUERY_STRING'] = 'page=1&limit=10&sort=name';
        $_GET = ['page' => '1', 'limit' => '10', 'sort' => 'name'];

        $request = Request::createFromGlobals();

        $this->assertEquals('1', $request->query('page'));
        $this->assertEquals('10', $request->query('limit'));
        $this->assertNull($request->query('nonexistent'));
        $this->assertEquals('default', $request->query('nonexistent', 'default'));
    }

    public function testItGetsAllQueryParameters()
    {
        $_SERVER['QUERY_STRING'] = 'page=1&limit=10&sort=name';
        $_GET = ['page' => '1', 'limit' => '10'];

        $request = Request::createFromGlobals();

        $query = $request->query();

        $this->assertIsArray($query);
        $this->assertArrayHasKey('page', $query);
        $this->assertArrayHasKey('limit', $query);
    }

    public function testItGetsCookies()
    {
        $_COOKIE = ['session_id' => 'abc123', 'preferences' => 'dark_mode'];

        $request = Request::createFromGlobals();

        $cookies = $request->cookie();

        $this->assertArrayHasKey('session_id', $cookies);
        $this->assertEquals('abc123', $cookies['session_id']);
    }

    public function testItChecksIfCookieExists()
    {
        $_COOKIE = ['session_id' => 'abc123'];

        $request = Request::createFromGlobals();

        $this->assertTrue($request->hasCookie('session_id'));
        $this->assertFalse($request->hasCookie('nonexistent'));
    }

    public function testItHandlesSingleFileUpload()
    {
        $_FILES = [
            'avatar' => [
                'name' => 'profile.jpg',
                'type' => 'image/jpeg',
                'tmp_name' => '/tmp/phpXXXXXX',
                'error' => UPLOAD_ERR_OK,
                'size' => 2048
            ]
        ];

        $request = Request::createFromGlobals();

        $this->assertTrue($request->hasFile('avatar'));

        $file = $request->file('avatar');
        $this->assertInstanceOf(File::class, $file);
    }

    public function testItHandlesMultipleFileUploads()
    {
        $_FILES = [
            'documents' => [
                'name' => ['doc1.pdf', 'doc2.pdf'],
                'type' => ['application/pdf', 'application/pdf'],
                'tmp_name' => ['/tmp/php1', '/tmp/php2'],
                'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
                'size' => [1024, 2048]
            ]
        ];

        $request = Request::createFromGlobals();

        $this->assertTrue($request->hasFile('documents'));

        $files = $request->file('documents');
        $this->assertIsArray($files);
        $this->assertCount(2, $files);
        $this->assertInstanceOf(File::class, $files[0]);
    }

    public function testItDetectsUploadErrors()
    {
        $_FILES = [
            'failed' => [
                'name' => '',
                'type' => '',
                'tmp_name' => '',
                'error' => UPLOAD_ERR_NO_FILE,
                'size' => 0
            ]
        ];

        $request = Request::createFromGlobals();

        $this->assertFalse($request->hasFile('failed'));
    }

    public function testItChecksIfAnyFilesUploaded()
    {
        $requestWithoutFiles = Request::createFromGlobals();
        $this->assertFalse($requestWithoutFiles->hasFiles());

        $_FILES = [
            'avatar' => [
                'name' => 'test.jpg',
                'type' => 'image/jpeg',
                'tmp_name' => '/tmp/php',
                'error' => UPLOAD_ERR_OK,
                'size' => 1024
            ]
        ];

        $requestWithFiles = Request::createFromGlobals();
        $this->assertTrue($requestWithFiles->hasFiles());
    }

    public function testItReturnsNullForNonexistentFile()
    {
        $request = Request::createFromGlobals();

        $this->assertNull($request->file('nonexistent'));
    }

    public function testItGetsAllHeaders()
    {
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $_SERVER['HTTP_USER_AGENT'] = 'TestAgent/1.0';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer token123';

        $request = Request::createFromGlobals();

        $headers = $request->headers();

        $this->assertIsArray($headers);
        $this->assertArrayHasKey('accept', $headers);
        $this->assertArrayHasKey('user-agent', $headers);
    }

    public function testItGetsSpecificHeader()
    {
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $request = Request::createFromGlobals();

        $this->assertEquals('application/json', $request->header('Accept'));
        $this->assertNull($request->header('NonExistent'));
    }

    public function testItChecksIfHeaderExists()
    {
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $request = Request::createFromGlobals();

        $this->assertTrue($request->hasHeader('Accept'));
        $this->assertFalse($request->hasHeader('NonExistent'));
    }

    public function testItGetsBearerToken()
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9';

        $request = Request::createFromGlobals();

        $this->assertEquals('eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9', $request->bearerToken());
    }

    public function testItReturnsNullForMissingBearerToken()
    {
        $request = Request::createFromGlobals();

        $this->assertNull($request->bearerToken());
    }

    public function testItReturnsNullForNonBearerAuthorization()
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic dXNlcjpwYXNz';

        $request = Request::createFromGlobals();

        $this->assertNull($request->bearerToken());
    }

    public function testItGetsEtags()
    {
        $_SERVER['HTTP_IF_NONE_MATCH'] = '"abc123", "def456", "ghi789"';

        $request = Request::createFromGlobals();

        $etags = $request->getETags();

        $this->assertCount(3, $etags);
        $this->assertContains('"abc123"', $etags);
    }

    public function testItGetsAcceptableContentTypes()
    {
        $_SERVER['HTTP_ACCEPT'] = 'application/json, text/html;q=0.9, */*;q=0.8';

        $request = Request::createFromGlobals();

        $types = $request->getAcceptableContentTypes();

        $this->assertContains('application/json', $types);
        $this->assertContains('text/html', $types);
    }

    public function testItChecksIfAcceptsJson()
    {
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $request = Request::createFromGlobals();

        $this->assertTrue($request->acceptsJson());
        $this->assertTrue($request->accepts('application/json'));
    }

    public function testItChecksIfAcceptsHtml()
    {
        $_SERVER['HTTP_ACCEPT'] = 'text/html';

        $request = Request::createFromGlobals();

        $this->assertTrue($request->acceptsHtml());
        $this->assertTrue($request->accepts('text/html'));
    }

    public function testItChecksIfAcceptsAnyContentType()
    {
        $_SERVER['HTTP_ACCEPT'] = '*/*';

        $request = Request::createFromGlobals();

        $this->assertTrue($request->acceptsAnyContentType());
    }

    public function testItDeterminesPreferredContentType()
    {
        $_SERVER['HTTP_ACCEPT'] = 'application/json;q=0.9, text/html;q=1.0';

        $request = Request::createFromGlobals();

        $preferred = $request->prefers(['application/json', 'text/html']);

        $this->assertEquals('text/html', $preferred);
    }

    public function testItChecksIfRequestIsJson()
    {
        $_SERVER['CONTENT_TYPE'] = 'application/json';

        $request = Request::createFromGlobals();

        $this->assertTrue($request->isJson());
    }

    public function testItChecksIfExpectsJsonResponse()
    {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $request = Request::createFromGlobals();

        $this->assertTrue($request->expectsJson());
    }

    public function testItChecksIfWantsJson()
    {
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $request = Request::createFromGlobals();

        $this->assertTrue($request->wantsJson());
    }

    public function testItGetsRequestFormat()
    {
        $_SERVER['HTTP_ACCEPT'] = 'application/xml';

        $request = Request::createFromGlobals();

        $this->assertEquals('xml', $request->format());
    }

    public function testItMatchesContentTypes()
    {
        $this->assertTrue(Request::matchesType('application/json', 'application/json'));
        $this->assertTrue(Request::matchesType('application/json', 'application/vnd.api+json'));
        $this->assertFalse(Request::matchesType('application/xml', 'application/json'));
    }

    public function testItDetectsAjaxRequests()
    {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

        $request = Request::createFromGlobals();

        $this->assertTrue($request->isAjax());
    }

    public function testItDetectsNonAjaxRequests()
    {
        $request = Request::createFromGlobals();

        $this->assertFalse($request->isAjax());
    }

    public function testItDetectsPjaxRequests()
    {
        $_SERVER['HTTP_X_PJAX'] = 'true';

        $request = Request::createFromGlobals();

        $this->assertTrue($request->isPjax());
    }

    public function testItDetectsSecureConnections()
    {
        $_SERVER['HTTPS'] = 'on';

        $request = Request::createFromGlobals();

        $this->assertTrue($request->isSecure());
        $this->assertEquals('https', $request->scheme());
        $this->assertEquals('https', $request->getScheme());
    }

    public function testItDetectsInsecureConnections()
    {
        $_SERVER['HTTPS'] = 'off';

        $request = Request::createFromGlobals();

        $this->assertFalse($request->isSecure());
        $this->assertEquals('http', $request->scheme());
    }

    public function testItSetsAndGetsRouteParameters()
    {
        $request = Request::createFromGlobals();

        $params = ['id' => 123, 'slug' => 'test-post'];
        $request->setRouteParams($params);

        $this->assertEquals($params, $request->getRouteParams());
    }

    public function testItGetsServerInformation()
    {
        $request = Request::createFromGlobals();

        $server = $request->server();

        $this->assertIsArray($server);
        $this->assertArrayHasKey('REQUEST_METHOD', $server);
    }

    public function testItGetsUserAgent()
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Custom Agent)';

        $request = Request::createFromGlobals();

        $this->assertEquals('Mozilla/5.0 (Custom Agent)', $request->userAgent());
    }

    public function testItGetsReferer()
    {
        $_SERVER['HTTP_REFERER'] = 'https://google.com';

        $request = Request::createFromGlobals();

        $this->assertEquals('https://google.com', $request->referer());
    }

    public function testItGetsContentType()
    {
        $_SERVER['CONTENT_TYPE'] = 'application/json; charset=utf-8';

        $request = Request::createFromGlobals();

        $this->assertStringContainsString('application/json', $request->contentType());
    }

    public function testItGetsContentLength()
    {
        $_SERVER['HTTP_CONTENT_LENGTH'] = '1024';

        $request = Request::createFromGlobals();

        $this->assertEquals(1024, $request->contentLength());
    }

    public function testItGetsProtocolVersion()
    {
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/2.0';

        $request = Request::createFromGlobals();

        $this->assertEquals('HTTP/2.0', $request->getProtocolVersion());
    }

    public function testItGetsScriptName()
    {
        $_SERVER['SCRIPT_NAME'] = '/public/index.php';

        $request = Request::createFromGlobals();

        $this->assertEquals('/public/index.php', $request->getScriptName());
    }

    public function testItGetsSessionInstance()
    {
        $request = Request::createFromGlobals();

        $session = $request->session();

        $this->assertInstanceOf(Session::class, $session);
    }

    // public function testItConvertsToString()
    // {
    //     $_SERVER['REQUEST_METHOD'] = 'POST';
    //     $_SERVER['REQUEST_URI'] = '/api/users';
    //     $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
    //     $_SERVER['HTTP_HOST'] = 'example.com';
    //     $_COOKIE = ['session' => 'abc123'];

    //     $request = Request::createFromGlobals();

    //     $string = (string) $request;

    //     $this->assertStringContainsString('POST /api/users HTTP/1.1', $string);
    //     $this->assertStringContainsString('Host:', $string);
    // }

    public function testItCapturesRequest()
    {
        $request = Request::capture();

        $this->assertInstanceOf(Request::class, $request);
        $this->assertTrue(Request::getHttpMethodParameterOverride());
    }

    public function testItStoresAndRetrievesPassedValidationData()
    {
        $request = Request::createFromGlobals();

        $passedData = ['name' => 'John', 'email' => 'john@example.com', 'csrf_token' => 'token'];
        $request->setPassedData($passedData);

        $passed = $request->passed();

        $this->assertArrayHasKey('name', $passed);
        $this->assertArrayHasKey('email', $passed);
        $this->assertArrayNotHasKey('csrf_token', $passed);
    }

    public function testItStoresAndRetrievesValidationErrors()
    {
        $request = Request::createFromGlobals();

        $errors = ['email' => 'Invalid email', 'name' => 'Required'];
        $request->setErrors($errors);

        $failed = $request->failed();

        $this->assertEquals($errors, $failed);
    }

    public function testItHandlesMalformedQueryString()
    {
        $_SERVER['QUERY_STRING'] = 'invalid&=&key=value&&&';

        $request = Request::createFromGlobals();

        $normalized = $request->getQueryString();

        $this->assertIsString($normalized);
    }

    public function testItHandlesExtremelyLongHeaderValues()
    {
        $longValue = str_repeat('a', 8192);
        $_SERVER['HTTP_X_CUSTOM_HEADER'] = $longValue;

        $request = Request::createFromGlobals();

        $this->assertEquals($longValue, $request->header('X-Custom-Header'));
    }

    public function testItHandlesUnicodeInInput()
    {
        $_POST = [
            'name' => '日本語',
            'emoji' => '🚀',
            'chinese' => '中文',
            'arabic' => 'العربية'
        ];

        $request = Request::createFromGlobals();

        $this->assertEquals('日本語', $request->input('name'));
        $this->assertEquals('🚀', $request->input('emoji'));
        $this->assertEquals('中文', $request->input('chinese'));
        $this->assertEquals('العربية', $request->input('arabic'));
    }

    public function testItHandlesDeeplyNestedArrays()
    {
        $_POST = [
            'level1' => [
                'level2' => [
                    'level3' => [
                        'level4' => [
                            'value' => 'deep'
                        ]
                    ]
                ]
            ]
        ];

        $request = Request::createFromGlobals();

        $input = $request->input('level1');
        $this->assertEquals('deep', $input['level2']['level3']['level4']['value']);
    }

    public function testItHandlesNumericStringKeys()
    {
        $_POST = ['0' => 'zero', '1' => 'one', '2' => 'two'];

        $request = Request::createFromGlobals();

        $this->assertEquals('zero', $request->input('0'));
        $this->assertEquals('one', $request->input('1'));
    }

    public function testItHandlesEmptyArrayValues()
    {
        $_POST = ['tags' => [], 'categories' => []];

        $request = Request::createFromGlobals();

        $this->assertIsArray($request->input('tags'));
        $this->assertEmpty($request->input('tags'));
    }

    public function testItHandlesNullValues()
    {
        $_POST = ['value' => null];

        $request = Request::createFromGlobals();

        $this->assertNull($request->input('value'));
    }

    public function testItHandlesBooleanValues()
    {
        $_POST = ['active' => true, 'deleted' => false];

        $request = Request::createFromGlobals();

        $this->assertTrue($request->input('active'));
        $this->assertFalse($request->input('deleted'));
    }

    public function testItHandlesMixedTypeArrays()
    {
        $_POST = [
            'mixed' => [
                'string' => 'text',
                'number' => 42,
                'bool' => true,
                'null' => null,
                'array' => ['nested']
            ]
        ];

        $request = Request::createFromGlobals();

        $mixed = $request->input('mixed');
        $this->assertIsString($mixed['string']);
        $this->assertIsInt($mixed['number']);
        $this->assertIsBool($mixed['bool']);
        $this->assertNull($mixed['null']);
        $this->assertIsArray($mixed['array']);
    }

    public function testItHandlesSpecialPhpArraySyntax()
    {
        $_POST = [
            'items' => ['apple', 'banana', 'cherry'],
            'user[name]' => 'John',
            'user[email]' => 'john@example.com'
        ];

        $request = Request::createFromGlobals();

        $this->assertIsArray($request->input('items'));
        $this->assertEquals('John', $request->input('user[name]'));
    }

    public function it_handles_request_with_fragment()
    {
        $_SERVER['REQUEST_URI'] = '/page#section';

        $request = Request::createFromGlobals();

        // Fragments are not sent to server, so URI should not contain #
        $this->assertStringNotContainsString('#', $request->getRequestUri());
    }

    public function testItHandlesMultipleSlashesInUri()
    {
        $_SERVER['REQUEST_URI'] = '/api//users///123';

        $request = Request::createFromGlobals();

        $this->assertEquals('/api//users///123', $request->getPath());
    }

    public function testItHandlesUriWithSpecialCharacters()
    {
        $_SERVER['REQUEST_URI'] = '/search?q=' . urlencode('hello world & stuff');

        $request = Request::createFromGlobals();

        $this->assertStringContainsString('/search', $request->getPath());
    }

    public function testItHandlesIpv4ClientAddresses()
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';

        $request = Request::createFromGlobals();

        $this->assertEquals('192.168.1.1', $request->ip());
    }

    public function testItHandlesIpv6ClientAddresses()
    {
        $_SERVER['REMOTE_ADDR'] = '2001:0db8:85a3:0000:0000:8a2e:0370:7334';

        $request = Request::createFromGlobals();

        $this->assertEquals('2001:0db8:85a3:0000:0000:8a2e:0370:7334', $request->ip());
    }

    public function testItHandlesLocalhostIpv4()
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $request = Request::createFromGlobals();

        $this->assertEquals('127.0.0.1', $request->ip());
    }

    public function testItHandlesLocalhostIpv6()
    {
        $_SERVER['REMOTE_ADDR'] = '::1';

        $request = Request::createFromGlobals();

        $this->assertEquals('::1', $request->ip());
    }

    public function testItHandlesLargeNumberOfParameters()
    {
        $largeArray = [];
        for ($i = 0; $i < 1000; $i++) {
            $largeArray["key_$i"] = "value_$i";
        }

        $_POST = $largeArray;

        $request = Request::createFromGlobals();

        $this->assertCount(1000, $request->all());
        $this->assertEquals('value_500', $request->input('key_500'));
    }

    public function testItCachesExpensiveOperations()
    {
        $_SERVER['REQUEST_URI'] = '/test/path';

        $request = Request::createFromGlobals();

        // First call
        $path1 = $request->getPathInfo();
        // Second call should use cached value
        $path2 = $request->getPathInfo();

        $this->assertEquals($path1, $path2);
    }

    public function testItDoesNotExecutePhpInInput()
    {
        $_POST = ['code' => '<?php echo "malicious"; ?>'];

        $request = Request::createFromGlobals();

        $this->assertEquals('<?php echo "malicious"; ?>', $request->input('code'));
    }

    public function testItPreservesSqlInjectionAttemptsForProperHandling()
    {
        $_POST = ['query' => "'; DROP TABLE users; --"];

        $request = Request::createFromGlobals();

        $this->assertEquals("'; DROP TABLE users; --", $request->input('query'));
    }

    public function testItPreservesXssAttemptsForProperHandling()
    {
        $_POST = ['comment' => '<script>alert("XSS")</script>'];

        $request = Request::createFromGlobals();

        $this->assertEquals('<script>alert("XSS")</script>', $request->input('comment'));
    }

    public function testItHandlesNullByteInjectionAttempts()
    {
        $_POST = ['filename' => "test.txt\0.php"];

        $request = Request::createFromGlobals();

        $this->assertStringContainsString("\0", $request->input('filename'));
    }

    public function testItHandlesDirectoryTraversalAttempts()
    {
        $_POST = ['path' => '../../../etc/passwd'];

        $request = Request::createFromGlobals();

        $this->assertEquals('../../../etc/passwd', $request->input('path'));
    }

    public function testItHandlesHeaderInjectionAttempts()
    {
        $_SERVER['HTTP_X_CUSTOM'] = "value\r\nX-Injected: malicious";

        $request = Request::createFromGlobals();

        // Should preserve the raw value
        $this->assertStringContainsString("\r\n", $request->header('X-Custom'));
    }

    public function testItIsolatesRequestInstances()
    {
        $_POST = ['request1' => 'value1'];
        $request1 = Request::createFromGlobals();

        $_POST = ['request2' => 'value2'];
        $request2 = Request::createFromGlobals();

        // Each request should have its own isolated data
        $this->assertEquals('value1', $request1->input('request1'));
        $this->assertEquals('value2', $request2->input('request2'));
        $this->assertNull($request1->input('request2'));
        $this->assertNull($request2->input('request1'));
    }

    public function testItAssociatesFormatWithMimeTypes()
    {
        $request = Request::createFromGlobals();

        $request->setFormat('custom', 'application/x-custom');

        $this->assertEquals('custom', $request->getFormat('application/x-custom'));
    }

    public function testItGetsMimeTypeForFormat()
    {
        $request = Request::createFromGlobals();

        $this->assertEquals('application/json', $request->getMimeType('json'));
        $this->assertEquals('text/html', $request->getMimeType('html'));
        $this->assertEquals('text/xml', $request->getMimeType('xml'));
    }

    public function testItSetsAndGetsRequestFormat()
    {
        $request = Request::createFromGlobals();

        $request->setRequestFormat('json');
        $this->assertEquals('json', $request->getRequestFormat());

        $request->setRequestFormat('xml');
        $this->assertEquals('xml', $request->getRequestFormat());
    }

    public function testItGetsContentTypeFormat()
    {
        $_SERVER['CONTENT_TYPE'] = 'application/json; charset=utf-8';

        $request = Request::createFromGlobals();

        $this->assertEquals('json', $request->getContentTypeFormat());
    }

    public function testItReturnsDefaultFormatWhenNotSet()
    {
        $request = Request::createFromGlobals();

        $this->assertEquals('html', $request->getRequestFormat());
        $this->assertEquals('custom', $request->getRequestFormat('custom'));
    }

    public function testItGetsStandardHttpPort()
    {
        $_SERVER['SERVER_PORT'] = '80';
        $_SERVER['HTTPS'] = 'off';

        $request = Request::createFromGlobals();

        $this->assertEquals(80, $request->port());
    }

    public function testItGetsStandardHttpsPort()
    {
        $_SERVER['SERVER_PORT'] = '443';
        $_SERVER['HTTPS'] = 'on';

        $request = Request::createFromGlobals();

        $this->assertEquals(443, $request->port());
    }

    public function testItGetsCustomPort()
    {
        $_SERVER['HTTP_HOST'] = 'example.com:8080';

        $request = Request::createFromGlobals();

        $this->assertEquals(8080, $request->port());
    }

    public function testItBuildsHostWithStandardPort()
    {
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['SERVER_PORT'] = '80';
        $_SERVER['HTTPS'] = 'off';

        $request = Request::createFromGlobals();

        $this->assertEquals('example.com', $request->host());
    }

    public function testItBuildsHostWithCustomPort()
    {
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['SERVER_PORT'] = '8080';
        $_SERVER['HTTPS'] = 'off';

        $request = Request::createFromGlobals();

        $this->assertEquals('example.com', $request->host());
    }

    public function testItGetsParameterFromAttributesFirst()
    {
        $_GET['key'] = 'from_query';
        $_POST['key'] = 'from_post';

        $request = Request::createFromGlobals();
        $request->attributes->set('key', 'from_attributes');

        $this->assertEquals('from_attributes', $request->get('key'));
    }

    public function testItGetsParameterFromQueryWhenNotInAttributes()
    {
        $_GET['key'] = 'from_query';
        $_POST['key'] = 'from_post';

        $request = Request::createFromGlobals();

        $this->assertEquals('from_query', $request->get('key'));
    }

    public function testItGetsParameterFromPostWhenNotInQuery()
    {
        $_POST['key'] = 'from_post';

        $request = Request::createFromGlobals();

        $this->assertEquals('from_post', $request->get('key'));
    }

    public function testItReturnsDefaultWhenParameterNotFound()
    {
        $request = Request::createFromGlobals();

        $this->assertEquals('default_value', $request->get('nonexistent', 'default_value'));
    }

    public function testItSetsAndGetsLocale()
    {
        $request = Request::createFromGlobals();

        $this->assertEquals('en', $request->getLocale());
    }

    public function testItHandlesRemoteAddrPlaceholderInTrustedProxies()
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';

        Request::setTrustedProxies(['REMOTE_ADDR'], Request::HEADER_X_FORWARDED_FOR);

        $proxies = Request::getTrustedProxies();

        $this->assertContains('192.168.1.1', $proxies);
    }

    public function testItHandlesPrivateSubnetsInTrustedProxies()
    {
        Request::setTrustedProxies(['PRIVATE_SUBNETS'], Request::HEADER_X_FORWARDED_FOR);

        $proxies = Request::getTrustedProxies();

        // Should contain private IP ranges
        $this->assertNotEmpty($proxies);
    }

    public function testItStripsPortFromIpv4InClientIps()
    {
        Request::setTrustedProxies(['192.168.1.1'], Request::HEADER_X_FORWARDED_FOR);

        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.1:8080';

        $request = Request::createFromGlobals();

        $clientIps = $request->getClientIps();

        $this->assertEquals('203.0.113.1', $clientIps[0]);
    }

    public function testItStripsBracketsFromIpv6InClientIps()
    {
        Request::setTrustedProxies(['::1'], Request::HEADER_X_FORWARDED_FOR);

        $_SERVER['REMOTE_ADDR'] = '::1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '[2001:db8::1]:8080';

        $request = Request::createFromGlobals();

        $clientIps = $request->getClientIps();

        $this->assertEquals('2001:db8::1', $clientIps[0]);
    }

    public function testItHandlesCompleteRestApiRequest()
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/api/v1/users',
            'HTTP_HOST' => 'api.example.com',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer eyJhbGc...',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            'HTTPS' => 'on',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'REMOTE_ADDR' => '203.0.113.1'
        ];

        $request = Request::createFromGlobals();

        $this->assertTrue($request->isSecure());
        $this->assertTrue($request->isAjax());
        $this->assertTrue($request->acceptsJson());
        $this->assertTrue($request->isJson());
        $this->assertEquals('POST', $request->getMethod());
        $this->assertNotNull($request->bearerToken());
    }

    public function testItHandlesCompleteFormSubmission()
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/contact',
            'HTTP_HOST' => 'example.com',
            'HTTP_ACCEPT' => 'text/html',
            'HTTP_CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            'HTTP_REFERER' => 'https://example.com/contact-form',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'REMOTE_ADDR' => '192.168.1.100'
        ];

        $_POST = [
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'message' => 'Hello World',
            '_token' => 'csrf_token_here'
        ];

        $request = Request::createFromGlobals();

        $this->assertEquals('POST', $request->getMethod());
        $this->assertTrue($request->acceptsHtml());
        $this->assertEquals('Jane Smith', $request->input('name'));
        $this->assertEquals('https://example.com/contact-form', $request->referer());
        $this->assertArrayHasKey('_token', $request->all());
    }

    public function testItInitializesAllBagsCorrectly()
    {
        $this->assertInstanceOf(ServerBag::class, $this->request->server);
        $this->assertInstanceOf(HeaderBag::class, $this->request->headers);
        $this->assertInstanceOf(InputBag::class, $this->request->request);
        $this->assertInstanceOf(InputBag::class, $this->request->query);
        $this->assertInstanceOf(ParameterBag::class, $this->request->attributes);
        $this->assertInstanceOf(InputBag::class, $this->request->cookies);
        $this->assertInstanceOf(Session::class, $this->request->session);
        $this->assertIsArray($this->request->files);
    }

    public function testItHandlesEmptySuperglobalsGracefully()
    {
        $this->resetGlobals();
        $_SERVER = ['REQUEST_METHOD' => 'GET'];

        $request = Request::createFromGlobals();

        $this->assertEmpty($request->all());
        $this->assertEmpty($request->query->all());
        $this->assertEmpty($request->cookies->all());
    }

    public function testItGetsAllExceptSpecifiedInputs()
    {
        $_POST = ['name' => 'John', 'email' => 'john@example.com', 'password' => 'secret'];

        $request = Request::createFromGlobals();

        $except = $request->except('password');

        $this->assertArrayHasKey('name', $except);
        $this->assertArrayHasKey('email', $except);
        $this->assertArrayNotHasKey('password', $except);
    }

    public function testItGetsOnlySpecifiedInputs()
    {
        $_POST = ['name' => 'John', 'email' => 'john@example.com', 'password' => 'secret'];

        $request = Request::createFromGlobals();

        $only = $request->only('name', 'email');

        $this->assertArrayHasKey('name', $only);
        $this->assertArrayHasKey('email', $only);
        $this->assertArrayNotHasKey('password', $only);
    }

    public function testItChecksIfAnyInputExists()
    {
        $_POST['key1'] = 'value1';

        $request = Request::createFromGlobals();

        $this->assertTrue($request->hasAny('key1', 'key2'));
        $this->assertTrue($request->hasAny('nonexistent1', 'key1'));
        $this->assertFalse($request->hasAny('nonexistent1', 'nonexistent2'));
    }

    public function testItChecksIfInputIsFilled()
    {
        $_POST['filled'] = 'value';
        $_POST['empty'] = '';
        $_POST['whitespace'] = '   ';
        $_POST['zero'] = '0';

        $request = Request::createFromGlobals();

        $this->assertTrue($request->filled('filled'));
        $this->assertFalse($request->filled('empty'));
        $this->assertTrue($request->filled('whitespace'));
        $this->assertFalse($request->filled('zero'));
    }

    public function testItChecksIfInputExists()
    {
        $_POST['existing'] = 'value';
        $_POST['empty_string'] = '';

        $request = Request::createFromGlobals();

        $this->assertTrue($request->has('existing'));
        $this->assertFalse($request->has('empty_string'));
        $this->assertFalse($request->has('nonexistent'));
    }

    public function testItGetsInputWithDefaultValue()
    {
        $request = Request::createFromGlobals();

        $this->assertEquals('default', $request->input('nonexistent', 'default'));
    }

    public function testItGetsAllInput()
    {
        $_GET['key1'] = 'value1';
        $_POST['key2'] = 'value2';

        $request = Request::createFromGlobals();

        $all = $request->all();

        $this->assertArrayHasKey('key1', $all);
        $this->assertArrayHasKey('key2', $all);
    }

    public function testItGetsInputFromMultipleSources()
    {
        $_GET['from_query'] = 'query_value';
        $_POST['from_body'] = 'body_value';

        $request = Request::createFromGlobals();

        $this->assertEquals('query_value', $request->input('from_query'));
        $this->assertEquals('body_value', $request->input('from_body'));
    }

    public function testItCalculatesRelativeUri()
    {
        $_SERVER['REQUEST_URI'] = '/a/b/c/d';

        $request = Request::createFromGlobals();

        $this->assertEquals('', $request->getRelativeUriForPath('/a/b/c/d'));
        $this->assertEquals('other', $request->getRelativeUriForPath('/a/b/c/other'));
        $this->assertEquals('../../x/y', $request->getRelativeUriForPath('/a/x/y'));
    }

    public function testItGetsBaseUrl()
    {
        $_SERVER['SCRIPT_NAME'] = '/public/index.php';
        $_SERVER['REQUEST_URI'] = '/public/api/users';

        $request = Request::createFromGlobals();

        $this->assertNotEmpty($request->getBaseUrl());
    }

    public function testItHandlesIisRewrite()
    {
        $_SERVER['IIS_WasUrlRewritten'] = '1';
        $_SERVER['UNENCODED_URL'] = '/api/users/test';

        $request = Request::createFromGlobals();

        $this->assertStringContainsString('/api/users', $request->getRequestUri());
    }

    public function testItGetsRequestUri()
    {
        $_SERVER['REQUEST_URI'] = '/api/users?page=1';

        $request = Request::createFromGlobals();

        $this->assertEquals('/api/users', $request->getRequestUri());
        $this->assertEquals('/api/users', $request->uri());
    }

    public function testItGetsQueryString()
    {
        $_SERVER['QUERY_STRING'] = 'page=1&sort=name&filter=active';

        $request = Request::createFromGlobals();

        $this->assertEquals('filter=active&page=1&sort=name', $request->getQueryString());
    }

    public function testItNormalizesQueryString()
    {
        $normalized = Request::normalizeQueryString('b=2&a=1&c=3');

        $this->assertEquals('a=1&b=2&c=3', $normalized);
    }

    public function testItHandlesEncodedUri()
    {
        $_SERVER['REQUEST_URI'] = '/api/users/john%20doe';

        $request = Request::createFromGlobals();

        $this->assertEquals('/api/users/john doe', $request->getPath());
    }

    // public function testItBuildsFullUrl()
    // {
    //     $_SERVER['HTTP_HOST'] = 'example.com';
    //     $_SERVER['REQUEST_URI'] = '/api/users?page=1';
    //     $_SERVER['HTTPS'] = 'on';

    //     $request = Request::createFromGlobals();

    //     $this->assertEquals('https://example.com/api/users?page=1', $request->fullUrl());
    // }

    public function testItGetsPathInfo()
    {
        $_SERVER['REQUEST_URI'] = '/api/users/123?sort=name';

        $request = Request::createFromGlobals();

        $this->assertEquals('/api/users/123', $request->getPathInfo());
        $this->assertEquals('/api/users/123', $request->getPath());
    }

    public function testItHandlesIpv6InXForwardedFor()
    {
        Request::setTrustedProxies(['::1'], Request::HEADER_X_FORWARDED_FOR);

        $_SERVER['REMOTE_ADDR'] = '::1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '2001:db8::1';

        $request = Request::createFromGlobals();

        $this->assertEquals('2001:db8::1', $request->getClientIp());
    }

    public function testItFiltersInvalidIpsFromForwardedHeader()
    {
        Request::setTrustedProxies(['192.168.1.1'], Request::HEADER_X_FORWARDED_FOR);

        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.1, invalid-ip, 198.51.100.1';

        $request = Request::createFromGlobals();

        $ips = $request->getClientIps();

        $this->assertNotContains('invalid-ip', $ips);
    }

    public function testItGetsClientIpWithoutProxy()
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.1';

        $request = Request::createFromGlobals();

        $this->assertEquals('203.0.113.1', $request->getClientIp());
        $this->assertEquals('203.0.113.1', $request->ip());
    }

    public function testItThrowsExceptionForUntrustedHost()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Untrusted Host');

        Request::setTrustedHosts(['^example\.com$']);

        $_SERVER['HTTP_HOST'] = 'malicious.com';

        $request = Request::createFromGlobals();
        $request->getHost();
    }

    public function testItValidatesTrustedHosts()
    {
        Request::setTrustedHosts(['^example\.com$', '^.*\.example\.com$']);

        $_SERVER['HTTP_HOST'] = 'example.com';
        $request = Request::createFromGlobals();

        $this->assertEquals('example.com', $request->getHost());
    }

    public function testItFiltersTrustedProxyIpsFromClientIps()
    {
        Request::setTrustedProxies(['192.168.1.1', '10.0.0.1'], Request::HEADER_X_FORWARDED_FOR);

        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.1, 10.0.0.1';

        $request = Request::createFromGlobals();

        $clientIps = $request->getClientIps();

        $this->assertNotContains('192.168.1.1', $clientIps);
        $this->assertNotContains('10.0.0.1', $clientIps);
        $this->assertContains('203.0.113.1', $clientIps);
    }

    public function testItValidatesRequestsFromTrustedProxies()
    {
        Request::setTrustedProxies(['192.168.1.1'], Request::HEADER_X_FORWARDED_FOR);

        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.1';

        $request = Request::createFromGlobals();

        $this->assertTrue($request->isValidRequest());
    }

    public function testItHandlesForwardedHeaderRfc7239()
    {
        Request::setTrustedProxies(['192.168.1.1'], Request::HEADER_FORWARDED);

        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $_SERVER['HTTP_FORWARDED'] = 'for=203.0.113.1;proto=https;host=example.com';

        $request = Request::createFromGlobals();

        $this->assertTrue($request->isSecure());
    }

    public function testItHandlesXForwardedPortHeader()
    {
        Request::setTrustedProxies(['192.168.1.1'], Request::HEADER_X_FORWARDED_PORT);

        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $_SERVER['HTTP_X_FORWARDED_PORT'] = '8443';
        $_SERVER['SERVER_PORT'] = '80';

        $request = Request::createFromGlobals();

        $this->assertEquals(8443, $request->port());
    }

    public function testItHandlesXForwardedProtoHeader()
    {
        Request::setTrustedProxies(['192.168.1.1'], Request::HEADER_X_FORWARDED_PROTO);

        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $_SERVER['HTTPS'] = 'off';

        $request = Request::createFromGlobals();

        $this->assertTrue($request->isSecure());
        $this->assertEquals('https', $request->getScheme());
    }

    public function testItHandlesXForwardedHostHeader()
    {
        Request::setTrustedProxies(['192.168.1.1'], Request::HEADER_X_FORWARDED_HOST);

        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $_SERVER['HTTP_X_FORWARDED_HOST'] = 'original-host.com';

        $request = Request::createFromGlobals();

        $this->assertEquals('original-host.com', $request->getHost());
    }

    public function testItDetectsRequestsNotFromTrustedProxies()
    {
        Request::setTrustedProxies(['192.168.1.1'], Request::HEADER_X_FORWARDED_FOR);

        $_SERVER['REMOTE_ADDR'] = '192.168.1.2';
        $request = Request::createFromGlobals();

        $this->assertFalse($request->isFromTrustedProxy());
    }

    public function testItDetectsRequestsFromTrustedProxies()
    {
        Request::setTrustedProxies(['192.168.1.1'], Request::HEADER_X_FORWARDED_FOR);

        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $request = Request::createFromGlobals();

        $this->assertTrue($request->isFromTrustedProxy());
    }
}