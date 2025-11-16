<?php

namespace Tests\Unit\Requests;

use Phaseolies\Support\Session;
use Phaseolies\Support\File;
use Phaseolies\Http\ServerBag;
use Phaseolies\Http\Request;
use Phaseolies\Http\ParameterBag;
use Phaseolies\Http\InputBag;
use Phaseolies\Http\HeaderBag;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;
use RuntimeException;

class AllRequestInputTest extends TestCase
{
    protected Request $request;
    protected array $defaultServerData;

    protected function setUp(): void
    {
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
}