<?php

namespace Tests\Unit\Http\Response;

use ReflectionClass;
use Phaseolies\Session\MessageBag;
use Phaseolies\Http\Response\RedirectResponse;
use Phaseolies\DI\Container;
use Phaseolies\Support\Session;
use Phaseolies\Support\StringService;
use PHPUnit\Framework\TestCase;

class MockRouter
{
    public static $namedRoutes = [];

    public static function addRoute($method, $route, $action, $name = null)
    {
        if ($name) {
            self::$namedRoutes[$name] = $route;
        }
    }
}

class MockSession
{
    private $data = [];

    public function get($key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    public function put($key, $value)
    {
        $this->data[$key] = $value;
    }

    public function forget($key)
    {
        unset($this->data[$key]);
    }

    public function flash($key, $value)
    {
        $this->data[$key] = $value;
    }
}

class MockRequest
{
    public $headers;

    public function __construct()
    {
        $this->headers = new class {
            private $headers = [];

            public function get($key, $default = null)
            {
                return $this->headers[$key] ?? $default;
            }

            public function set($key, $value)
            {
                $this->headers[$key] = $value;
            }
        };
    }
}

class MockMessageBag
{
    private static $data = [];
    private static $flashed = [];

    public static function set($key, $value)
    {
        self::$data[$key] = $value;
    }

    public static function get($key, $default = null)
    {
        return self::$data[$key] ?? $default;
    }

    public static function flashInput()
    {
        self::$flashed['input'] = true;
    }

    public static function clear()
    {
        self::$data = [];
        self::$flashed = [];
    }

    public static function getFlashed()
    {
        return self::$flashed;
    }
}

class RedirectResponseTest extends TestCase
{
    private RedirectResponse $redirect;
    private MockSession $session;
    private MockRequest $request;
    private $originalRouter;

    protected function setUp(): void
    {
        $container = new Container();
        $container->bind('session', Session::class);
        $container->bind('str', StringService::class);

        $this->session = new MockSession();
        $this->request = new MockRequest();

        $this->replaceRouterWithMock();

        $this->redirect = new RedirectResponse();

        MockMessageBag::clear();
        MockRouter::$namedRoutes = [];

        $this->setupGlobalMocks();

        $this->replaceMessageBag();
    }

    private function replaceRouterWithMock()
    {
        // Use reflection to replace the Router class reference in RedirectResponse
        $reflection = new ReflectionClass(RedirectResponse::class);
        // $routerProperty = $reflection->getProperty('router');
        // dd($routerProperty);
        // $routerProperty->setAccessible(true);

        // // Store original router if needed
        // $this->originalRouter = $routerProperty->getValue();

        // // Replace with mock
        // $routerProperty->setValue(null, MockRouter::class);
    }

    private function setupGlobalMocks()
    {
        // Mock global session() function
        if (!function_exists('session')) {
            function session()
            {
                global $testSession;
                return $testSession ?? new class {
                    public function get($key, $default = null)
                    {
                        return $default;
                    }
                    public function put($key, $value) {}
                    public function forget($key) {}
                };
            }
        }

        // Mock global request() function
        if (!function_exists('request')) {
            function request()
            {
                global $testRequest;
                return $testRequest ?? new class {
                    public $headers;
                    public function __construct()
                    {
                        $this->headers = new class {
                            public function get($key, $default = null)
                            {
                                return $default;
                            }
                        };
                    }
                };
            }
        }

        global $testSession, $testRequest;
        $testSession = $this->session;
        $testRequest = $this->request;
    }

    private function replaceMessageBag()
    {
        $reflection = new ReflectionClass(MessageBag::class);
    }

    public function testSetTargetUrlWithValidUrl()
    {
        $url = 'https://example.com';
        $result = $this->redirect->setTargetUrl($url);

        $this->assertSame($this->redirect, $result);
        $this->assertEquals($url, $this->redirect->headers->get('Location'));
        $this->assertEquals('text/html; charset=utf-8', $this->redirect->headers->get('Content-Type'));
        $this->assertStringContainsString('Redirecting to', $this->redirect->getBody());
        $this->assertStringContainsString(htmlspecialchars($url, ENT_QUOTES, 'UTF-8'), $this->redirect->getBody());
    }

    public function testSetTargetUrlWithEmptyUrlThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot redirect to an empty URL.');

        $this->redirect->setTargetUrl('');
    }

    public function testToMethod()
    {
        $url = '/dashboard';
        $statusCode = 301;
        $headers = ['X-Custom' => 'value'];

        $result = $this->redirect->to($url, $statusCode, $headers);

        $this->assertSame($this->redirect, $result);
        $this->assertEquals($statusCode, $this->redirect->getStatusCode());
        $this->assertEquals($url, $this->redirect->headers->get('Location'));
        $this->assertEquals('value', $this->redirect->headers->get('X-Custom'));
    }

    public function testToMethodWithSecureTrue()
    {
        $this->request->headers->set('host', 'example.com');
        $url = 'http://example.com/profile';

        $result = $this->redirect->to($url, 302, [], true);

        $location = $this->redirect->headers->get('Location');
        $this->assertStringStartsWith('https://example.com', $location);
        $this->assertEquals('https://example.com/profile', $location);
    }

    public function testToMethodWithSecureFalse()
    {
        $this->request->headers->set('host', 'example.com');
        $url = 'http://example.com/profile';

        $result = $this->redirect->to($url, 302, [], false);

        $location = $this->redirect->headers->get('Location');
        $this->assertStringStartsWith('http://example.com', $location);
        $this->assertEquals('http://example.com/profile', $location);
    }

    public function testBackMethodWithReferer()
    {
        $referer = 'https://example.com/previous';
        $this->request->headers->set('referer', $referer);

        $result = $this->redirect->back(301, ['X-Test' => 'value']);

        $this->assertSame($this->redirect, $result);
        $this->assertEquals(301, $this->redirect->getStatusCode());
        $this->assertEquals('value', $this->redirect->headers->get('X-Test'));
    }

    public function testBackMethodWithoutReferer()
    {
        $this->request->headers->set('referer', null);

        $result = $this->redirect->back();

        $this->assertEquals('/', $this->redirect->headers->get('Location'));
    }

    public function testIntendedMethodWithStoredUrl()
    {
        $intendedUrl = 'https://example.com/intended';
        $this->session->put('url.intended', $intendedUrl);

        $result = $this->redirect->intended('/default');

        $this->assertEquals($intendedUrl, $this->session->get('url.intended'));
    }

    public function testIntendedMethodWithoutStoredUrl()
    {
        $defaultUrl = '/dashboard';
        $result = $this->redirect->intended($defaultUrl, 301);

        $this->assertEquals($defaultUrl, $this->redirect->headers->get('Location'));
        $this->assertEquals(301, $this->redirect->getStatusCode());
    }

    public function testAwayMethod()
    {
        $externalUrl = 'https://external-site.com';

        $result = $this->redirect->away($externalUrl, 301);

        $this->assertSame($this->redirect, $result);
        $this->assertEquals($externalUrl, $this->redirect->headers->get('Location'));
        $this->assertEquals(301, $this->redirect->getStatusCode());
    }

    public function testWithErrorsMethod()
    {
        $errors = ['email' => 'Invalid email address', 'password' => 'Too short'];

        $result = $this->redirect->withErrors($errors);

        $this->assertSame($this->redirect, $result);
    }

    public function testWithInputMethod()
    {
        $result = $this->redirect->withInput();

        $this->assertSame($this->redirect, $result);
    }

    public function testWithMethod()
    {
        $result = $this->redirect->with('success', 'Operation completed successfully');

        $this->assertSame($this->redirect, $result);

        $this->assertEquals('Operation completed successfully', session('success'));
    }

    public function testDynamicWithMethods()
    {
        $result = $this->redirect->withSuccess('Great success!');

        $this->assertSame($this->redirect, $result);
        $this->assertEquals('Great success!', session('success'));

        $result = $this->redirect->withError('Something went wrong');
        $this->assertEquals('Something went wrong', session('error'));

        $result = $this->redirect->withWarning('Proceed with caution');
        $this->assertEquals('Proceed with caution', session('warning'));
    }

    public function testDynamicWithMethodWithInvalidName()
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Method invalidMethod does not exist.');

        $this->redirect->invalidMethod('value');
    }

    public function testMultipleWithCalls()
    {
        $result = $this->redirect
            ->with('message', 'Hello')
            ->with('type', 'info')
            ->with('count', 5);

        $this->assertSame($this->redirect, $result);
        $this->assertEquals('Hello', session('message'));
        $this->assertEquals('info', session('type'));
        $this->assertEquals(5, session('count'));
    }

    public function testChainableMethods()
    {
        $result = $this->redirect
            ->to('/dashboard')
            ->with('status', 'redirected');

        $this->assertSame($this->redirect, $result);
        $this->assertEquals('/dashboard', $this->redirect->headers->get('Location'));
        $this->assertEquals('redirected', session('status'));
    }
}
