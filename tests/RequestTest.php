<?php

namespace Tests\Unit;

use Phaseolies\Support\Session;
use Phaseolies\Support\File;
use Phaseolies\Http\ServerBag;
use Phaseolies\Http\Response;
use Phaseolies\Http\Request;
use Phaseolies\Http\ParameterBag;
use Phaseolies\Http\InputBag;
use Phaseolies\Http\HeaderBag;
use Phaseolies\Http\Exceptions\HttpResponseException;
use PHPUnit\Framework\TestCase;

class RequestTest extends TestCase
{
    protected Request $request;
    protected array $serverData;
    protected array $getData;
    protected array $postData;
    protected array $cookieData;
    protected array $sessionData;
    protected array $filesData;

    // The following protected properties ($content, $pathInfo, $method) are accessed in tests.
    // Made public to facilitate direct testing of request internals and simplify test assertions.
    protected function setUp(): void
    {
        $this->serverData = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/test',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'HTTP_HOST' => 'example.com',
            'HTTP_ACCEPT' => 'text/html,application/xhtml+xml',
            'HTTP_USER_AGENT' => 'PHPUnit',
            'REMOTE_ADDR' => '127.0.0.1',
            'SCRIPT_NAME' => '/index.php',
            'CONTENT_TYPE' => 'text/html',
        ];

        $this->getData = [
            'id' => 123,
            'name' => 'test',
            'email' => 'test@example.com',
            'bio' => '<p>Test bio</p>',
            'age' => '25',
            'nested' => [
                'value' => ' nested ',
                'deep' => [
                    'item' => ' DEEP '
                ]
            ],
            'tags' => '1,2,3'
        ];
        $this->postData = ['email' => 'test@example.com', 'password' => 'secret'];
        $this->cookieData = ['session_id' => 'abc123'];
        $this->sessionData = ['user_id' => 1];
        $this->filesData = [
            'avatar' => [
                'name' => 'avatar.jpg',
                'type' => 'image/jpeg',
                'tmp_name' => '/tmp/php123.tmp',
                'error' => 0,
                'size' => 1024
            ]
        ];

        $_SERVER = $this->serverData;
        $_GET = $this->getData;
        $_POST = $this->postData;
        $_COOKIE = $this->cookieData;
        $_SESSION = $this->sessionData;
        $_FILES = $this->filesData;

        $this->request = new Request();
    }

    protected function tearDown(): void
    {
        $_SERVER = [];
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_SESSION = [];
        $_FILES = [];
    }

    public function testConstructorInitializesBags()
    {
        $this->assertInstanceOf(ServerBag::class, $this->request->server);
        $this->assertInstanceOf(HeaderBag::class, $this->request->headers);
        $this->assertInstanceOf(InputBag::class, $this->request->request);
        $this->assertInstanceOf(InputBag::class, $this->request->query);
        $this->assertInstanceOf(ParameterBag::class, $this->request->attributes);
        $this->assertInstanceOf(InputBag::class, $this->request->cookies);
        $this->assertInstanceOf(Session::class, $this->request->session);
    }

    public function testCreateFromGlobals()
    {
        $data = $this->request->createFromGlobals();
        $this->assertEquals(array_merge($this->postData, $this->getData), $data);
    }

    // public function testGetContent()
    // {
    //     $content = 'test content';

    //     // $this->content is protected property
    //     // Make it public before UNIT Test to avoid error
    //     $this->request->content = $content;
    //     $this->assertEquals($content, $this->request->getContent());
    // }

    // public function testGetContentAsResource()
    // {
    //     $resource = fopen('php://temp', 'r+');
    //     fwrite($resource, 'test');
    //     rewind($resource);

    //     // $this->content is protected property
    //     // Make it public before UNIT Test to avoid error
    //     $this->request->content = $resource;
    //     $result = $this->request->getContent(true);

    //     $this->assertIsResource($result);
    //     $this->assertEquals('test', stream_get_contents($result));
    // }

    // public function testIsValidMethod()
    // {
    //     $this->assertTrue($this->request->isValidMethod());

    //     $this->request->method = 'INVALID';
    //     $this->assertFalse($this->request->isValidMethod());
    // }

    public function testIsValidRequest()
    {
        if (empty($this->trustedProxies)) {
            return true;
        }

        $remoteAddress = $this->request->servers->get('REMOTE_ADDR');

        if (!in_array($remoteAddress, $this->request->trustedProxies, true)) {
            return true;
        }

        if ($this->request->trustedHeaderSet === 0) {
            return true;
        }

        // Check each trusted header bit
        foreach (Request::TRUSTED_HEADERS as $headerBit => $headerName) {
            if (($this->request->trustedHeaderSet & $headerBit) === $headerBit) {
                if (!$this->request->headers->has($headerName)) {
                    return false;
                }
            }
        }

        return true;
    }

    public function testToString()
    {
        // $this->request->content = 'test body';

        $expected = "GET /test HTTP/1.1\r\n";
        $expected .= "Accept:       text/html,application/xhtml+xml\r\n";
        $expected .= "Content-Type: text/html\r\n";
        $expected .= "Host:         example.com\r\n";
        $expected .= "User-Agent:   PHPUnit\r\n";
        $expected .= "\r\n";
        // $expected .= "test body";

        $this->assertEquals($expected, (string) $this->request);
    }

    public function testTrustedHosts()
    {
        Request::setTrustedHosts(['^example\.com$']);
        $this->assertEquals(['{^example\.com$}i'], Request::getTrustedHosts());
    }

    public function testIsFromTrustedProxy()
    {
        $this->assertFalse($this->request->isFromTrustedProxy());

        Request::setTrustedProxies(['127.0.0.1'], Request::HEADER_X_FORWARDED_FOR);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $this->assertTrue($this->request->isFromTrustedProxy());
    }

    public function testHttpMethodParameterOverride()
    {
        $this->assertFalse(Request::getHttpMethodParameterOverride());

        Request::enableHttpMethodParameterOverride();
        $this->assertTrue(Request::getHttpMethodParameterOverride());
    }

    public function testGetTrustedHeaderValue()
    {
        $this->assertNull($this->request->getTrustedHeaderValue(Request::HEADER_X_FORWARDED_FOR));

        $this->request->headers->set('X_FORWARDED_FOR', '192.168.1.1');
        Request::setTrustedProxies(['127.0.0.1'], Request::HEADER_X_FORWARDED_FOR);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $this->assertEquals('192.168.1.1', $this->request->getTrustedHeaderValue(Request::HEADER_X_FORWARDED_FOR));
    }

    public function testNormalizeQueryString()
    {
        $qs = 'b=2&a=1';
        $normalized = Request::normalizeQueryString($qs);
        $this->assertEquals('a=1&b=2', $normalized);
    }

    public function testAll()
    {
        $this->assertEquals(array_merge($this->postData, $this->getData), $this->request->all());
    }

    public function testMerge()
    {
        $newData = ['new' => 'value'];
        $merged = $this->request->merge($newData);

        $this->assertSame($this->request, $merged);
        $this->assertArrayHasKey('new', $this->request->all());
    }

    public function testGetPath()
    {
        $this->assertEquals('/test', $this->request->getPath());
    }

    public function testGetMethod()
    {
        $this->assertEquals('GET', $this->request->getMethod());

        $this->request->headers->set('X-HTTP-METHOD-OVERRIDE', 'PUT');
        $this->assertEquals('PUT', $this->request->getMethod());
    }

    public function testGetRealMethod()
    {
        $this->assertEquals('GET', $this->request->getRealMethod());
    }

    public function testSetAndGetFormat()
    {
        $this->request->setFormat('custom', 'application/custom');
        $this->assertEquals('custom', $this->request->getFormat('application/custom'));

        $this->request->setRequestFormat('json');
        $this->assertEquals('json', $this->request->getRequestFormat());
    }

    public function testGetContentTypeFormat()
    {
        $this->assertEquals('html', $this->request->getContentTypeFormat());
    }

    public function testLocaleMethods()
    {
        $this->assertEquals('en', $this->request->getLocale());

        // $this->request->setLocale('fr');
        // $this->assertEquals('en', $this->request->getLocale());
    }

    // public function testGetRelativeUriForPath()
    // {
    //     $this->request->pathInfo = '/a/b/c/d';
    //     $this->assertEquals('', $this->request->getRelativeUriForPath('/a/b/c/d'));
    //     $this->assertEquals('other', $this->request->getRelativeUriForPath('/a/b/c/other'));
    //     $this->assertEquals('../../x/y', $this->request->getRelativeUriForPath('/a/x/y'));
    // }

    // public function testMethodChecks()
    // {
    //     $this->assertTrue($this->request->isGet());

    //     $this->request->method = 'GET';

    //     $this->assertTrue($this->request->isGet());
    // }

    public function testGet()
    {
        $this->assertEquals(123, $this->request->get('id'));
        $this->assertEquals('test@example.com', $this->request->get('email'));
        $this->assertNull($this->request->get('nonexistent'));
    }

    public function testGetClientIps()
    {
        $this->assertEquals(['127.0.0.1'], $this->request->getClientIps());

        Request::setTrustedProxies(['127.0.0.1'], Request::HEADER_X_FORWARDED_FOR);
        $this->request->headers->set('X_FORWARDED_FOR', '192.168.1.1, 10.0.0.1');

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $this->assertEquals(['10.0.0.1', '192.168.1.1'], $this->request->getClientIps());
    }

    public function testGetClientIp()
    {
        $this->assertEquals('127.0.0.1', $this->request->getClientIp());
    }

    public function testGetScriptName()
    {
        $this->assertEquals('/index.php', $this->request->getScriptName());
    }

    public function testGetScheme()
    {
        $this->assertEquals('http', $this->request->getScheme());
    }

    public function testGetProtocolVersion()
    {
        $this->assertEquals('HTTP/1.1', $this->request->getProtocolVersion());
    }

    public function testHost()
    {
        $this->assertEquals('example.com', $this->request->host());
    }

    public function testGetHost()
    {
        $this->assertEquals('example.com', $this->request->getHost());
    }

    public function testPort()
    {
        $this->assertEquals(80, $this->request->port());
    }

    public function testIsMethodSafe()
    {
        $this->assertTrue($this->request->isMethodSafe());
    }

    public function testIsMethodIdempotent()
    {
        $this->assertTrue($this->request->isMethodIdempotent());
    }

    public function testGetRequestUri()
    {
        $this->assertEquals('/test', $this->request->getRequestUri());
    }

    public function testGetPathInfo()
    {
        $this->assertEquals('/test', $this->request->getPathInfo());
    }

    public function testSetAndGetRouteParams()
    {
        $params = ['id' => 123, 'slug' => 'test'];
        $this->assertEquals(123, $this->request->get('id'));
    }

    // public function testFile()
    // {
    //     $file = $this->request->file('avatar');
    //     $this->assertInstanceOf(File::class, $file);

    //     $this->assertEquals('avatar.jpg', $file->getClientOriginalName());
    //     $this->assertEquals('image/jpeg', $file->getClientOriginalType());
    //     $this->assertEquals(1024, $file->getClientOriginalSize());
    //     $this->assertEquals(0, $file->getError());

    //     $this->assertNull($this->request->file('nonexistent'));
    // }

    public function testSession()
    {
        $session = $this->request->session();

        $this->assertEquals(1, $session->get('user_id'));

        $session->put('test_key', 'test_value');
        $this->assertEquals('test_value', $session->get('test_key'));

        $emptyRequest = new Request();
        $_SESSION = [];
        $emptySession = $emptyRequest->session();
        $this->assertEmpty($emptySession->all());
    }

    public function testCapture()
    {
        $request = Request::capture();
        $this->assertInstanceOf(Request::class, $request);
    }

    public function testIsMethodCacheable()
    {
        $this->assertTrue($this->request->isMethodCacheable());
    }

    public function testGetETags()
    {
        $this->request->headers->set('If-None-Match', '"abc123", "def456"');
        $this->assertEquals(['"abc123"', '"def456"'], $this->request->getETags());
    }

    public function testHasHeader()
    {
        $this->assertTrue($this->request->hasHeader('Host'));
        $this->assertFalse($this->request->hasHeader('X-Nonexistent'));
    }

    public function testGetAcceptableContentTypes()
    {
        $types = $this->request->getAcceptableContentTypes();
        $this->assertContains('text/html', $types);
        $this->assertContains('application/xhtml+xml', $types);
    }

    public function testHasCookie()
    {
        $this->assertTrue($this->request->hasCookie('session_id'));
        $this->assertFalse($this->request->hasCookie('nonexistent'));
    }

    public function testIp()
    {
        $this->assertEquals('127.0.0.1', $this->request->ip());
    }

    public function testUri()
    {
        $this->assertEquals('/test', $this->request->uri());
    }

    public function testServer()
    {
        $server = $this->request->server();
        $this->assertArrayHasKey('REQUEST_METHOD', $server);
    }

    public function testHeaders()
    {
        $headers = $this->request->headers();

        $this->assertArrayHasKey('host', $headers);
    }

    public function testHeader()
    {
        $this->assertEquals('example.com', $this->request->header('Host'));
        $this->assertNull($this->request->header('X-Nonexistent'));
    }

    public function testScheme()
    {
        $this->assertEquals('http', $this->request->scheme());
    }

    public function testUrl()
    {
        $this->assertEquals('http://example.com/test', $this->request->url());
    }

    // public function testContent()
    // {
    //     $this->request->content = 'test';
    //     $this->assertEquals('test', $this->request->content());
    // }

    public function testMethod()
    {
        $this->assertEquals('get', $this->request->method());
    }

    public function testCookie()
    {
        $this->assertEquals(['session_id' => 'abc123'], $this->request->cookie());
    }

    public function testUserAgent()
    {
        $this->assertEquals('PHPUnit', $this->request->userAgent());
    }

    public function testReferer()
    {
        $this->assertNull($this->request->referer());

        $this->request->headers->set('Referer', 'http://example.com');
        $this->assertEquals('http://example.com', $this->request->referer());
    }

    public function testIsSecure()
    {
        $this->assertFalse($this->request->isSecure());
    }

    public function testIsAjax()
    {
        $this->assertFalse($this->request->isAjax());

        $this->request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $this->assertTrue($this->request->isAjax());
    }

    public function testIsPjax()
    {
        $this->assertFalse($this->request->isPjax());

        $this->request->headers->set('X-PJAX', 'true');
        $this->assertTrue($this->request->isPjax());
    }

    public function testContentType()
    {
        $this->assertEquals('text/html', $this->request->contentType());
    }

    public function testContentLength()
    {
        $this->request->headers->set('Content-Length', '1024');
        $this->assertEquals(1024, $this->request->contentLength());
    }

    public function testExcept()
    {
        $result = $this->request->except('id');
        $this->assertArrayNotHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
    }

    public function testOnly()
    {
        $result = $this->request->only('id');
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayNotHasKey('name', $result);
    }

    public function testPassed()
    {
        $this->request->setPassedData(['name' => 'test', 'csrf_token' => 'abc123']);
        $result = $this->request->passed();
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayNotHasKey('csrf_token', $result);
    }

    public function testFailed()
    {
        $this->request->setErrors(['name' => 'Required', 'csrf_token' => 'Invalid']);
        $result = $this->request->failed();
        $this->assertEquals(['name' => 'Required', 'csrf_token' => 'Invalid'], $result);
    }

    public function testIsEmpty()
    {
        $this->assertFalse($this->request->isEmpty());

        $emptyRequest = new Request();
        $_GET = [];
        $_POST = [];
        $this->assertFalse($emptyRequest->isEmpty());
    }

    public function testInput()
    {
        $this->assertEquals('test@example.com', $this->request->input('email'));
        $this->assertEquals('test@example.com', $this->request->email);
        $this->assertEquals('test@example.com', $this->request->get('email'));
        $this->assertEquals('default', $this->request->input('nonexistent', 'default'));
    }

    public function testHas()
    {
        $this->assertTrue($this->request->has('email'));
        $this->assertFalse($this->request->has('nonexistent'));
    }

    public function testMagicGet()
    {
        $this->assertEquals('test@example.com', $this->request->email);
        $this->assertNull($this->request->nonexistent);
    }

    public function testExpectsJson()
    {
        $this->request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $this->request->headers->remove('Accept');
        $this->assertTrue($this->request->expectsJson());

        $this->request->headers->set('Accept', 'application/json');
        $this->assertTrue($this->request->expectsJson());

        $this->request->headers->set('X-PJAX', 'true');
        $this->assertFalse($this->request->expectsJson());
    }

    public function testAccepts()
    {
        $this->request->headers->set('Accept', 'application/json');
        $this->assertTrue($this->request->accepts('application/json'));
        $this->assertFalse($this->request->accepts('text/html'));

        $this->request->headers->set('Accept', '*/*');
        $this->assertTrue($this->request->accepts(['application/json', 'text/html']));

        $this->request->headers->remove('Accept');
        $this->assertTrue($this->request->accepts('application/json'));
    }

    public function testPrefers()
    {
        $this->request->headers->set('Accept', 'application/json, text/html;q=0.9');

        $this->assertEquals('application/json', $this->request->prefers(['application/json', 'text/html']));

        $this->request->headers->set('Accept', '*/*');
        $this->assertEquals('application/json', $this->request->prefers(['text/html', 'application/json']));

        $this->assertNull($this->request->prefers(['application/xml']));
    }

    public function testAcceptsAnyContentType()
    {
        $this->request->headers->remove('Accept');
        $this->assertTrue($this->request->acceptsAnyContentType());

        $this->request->headers->set('Accept', '*/*');
        $this->assertTrue($this->request->acceptsAnyContentType());
    }

    public function testAcceptsJson()
    {
        $this->request->headers->set('Accept', 'application/json');
        $this->assertTrue($this->request->acceptsJson());
    }

    public function testAcceptsHtml()
    {
        $this->request->headers->set('Accept', 'text/html');
        $this->assertTrue($this->request->acceptsHtml());
    }

    public function testMatchesType()
    {
        $this->assertTrue($this->request::matchesType('application/json', 'application/json'));
        $this->assertFalse($this->request::matchesType('text/html', 'application/json'));
    }

    public function testFormat()
    {
        $this->request->headers->set('Accept', 'application/xml');
        $this->assertEquals('xml', $this->request->format());
    }

    public function testHasAny()
    {
        $this->assertTrue($this->request->hasAny('name', 'nonexistent'));
        $this->assertTrue($this->request->hasAny('nonexistent', 'email'));
        $this->assertFalse($this->request->hasAny('nonexistent1', 'nonexistent2'));
    }

    public function testMergeIfMissing()
    {
        $this->request->mergeIfMissing([
            'name' => 'John Doe',
            'new_field' => 'New Value'
        ]);

        $this->assertEquals('test', $this->request->input('name'));
        // $this->assertEquals('New Value', $this->request->new_field);
    }

    public function testPipe()
    {
        $this->request->merge(['test_field' => '  value  ']);
        $result = $this->request->pipe('test_field', 'trim');
        $this->assertEquals('value', $result);
    }

    public function testNullifyBlanks()
    {
        $this->request->nullifyBlanks();

        $this->assertNull($this->request->input('empty_string'));
        $this->assertNull($this->request->input('whitespace'));

        $request = new Request();
        $request->merge(['whitespace' => '   ']);
        $request->nullifyBlanks(false, false, false);
        $this->assertEquals('   ', $request->input('whitespace'));
    }

    public function testTapInput()
    {
        $testValue = '';
        $this->request->tapInput('name', function ($value) use (&$testValue) {
            $testValue = $value;
        });

        $this->assertEquals('test', $testValue);
    }

    public function testIfFilled()
    {
        $testValue = '';
        $this->request->ifFilled('name', function ($value) use (&$testValue) {
            $testValue = $value;
        });
        $this->assertEquals('test', $testValue);

        $testValue = 'unchanged';
        $this->request->ifFilled('nonexistent', function ($value) use (&$testValue) {
            $testValue = 'changed';
        });
        $this->assertEquals('unchanged', $testValue);
    }

    public function testFilled()
    {
        $this->assertTrue($this->request->filled('name'));
        $this->assertFalse($this->request->filled('empty_string'));
        $this->assertFalse($this->request->filled('whitespace'));
        $this->assertFalse($this->request->filled('empty_array'));
        $this->assertFalse($this->request->filled('nonexistent'));
    }

    public function testTransform()
    {
        $result = $this->request->transform([
            'name' => 'trim',
            'email' => 'strtolower'
        ]);

        $this->assertEquals([
            'name' => 'test',
            'email' => 'test@example.com',
        ], $result);
    }

    public function testPipeInputs()
    {
        $this->request->pipeInputs([
            'name' => 'trim',
            'email' => 'strtolower',
        ]);

        $this->assertEquals('test', $this->request->input('name'));
        $this->assertEquals('test@example.com', $this->request->input('email'));
    }

    public function testEnsure()
    {
        // $this->request->ensure('age', fn($value) => is_numeric($value));

        // $this->expectException(\InvalidArgumentException::class);
        $this->request->ensure('name', fn($value) => is_string($value));
    }

    public function testContextual()
    {
        $this->request->contextual(function ($data) {
            return ['processed' => true];
        });

        $this->assertTrue($this->request->input('processed'));
    }

    // public function testSanitizeIf()
    // {
    //     $this->request->sanitizeIf(true, ['name' => 'trim']);
    //     $this->assertEquals('test', $this->request->input('name'));

    //     $this->request->sanitizeIf(false, ['email' => 'trim']);
    //     $this->assertEquals('TEST@EXAMPLE.COM', $this->request->input('email'));
    // }

    public function testExtract()
    {
        $result = $this->request->extract(function ($request) {
            return $request->input('name');
        });

        $this->assertEquals('test', $result);
    }

    public function testCleanse()
    {
        $result = $this->request->cleanse([
            'name' => 'trim',
            'email' => 'lowercase|trim',
            'bio' => 'strip_tags',
            'age' => 'int',
            'nested.value' => 'trim|uppercase',
            'nested.deep.item' => 'trim|lowercase'
        ]);

        $this->assertEquals('test', $result['name']);
        $this->assertEquals('test@example.com', $result['email']);
        $this->assertEquals('Test bio', $result['bio']);
        $this->assertEquals(25, $result['age']);
        $this->assertEquals('NESTED', $result['nested']['value']);
        $this->assertEquals('deep', $result['nested']['deep']['item']);
    }

    public function testMapIf()
    {
        $result = $this->request->mapIf(true, function ($data) {
            return $this->recursiveTrim($data);
        });
        $this->assertEquals('test', $result['name']);
        $this->assertEquals('nested', $result['nested']['value']);

        $result = $this->request->mapIf(false, function ($data) {
            return $this->recursiveTrim($data);
        });
        $this->assertEquals('test', $result['name']);
        $this->assertEquals(' nested ', $result['nested']['value']);
    }

    private function recursiveTrim(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->recursiveTrim($value);
            } elseif (is_string($value)) {
                $data[$key] = trim($value);
            }
        }
        return $data;
    }

    public function testAsArray()
    {
        $this->assertEquals(['1', '2', '3'], $this->request->asArray('tags'));
        $this->assertEquals([], $this->request->asArray('nonexistent'));
    }


    public function testBindTo()
    {
        $target = new class {
            public $name;
            public $email;
            public $age;
            private $nonexistent;

            public function getAge()
            {
                return $this->age;
            }

            public function getNonexistent()
            {
                return $this->nonexistent;
            }
        };

        $result = $this->request->bindTo($target);

        $this->assertEquals('test', $result->name);
        $this->assertEquals('test@example.com', $result->email);
    }

    // OK, but there were issues!
    // Tests: 308, Assertions: 597, Deprecations: 9
    // public function testBindToWithNestedObjects()
    // {
    //     $this->request->merge([
    //         'user' => [
    //             'name' => 'test',
    //             'email' => 'test@example.com',
    //             'details' => [
    //                 'age' => 30,
    //                 'active' => true
    //             ]
    //         ]
    //     ]);

    //     $result = $this->request->bindTo(new UserDTO(), false);

    //     $this->assertIsObject($result->user);
    //     $this->assertEquals('test', $result->user->name);
    //     $this->assertEquals('test@example.com', $result->user->email);

    //     $this->assertIsArray($result->user->details);
    //     $this->assertEquals(30, $result->user->details['age']);
    //     $this->assertTrue($result->user->details['active']);

    //     $this->assertTrue(property_exists($result->user, 'details'));
    // }

    private function createTestClass(string $property, ?string $type = null): object
    {
        $code = "return new class() {";
        if ($type) {
            $code .= "public $type \$$property;";
        } else {
            $code .= "public \$$property;";
        }
        $code .= "};";

        return eval($code);
    }
}

// class UserDTO
// {
//     public $user;
// }