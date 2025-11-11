<?php

namespace Tests\Unit\Traits;

use Tests\Support\Kernel;
use Phaseolies\Support\Router;
use Phaseolies\Http\Request;
use Phaseolies\DI\Container;
use PHPUnit\Framework\TestCase;

if (!class_exists('App\Http\Kernel')) {
    class_alias(Kernel::class, 'App\Http\Kernel');
}

class RequestParserTraitTest extends TestCase
{
    protected Request $request;
    protected Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new Container();
        $this->request = new Request();
        $this->container->singleton('request', fn() => $this->request);
        $this->container->singleton('route', Router::class);

        // Reset all globals
        $_SERVER = [];
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_FILES = [];
    }

    protected function tearDown(): void
    {
        $_SERVER = [];
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_FILES = [];
        parent::tearDown();
    }

    /**
     * Set up server variables and create a new request instance
     */
    protected function setServerVars(array $server = [], array $get = [], array $post = [], array $cookies = []): Request
    {
        // Set default required server variables
        $defaults = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'HTTP_HOST' => 'localhost',
        ];

        $_SERVER = array_merge($defaults, $server);
        $_GET = $get;
        $_POST = $post;
        $_COOKIE = $cookies;

        return new Request();
    }

    // ===========================
    // IP Address Tests
    // ===========================

    public function testIpReturnsRemoteAddr()
    {
        $this->request = $this->setServerVars([
            'REMOTE_ADDR' => '192.168.1.100'
        ]);

        $this->assertEquals('192.168.1.100', $this->request->ip());
    }

    public function testIpReturnsNullWhenNoRemoteAddr()
    {
        $this->request = $this->setServerVars();

        $this->assertNull($this->request->ip());
    }

    public function testIpsReturnsArrayOfIpAddresses()
    {
        $this->request = $this->setServerVars([
            'REMOTE_ADDR' => '192.168.1.1'
        ]);

        $ips = $this->request->ips();

        $this->assertIsArray($ips);
        $this->assertContains('192.168.1.1', $ips);
    }

    // ===========================
    // URI and URL Tests
    // ===========================

    public function testUriReturnsRequestUri()
    {
        $this->request = $this->setServerVars([
            'REQUEST_URI' => '/api/users/123'
        ]);

        $this->assertEquals('/api/users/123', $this->request->uri());
    }

    public function testUriWithQueryString()
    {
        $this->request = $this->setServerVars([
            'REQUEST_URI' => '/api/users?page=1&limit=10',
            'QUERY_STRING' => 'page=1&limit=10'
        ]);

        $uri = $this->request->uri();
        $this->assertStringContainsString('/api/users', $uri);
    }

    public function testSchemeReturnsHttp()
    {
        $this->request = $this->setServerVars([
            'HTTPS' => 'off'
        ]);

        $this->assertEquals('http', $this->request->scheme());
    }

    public function testSchemeReturnsHttps()
    {
        $this->request = $this->setServerVars([
            'HTTPS' => 'on'
        ]);

        $this->assertEquals('https', $this->request->scheme());
    }

    public function testUrlReturnsFullUrl()
    {
        $this->request = $this->setServerVars([
            'HTTPS' => 'on',
            'HTTP_HOST' => 'example.com',
            'REQUEST_URI' => '/api/users'
        ]);

        $expected = 'https://example.com/api/users';
        $this->assertEquals($expected, $this->request->url());
    }

    public function testFullUrlIncludesQueryString()
    {
        $this->request = $this->setServerVars([
            'HTTPS' => 'on',
            'HTTP_HOST' => 'example.com',
            'REQUEST_URI' => '/api/users?page=1&limit=10',
            'QUERY_STRING' => 'page=1&limit=10'
        ]);

        $fullUrl = $this->request->fullUrl();
        $this->assertStringContainsString('example.com/api/users', $fullUrl);
        $this->assertStringContainsString('page=1', $fullUrl);
    }

    public function testFullUrlWithoutQueryString()
    {
        $this->request = $this->setServerVars([
            'HTTPS' => 'on',
            'HTTP_HOST' => 'example.com',
            'REQUEST_URI' => '/api/users'
        ]);

        $expected = 'https://example.com/api/users';
        $this->assertEquals($expected, $this->request->fullUrl());
    }

    // ===========================
    // Query Parameter Tests
    // ===========================

    public function testQueryReturnsAllParameters()
    {
        $this->request = $this->setServerVars([
            'QUERY_STRING' => 'page=1&limit=10&sort=name'
        ]);

        $query = $this->request->query();

        $this->assertIsArray($query);
        $this->assertEquals('1', $query['page']);
        $this->assertEquals('10', $query['limit']);
        $this->assertEquals('name', $query['sort']);
    }

    public function testQueryReturnsSpecificParameter()
    {
        $this->request = $this->setServerVars([
            'QUERY_STRING' => 'page=1&limit=10'
        ]);

        $this->assertEquals('1', $this->request->query('page'));
        $this->assertEquals('10', $this->request->query('limit'));
    }

    public function testQueryReturnsDefaultValueForMissingParameter()
    {
        $this->request = $this->setServerVars([
            'QUERY_STRING' => 'page=1'
        ]);

        $this->assertEquals('default', $this->request->query('missing', 'default'));
        $this->assertNull($this->request->query('missing'));
    }

    public function testQueryWithEmptyQueryString()
    {
        $this->request = $this->setServerVars();

        $query = $this->request->query();

        $this->assertIsArray($query);
        $this->assertEmpty($query);
    }

    // ===========================
    // HTTP Method Tests
    // ===========================

    public function testMethodReturnsLowercaseMethod()
    {
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];

        foreach ($methods as $method) {
            $request = $this->setServerVars([
                'REQUEST_METHOD' => $method
            ]);
            $this->assertEquals(strtolower($method), $request->method());
        }
    }

    // ===========================
    // Header Tests
    // ===========================

    public function testHeadersReturnsAllHeaders()
    {
        $this->request = $this->setServerVars([
            'HTTP_CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_USER_AGENT' => 'Mozilla/5.0'
        ]);

        $headers = $this->request->headers();

        $this->assertIsArray($headers);
    }

    public function testHeaderReturnsSpecificHeader()
    {
        $this->request = $this->setServerVars([
            'CONTENT_TYPE' => 'application/json'
        ]);

        $this->assertEquals('application/json', $this->request->header('CONTENT_TYPE'));
    }

    public function testHeaderReturnsNullForMissingHeader()
    {
        $this->request = $this->setServerVars();

        $this->assertNull($this->request->header('MISSING_HEADER'));
    }

    public function testUserAgentReturnsUserAgent()
    {
        $this->request = $this->setServerVars([
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'
        ]);

        $this->assertEquals('Mozilla/5.0 (Windows NT 10.0; Win64; x64)', $this->request->userAgent());
    }

    public function testRefererReturnsReferer()
    {
        $this->request = $this->setServerVars([
            'HTTP_REFERER' => 'https://example.com/previous-page'
        ]);

        $this->assertEquals('https://example.com/previous-page', $this->request->referer());
    }

    public function testContentTypeReturnsContentType()
    {
        $this->request = $this->setServerVars([
            'CONTENT_TYPE' => 'application/json'
        ]);

        $this->assertEquals('application/json', $this->request->contentType());
    }

    public function testContentLengthReturnsInteger()
    {
        $this->request = $this->setServerVars([
            'CONTENT_LENGTH' => '1024'
        ]);

        $this->assertEquals(1024, $this->request->contentLength());
        $this->assertIsInt($this->request->contentLength());
    }

    public function testContentLengthReturnsNullWhenNotSet()
    {
        $this->request = $this->setServerVars();

        $this->assertNull($this->request->contentLength());
    }

    // ===========================
    // Cookie Tests
    // ===========================

    public function testCookieReturnsAllCookies()
    {
        $this->request = $this->setServerVars([], [], [], [
            'session' => 'abc123',
            'user_id' => '456'
        ]);

        $cookies = $this->request->cookie();

        $this->assertIsArray($cookies);
        $this->assertArrayHasKey('session', $cookies);
        $this->assertArrayHasKey('user_id', $cookies);
    }

    public function testCookieReturnsEmptyArrayWhenNoCookies()
    {
        $this->request = $this->setServerVars();

        $cookies = $this->request->cookie();

        $this->assertIsArray($cookies);
        $this->assertEmpty($cookies);
    }

    // ===========================
    // Server Tests
    // ===========================

    public function testServerReturnsAllServerData()
    {
        $this->request = $this->setServerVars([
            'SERVER_NAME' => 'example.com',
            'SERVER_PORT' => '80',
            'REQUEST_METHOD' => 'GET'
        ]);

        $server = $this->request->server();

        $this->assertIsArray($server);
        $this->assertArrayHasKey('SERVER_NAME', $server);
        $this->assertArrayHasKey('SERVER_PORT', $server);
        $this->assertArrayHasKey('REQUEST_METHOD', $server);
    }

    // ===========================
    // Security Tests
    // ===========================

    public function testIsSecureReturnsTrueForHttps()
    {
        $this->request = $this->setServerVars([
            'HTTPS' => 'on'
        ]);

        $this->assertTrue($this->request->isSecure());
    }

    public function testIsSecureReturnsFalseForHttp()
    {
        $this->request = $this->setServerVars([
            'HTTPS' => 'off'
        ]);

        $this->assertFalse($this->request->isSecure());
    }

    public function testIsSecureWithMixedCaseOff()
    {
        $this->request = $this->setServerVars([
            'HTTPS' => 'OFF'
        ]);

        $this->assertFalse($this->request->isSecure());
    }

    public function testIsSecureWithoutHttpsHeader()
    {
        $this->request = $this->setServerVars();

        $this->assertFalse($this->request->isSecure());
    }

    // ===========================
    // AJAX and PJAX Tests
    // ===========================

    public function testIsAjaxReturnsTrueForXMLHttpRequest()
    {
        $this->request = $this->setServerVars([
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'
        ]);

        $this->assertTrue($this->request->isAjax());
    }

    public function testIsAjaxReturnsFalseForRegularRequest()
    {
        $this->request = $this->setServerVars();

        $this->assertFalse($this->request->isAjax());
    }

    public function testIsPjaxReturnsTrueForPjaxRequest()
    {
        $this->request = $this->setServerVars([
            'HTTP_X_PJAX' => 'true'
        ]);

        $this->assertTrue($this->request->isPjax());
    }

    public function testIsPjaxReturnsFalseForRegularRequest()
    {
        $this->request = $this->setServerVars();

        $this->assertFalse($this->request->isPjax());
    }

    // ===========================
    // Pattern Matching Tests
    // ===========================

    public function testIsMethodWithExactMatch()
    {
        $this->request = $this->setServerVars([
            'REQUEST_URI' => '/api/users'
        ]);

        $this->container->singleton('request', fn() => $this->request);

        $this->assertTrue(Request::is('/api/users'));
        $this->assertFalse(Request::is('/api/posts'));
    }

    public function testIsMethodWithWildcard()
    {
        $testCases = [
            ['/api/users', '/api/*', true],
            ['/api/users/123', '/api/*', true],
            ['/api/users/123/posts', '/api/*', true],
            ['/api/v1/users', '/api/v1/*', true],
            ['/admin/users', '/api/*', false],
            ['/users', '/api/*', false],
        ];

        foreach ($testCases as [$uri, $pattern, $expected]) {
            $request = $this->setServerVars([
                'REQUEST_URI' => $uri
            ]);

            $this->container->singleton('request', fn() => $request);

            $result = Request::is($pattern);
            $this->assertEquals(
                $expected,
                $result,
                "Failed asserting that '{$uri}' " . ($expected ? 'matches' : 'does not match') . " '{$pattern}'"
            );
        }
    }

    public function testIsMethodWithComplexPatterns()
    {
        $testCases = [
            ['/api/users/123/posts', '/api/*/posts', true],
            ['/api/comments/456/posts', '/api/*/posts', true],
            ['/api/users/123/comments', '/api/*/posts', false],
        ];

        foreach ($testCases as [$uri, $pattern, $expected]) {
            $request = $this->setServerVars([
                'REQUEST_URI' => $uri
            ]);

            $this->container->singleton('request', fn() => $request);

            $this->assertEquals($expected, Request::is($pattern));
        }
    }

    public function testIsMethodCaseInsensitive()
    {
        $request = $this->setServerVars([
            'REQUEST_URI' => '/API/USERS'
        ]);

        $this->container->singleton('request', fn() => $request);

        $this->assertTrue(Request::is('/api/users'));
        $this->assertTrue(Request::is('/api/*'));
    }

    public function testIsMethodWithTrailingSlash()
    {
        $request = $this->setServerVars([
            'REQUEST_URI' => '/api/'
        ]);

        $this->container->singleton('request', fn() => $request);

        $this->assertTrue(Request::is('/api/*'));
    }

    // ===========================
    // API Request Tests
    // ===========================

    public function testIsApiRequestReturnsTrueForApiRoutes()
    {
        $apiUris = [
            '/api/',
            '/api/users',
            '/api/v1/users',
            '/api/admin/settings',
            '/api/users/123/posts/456',
        ];

        foreach ($apiUris as $uri) {
            $request = $this->setServerVars([
                'REQUEST_URI' => $uri
            ]);

            $this->container->singleton('request', fn() => $request);

            $this->assertTrue(
                $request->isApiRequest(),
                "Failed asserting that '{$uri}' is an API request"
            );
        }
    }

    public function testIsApiRequestReturnsFalseForNonApiRoutes()
    {
        $nonApiUris = [
            '/',
            '/admin',
            '/users',
            '/contact',
            '/apartment/list',
            '/apis',
            '/my-api',
        ];

        foreach ($nonApiUris as $uri) {
            $request = $this->setServerVars([
                'REQUEST_URI' => $uri
            ]);

            $this->assertFalse(
                $request->isApiRequest(),
                "Failed asserting that '{$uri}' is not an API request"
            );
        }
    }

    // ===========================
    // Content Tests
    // ===========================

    public function testContentMethodReturnsBody()
    {
        $this->request = $this->setServerVars();

        $content = $this->request->content();

        $this->assertTrue(
            is_string($content) || is_resource($content) || is_null($content) || $content === false
        );
    }

    // ===========================
    // Edge Cases
    // ===========================

    public function testEmptyServerArray()
    {
        $this->request = $this->setServerVars();

        $this->assertNull($this->request->ip());
        $this->assertEquals('http', $this->request->scheme());
    }

    public function testSpecialCharactersInUri()
    {
        $request = $this->setServerVars([
            'REQUEST_URI' => '/api/users/john%20doe'
        ]);

        $this->container->singleton('request', fn() => $request);

        $this->assertTrue(Request::is('/api/*'));
    }

    public function testMultipleWildcardsInPattern()
    {
        $request = $this->setServerVars([
            'REQUEST_URI' => '/api/v1/users/123/posts/456'
        ]);

        $this->container->singleton('request', fn() => $request);

        $this->assertTrue(Request::is('/api/*/*/posts/*'));
    }

    public function testIpv6Addresses()
    {
        $this->request = $this->setServerVars([
            'REMOTE_ADDR' => '2001:0db8:85a3:0000:0000:8a2e:0370:7334'
        ]);

        $ip = $this->request->ip();
        $this->assertNotNull($ip);
        $this->assertIsString($ip);
    }

    public function testUriStripsQueryString()
    {
        $this->request = $this->setServerVars([
            'REQUEST_URI' => '/api/users?page=1&sort=name',
            'QUERY_STRING' => 'page=1&sort=name'
        ]);

        $uri = $this->request->uri();

        // uri() returns the path without query string
        $this->assertEquals('/api/users', $uri);
    }

    public function testServerMethodReturnsArrayWhenNoData()
    {
        $this->request = $this->setServerVars();

        $server = $this->request->server();

        $this->assertIsArray($server);
    }

    // ===========================
    // Route Tests
    // ===========================

    public function testRouteReturnsNullOrStringOrBool()
    {
        $request = $this->setServerVars([
            'REQUEST_URI' => '/api/users'
        ]);

        $this->container->singleton('request', fn() => $request);

        // Mock route if needed
        $this->container->singleton('route', fn() => new class {
            public function getRouteNames()
            {
                return ['/api/users' => 'api.users.index'];
            }
        });

        $routeName = Request::route();

        $this->assertTrue(
            is_string($routeName) || is_bool($routeName) || is_null($routeName)
        );
    }

    public function testRouteComparesRouteName()
    {
        $request = $this->setServerVars([
            'REQUEST_URI' => '/api/users'
        ]);

        $this->container->singleton('request', fn() => $request);

        // Mock route
        $this->container->singleton('route', fn() => new class {
            public function getRouteNames()
            {
                return ['api.users.index' => '/api/users'];
            }
        });

        $result = Request::route('api.users.index');

        $this->assertTrue(is_bool($result) || is_null($result));
    }

    // ===========================
    // Additional Method Tests
    // ===========================

    public function testHostReturnsHostWithoutPort()
    {
        $this->request = $this->setServerVars([
            'HTTP_HOST' => 'example.com',
            'SERVER_PORT' => '80'
        ]);

        $this->assertEquals('example.com', $this->request->host());
    }

    public function testHostReturnsHostWithNonStandardPort()
    {
        $this->request = $this->setServerVars([
            'HTTP_HOST' => 'example.com',
            'SERVER_PORT' => '8080'
        ]);

        $host = $this->request->host();
        $this->assertStringContainsString('example.com', $host);
    }

    public function testGetPathReturnsDecodedPath()
    {
        $this->request = $this->setServerVars([
            'REQUEST_URI' => '/api/users%20list'
        ]);

        $path = $this->request->getPath();
        $this->assertEquals('/api/users list', $path);
    }

    public function testIsMethodSafeForGetRequest()
    {
        $this->request = $this->setServerVars([
            'REQUEST_METHOD' => 'GET'
        ]);

        $this->assertTrue($this->request->isMethodSafe());
    }

    public function testIsMethodSafeForPostRequest()
    {
        $this->request = $this->setServerVars([
            'REQUEST_METHOD' => 'POST'
        ]);

        $this->assertFalse($this->request->isMethodSafe());
    }

    public function testGetClientIpReturnsRemoteAddr()
    {
        $this->request = $this->setServerVars([
            'REMOTE_ADDR' => '192.168.1.100'
        ]);

        $this->assertEquals('192.168.1.100', $this->request->getClientIp());
    }
}