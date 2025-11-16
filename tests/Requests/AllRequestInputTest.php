<?php

namespace Tests\Unit\Requests;

use RuntimeException;
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
use InvalidArgumentException;

class AllRequestInputTest extends TestCase
{
    protected Request $request;
    protected array $defaultServerData;

    protected function setUp(): void
    {
        $container = new Container();
        $container->bind('str', StringService::class);

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
        $this->request = new Request();
    }

    protected function tearDown(): void
    {
        $this->resetGlobals();
        Request::setTrustedProxies([], -1);
        Request::setTrustedHosts([]);
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

        $request = new Request();
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

        $request = new Request();

        $this->assertEquals('john_doe', $request->username);
        $this->assertNull($request->nonexistent);
    }

    public function testItChecksIfRequestIsEmpty()
    {
        $emptyRequest = new Request();

        $this->assertTrue($emptyRequest->isEmpty());

        $_POST = ['key' => 'value'];
        $filledRequest = new Request();

        $this->assertFalse($filledRequest->isEmpty());
    }

    public function testItGetsQueryParameters()
    {
        $_SERVER['QUERY_STRING'] = 'page=1&limit=10&sort=name';
        $_GET = ['page' => '1', 'limit' => '10', 'sort' => 'name'];

        $request = new Request();

        $this->assertEquals('1', $request->query('page'));
        $this->assertEquals('10', $request->query('limit'));
        $this->assertNull($request->query('nonexistent'));
        $this->assertEquals('default', $request->query('nonexistent', 'default'));
    }

    public function testItGetsAllQueryParameters()
    {
        $_SERVER['QUERY_STRING'] = 'page=1&limit=10&sort=name';
        $_GET = ['page' => '1', 'limit' => '10'];

        $request = new Request();

        $query = $request->query();

        $this->assertIsArray($query);
        $this->assertArrayHasKey('page', $query);
        $this->assertArrayHasKey('limit', $query);
    }

    public function testItGetsCookies()
    {
        $_COOKIE = ['session_id' => 'abc123', 'preferences' => 'dark_mode'];

        $request = new Request();

        $cookies = $request->cookie();

        $this->assertArrayHasKey('session_id', $cookies);
        $this->assertEquals('abc123', $cookies['session_id']);
    }

    public function testItChecksIfCookieExists()
    {
        $_COOKIE = ['session_id' => 'abc123'];

        $request = new Request();

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

        $request = new Request();

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

        $request = new Request();

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

        $request = new Request();

        $this->assertFalse($request->hasFile('failed'));
    }

    public function testItChecksIfAnyFilesUploaded()
    {
        $requestWithoutFiles = new Request();
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

        $requestWithFiles = new Request();
        $this->assertTrue($requestWithFiles->hasFiles());
    }

    public function testItReturnsNullForNonexistentFile()
    {
        $request = new Request();

        $this->assertNull($request->file('nonexistent'));
    }

    public function testItGetsAllHeaders()
    {
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $_SERVER['HTTP_USER_AGENT'] = 'TestAgent/1.0';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer token123';

        $request = new Request();

        $headers = $request->headers();

        $this->assertIsArray($headers);
        $this->assertArrayHasKey('accept', $headers);
        $this->assertArrayHasKey('user-agent', $headers);
    }

    public function testItGetsSpecificHeader()
    {
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $request = new Request();

        $this->assertEquals('application/json', $request->header('Accept'));
        $this->assertNull($request->header('NonExistent'));
    }

    public function testItChecksIfHeaderExists()
    {
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $request = new Request();

        $this->assertTrue($request->hasHeader('Accept'));
        $this->assertFalse($request->hasHeader('NonExistent'));
    }

    public function testItGetsBearerToken()
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9';

        $request = new Request();

        $this->assertEquals('eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9', $request->bearerToken());
    }

    public function testItReturnsNullForMissingBearerToken()
    {
        $request = new Request();

        $this->assertNull($request->bearerToken());
    }

    public function testItReturnsNullForNonBearerAuthorization()
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic dXNlcjpwYXNz';

        $request = new Request();

        $this->assertNull($request->bearerToken());
    }

    public function testItGetsEtags()
    {
        $_SERVER['HTTP_IF_NONE_MATCH'] = '"abc123", "def456", "ghi789"';

        $request = new Request();

        $etags = $request->getETags();

        $this->assertCount(3, $etags);
        $this->assertContains('"abc123"', $etags);
    }

    public function testItGetsAcceptableContentTypes()
    {
        $_SERVER['HTTP_ACCEPT'] = 'application/json, text/html;q=0.9, */*;q=0.8';

        $request = new Request();

        $types = $request->getAcceptableContentTypes();

        $this->assertContains('application/json', $types);
        $this->assertContains('text/html', $types);
    }

    public function testItChecksIfAcceptsJson()
    {
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $request = new Request();

        $this->assertTrue($request->acceptsJson());
        $this->assertTrue($request->accepts('application/json'));
    }

    public function testItChecksIfAcceptsHtml()
    {
        $_SERVER['HTTP_ACCEPT'] = 'text/html';

        $request = new Request();

        $this->assertTrue($request->acceptsHtml());
        $this->assertTrue($request->accepts('text/html'));
    }

    public function testItChecksIfAcceptsAnyContentType()
    {
        $_SERVER['HTTP_ACCEPT'] = '*/*';

        $request = new Request();

        $this->assertTrue($request->acceptsAnyContentType());
    }

    public function testItDeterminesPreferredContentType()
    {
        $_SERVER['HTTP_ACCEPT'] = 'application/json;q=0.9, text/html;q=1.0';

        $request = new Request();

        $preferred = $request->prefers(['application/json', 'text/html']);

        $this->assertEquals('text/html', $preferred);
    }

    public function testItChecksIfRequestIsJson()
    {
        $_SERVER['CONTENT_TYPE'] = 'application/json';

        $request = new Request();

        $this->assertTrue($request->isJson());
    }

    public function testItChecksIfExpectsJsonResponse()
    {
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $request = new Request();

        $this->assertTrue($request->expectsJson());
    }

    public function testItChecksIfWantsJson()
    {
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $request = new Request();

        $this->assertTrue($request->wantsJson());
    }

    public function testItGetsRequestFormat()
    {
        $_SERVER['HTTP_ACCEPT'] = 'application/xml';

        $request = new Request();

        $this->assertEquals('xml', $request->format());
    }

    public function testItMatchesContentTypes()
    {
        $this->assertTrue(Request::matchesType('application/json', 'application/json'));
        $this->assertTrue(Request::matchesType('application/json', 'application/vnd.api+json'));
        $this->assertFalse(Request::matchesType('application/xml', 'application/json'));
    }
}