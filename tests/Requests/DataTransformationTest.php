<?php

namespace Tests\Unit\Requests;

use Tests\Support\MockContainer;
use Tests\Support\Kernel;
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


if (!class_exists('App\Http\Kernel')) {
    class_alias(Kernel::class, 'App\Http\Kernel');
}

function base_path($path = '')
{
    return '/test/path' . ($path ? '/' . $path : '');
}

function config($key = null, $default = null)
{
    return $default;
}

function env($key, $default = null)
{
    return $default;
}

function app($abstract = null, array $parameters = [])
{
    return \Phaseolies\DI\Container::getInstance()->make($abstract, $parameters);
}

class DataTransformationTest extends TestCase
{
    protected Request $request;
    protected array $defaultServerData;

    protected function setUp(): void
    {
        $container = new Container();
        Container::setInstance(new MockContainer());
        $container = new Container();
        $container->bind('request', fn() => Request::class);
        $container = new Container();
        $this->request = new Request();
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

    public function testItPipesInputThroughCallback()
    {
        $_POST = ['name' => '  john doe  '];
        
        $request = new Request();
        
        $result = $request->pipe('name', 'trim');
        
        $this->assertEquals('john doe', $result);
    }

    public function testItTapsInputWithCallback()
    {
        $_POST = ['count' => 5];

        $request = new Request();

        $captured = null;
        $result = $request->tapInput('count', function($value) use (&$captured) {
            $captured = $value * 2;
        });

        $this->assertEquals(10, $captured);
        $this->assertSame($request, $result);
    }

    public function testItExecutesCallbackIfFilled()
    {
        $_POST = ['name' => 'John', 'empty' => ''];

        $request = new Request();

        $executed = false;
        $request->ifFilled('name', function() use (&$executed) {
            $executed = true;
        });
        $this->assertTrue($executed);

        $executed = false;
        $request->ifFilled('empty', function() use (&$executed) {
            $executed = true;
        });
        $this->assertFalse($executed);
    }

    public function testItTransformsInputs()
    {
        $_POST = ['name' => 'JOHN', 'email' => 'JOHN@EXAMPLE.COM'];

        $request = new Request();

        $transformed = $request->transform([
            'name' => fn($v) => strtolower($v),
            'email' => fn($v) => strtolower($v)
        ]);

        $this->assertEquals('john', $transformed['name']);
        $this->assertEquals('john@example.com', $transformed['email']);
    }

    public function testItEnsuresValidationWithCallback()
    {
        $_POST = ['age' => 25];

        $request = new Request();

        $result = $request->ensure('age', fn($v) => is_numeric($v) && $v >= 18);

        $this->assertSame($request, $result);
    }

    public function testItThrowsExceptionWhenEnsureFails()
    {
        $this->expectException(InvalidArgumentException::class);

        $_POST = ['age' => 15];

        $request = new Request();
        $request->ensure('age', fn($v) => $v >= 18);
    }

    public function testItProcessesContextualData()
    {
        $_POST = ['count' => 5];

        $request = new Request();

        $request->contextual(function($data) {
            return ['count' => $data['count'] * 2, 'processed' => true];
        });

        $this->assertEquals(10, $request->input('count'));
        $this->assertTrue($request->input('processed'));
    }

    public function testItExtractsDataWithCallback()
    {
        $_POST = ['name' => 'John', 'email' => 'john@example.com'];

        $request = new Request();

        $result = $request->extract(function($req) {
            return $req->only('name');
        });

        $this->assertArrayHasKey('name', $result);
        $this->assertArrayNotHasKey('email', $result);
    }

    public function testItNullifiesBlankValues()
    {
        $_POST = [
            'name' => 'John',
            'empty' => '',
            'whitespace' => '   ',
            'zero' => '0'
        ];

        $request = new Request();
        $request->nullifyBlanks();

        $this->assertEquals('John', $request->input('name'));
        $this->assertNull($request->input('empty'));
        $this->assertNull($request->input('whitespace'));
        $this->assertNotNull($request->input('zero'));
    }

    public function testItCleansesInputData()
    {
        $_POST = [
            'name' => '  John  ',
            'bio' => '<p>Hello <script>alert("xss")</script></p>',
            'age' => '25',
            'email' => 'JOHN@EXAMPLE.COM'
        ];

        $request = new Request();

        $cleansed = $request->cleanse([
            'name' => 'trim',
            'bio' => 'strip_tags',
            'age' => 'int',
            'email' => 'lowercase'
        ]);

        $this->assertEquals('John', $cleansed['name']);
        $this->assertEquals('Hello alert("xss")', $cleansed['bio']);
        $this->assertEquals(25, $cleansed['age']);
        $this->assertEquals('john@example.com', $cleansed['email']);
    }

    public function testItCleansesNestedInputData()
    {
        $_POST = [
            'user' => [
                'name' => '  Jane  ',
                'email' => 'JANE@EXAMPLE.COM'
            ]
        ];

        $request = new Request();

        $cleansed = $request->cleanse([
            'user.name' => 'trim',
            'user.email' => 'lowercase'
        ]);

        $this->assertEquals('Jane', $cleansed['user']['name']);
        $this->assertEquals('jane@example.com', $cleansed['user']['email']);
    }

    public function testItMapsDataConditionally()
    {
        $_POST = ['count' => 10];

        $request = new Request();

        $result = $request->mapIf(true, fn($data) => ['count' => $data['count'] * 2]);
        $this->assertEquals(20, $result['count']);

        $result = $request->mapIf(false, fn($data) => ['count' => $data['count'] * 2]);
        $this->assertEquals(10, $result['count']);
    }

    public function testItConvertsInputToArray()
    {
        $_POST = ['tags' => '1,2,3,4,5'];

        $request = new Request();

        $array = $request->asArray('tags');

        $this->assertIsArray($array);
        $this->assertCount(5, $array);
        $this->assertEquals(['1', '2', '3', '4', '5'], $array);
    }

    public function testItBindsDataToObject()
    {
        $_POST = ['name' => 'John', 'email' => 'john@example.com', 'age' => 30];

        $request = new Request();

        $dto = new class {
            public $name;
            public $email;
            public $age;
        };

        $result = $request->bindTo($dto);

        $this->assertEquals('John', $result->name);
        $this->assertEquals('john@example.com', $result->email);
        $this->assertEquals(30, $result->age);
    }

    public function testItBindsDataInStrictMode()
    {
        $_POST = ['name' => 'John', 'nonexistent' => 'value'];

        $request = new Request();

        $dto = new class {
            public $name;
        };

        $result = $request->bindTo($dto, true);

        $this->assertEquals('John', $result->name);
        $this->assertFalse(property_exists($result, 'nonexistent'));
    }

    public function testItBindsDataInNonStrictMode()
    {
        $_POST = ['name' => 'John', 'extra' => 'value'];

        $request = new Request();

        $dto = new #[\AllowDynamicProperties] class {
            public $name;
        };

        $result = $request->bindTo($dto, false);

        $this->assertEquals('John', $result->name);
        $this->assertEquals('value', $result->extra);
    }
}