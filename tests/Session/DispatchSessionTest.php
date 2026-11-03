<?php

namespace Tests\Unit\Session;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Phaseolies\Application;
use Phaseolies\Http\Exceptions\HttpException;
use Phaseolies\Http\Exceptions\HttpResponseException;
use Phaseolies\Http\Request;
use Phaseolies\Http\Response;
use Phaseolies\Session\Stores\ArrayStore;
use Phaseolies\Support\Router;
use Phaseolies\Support\Session;
use ReflectionClass;
use RuntimeException;

#[AllowMockObjectsWithoutExpectations]
final class DispatchSessionTest extends TestCase
{
    private const COOKIE = 'test_session';

    private Application $app;

    private ArrayStore $store;

    protected function setUp(): void
    {
        $this->app = (new ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        $this->store = new ArrayStore();
    }

    private function request(): Request
    {
        $request = new Request();
        $request->session = new Session($this->store, [
            'cookie' => self::COOKIE,
            'lifetime' => 120,
            'stateless' => [],
            'lottery' => [0, 100],
        ]);
        $request->session->attach($request);

        return $request;
    }

    private function routeTo(callable $handler): void
    {
        $router = $this->createMock(Router::class);
        $router->method('resolve')->willReturnCallback($handler);
        $this->app->router = $router;
    }

    private function savedCookie(Response $response): ?string
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === self::COOKIE) {
                return $cookie->getValue();
            }
        }

        return null;
    }

    public function testASessionWrittenBeforeAValidationRedirectIsSavedOnThatRedirect(): void
    {
        // Validation failures throw an HttpResponseException that carries the
        // redirect. The flashed errors and old input must survive it.
        $redirect = new Response('', 302);

        $request = $this->request();
        $this->routeTo(function () use ($request, $redirect) {
            $request->session()->put('errors', ['name' => ['The Name field is required']]);
            $request->session()->put('input', ['name' => '']);

            throw (new HttpResponseException('invalid', 422))->setResponse($redirect);
        });

        try {
            $this->app->dispatch($request);
            $this->fail('The exception must still reach the error handler.');
        } catch (HttpResponseException $e) {
            $this->assertSame($redirect, $e->getResponse());
        }

        $value = $this->savedCookie($redirect);

        $this->assertNotNull($value, 'The redirect must carry the session cookie.');
        $this->assertSame(
            ['name' => ['The Name field is required']],
            $this->store->read($value)['data']['errors']
        );
    }

    public function testAnHttpResponseExceptionWithoutASessionSendsNoCookie(): void
    {
        $redirect = new Response('', 302);

        $request = $this->request();
        $this->routeTo(fn () => throw (new HttpResponseException('x', 404))->setResponse($redirect));

        try {
            $this->app->dispatch($request);
        } catch (HttpResponseException) {
        }

        $this->assertNull($this->savedCookie($redirect));
    }

    public function testAnUnexpectedExceptionIsNotMaskedByTheSession(): void
    {
        $request = $this->request();
        $this->routeTo(function () use ($request) {
            $request->session()->put('a', 1);

            throw new RuntimeException('boom');
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('boom');

        $this->app->dispatch($request);
    }

    public function testAFailingSessionStoreNeverReplacesTheOriginalException(): void
    {
        $request = $this->request();
        $request->session = new Session(new class extends ArrayStore {
            public function write(string $id, array $data): string
            {
                throw new RuntimeException('store down');
            }
        }, ['cookie' => self::COOKIE, 'lifetime' => 120, 'stateless' => [], 'lottery' => [0, 100]]);
        $request->session->attach($request);

        $redirect = new Response('', 302);
        $this->routeTo(function () use ($request, $redirect) {
            $request->session()->put('a', 1);

            throw (new HttpResponseException('invalid', 422))->setResponse($redirect);
        });

        $this->expectException(HttpResponseException::class);

        $this->app->dispatch($request);
    }

    public function testAFailingSessionSaveDoesNotTurnAnHttpExceptionIntoAnError(): void
    {
        $request = $this->request();
        $request->session = new Session(new class extends ArrayStore {
            public function write(string $id, array $data): string
            {
                throw new RuntimeException('store down');
            }
        }, ['cookie' => self::COOKIE, 'lifetime' => 120, 'stateless' => [], 'lottery' => [0, 100]]);
        $request->session->attach($request);

        $this->routeTo(function () use ($request) {
            $request->session()->put('a', 1);

            throw new HttpException(404, 'Not found');
        });

        // The error page is rendered for real; keep it out of the test output.
        ob_start();
        set_error_handler(static fn() => true);

        try {
            $this->app->dispatch($request);
        } catch (\Throwable $e) {
            // Rendering the error page needs a bootstrapped application, so it may
            // raise something here. Whatever it raises, it must not be the failure
            // of the session store, which would have replaced the original error.
            $this->assertNotSame('store down', $e->getMessage());
        } finally {
            restore_error_handler();
            ob_end_clean();
        }

        $this->addToAssertionCount(1);
    }
}
