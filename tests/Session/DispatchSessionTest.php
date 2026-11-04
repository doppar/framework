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

/**
 * An application that remembers the session failures it was asked to report.
 */
class LoggingApplication extends Application
{
    /** @var string[] */
    public array $logged = [];

    protected function reportSessionFailure(\Throwable $failure): void
    {
        $this->logged[] = $failure->getMessage();
    }
}

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

    private function requestOn(string $id): Request
    {
        $request = $this->request();
        $request->cookies = new \Phaseolies\Http\InputBag([self::COOKIE => $id]);

        return $request;
    }

    private function seed(array $data = ['user' => 7]): string
    {
        $id = bin2hex(random_bytes(20));
        $this->store->write($id, $data + ['last_regenerated' => time()]);

        return $id;
    }

    /**
     * An application that remembers what it was asked to log.
     */
    private function loggingApplication(): LoggingApplication
    {
        return (new ReflectionClass(LoggingApplication::class))->newInstanceWithoutConstructor();
    }

    public function testALogoutIsAppliedEvenWhenTheRequestThenThrows(): void
    {
        // The real logout path: forget the user, invalidate, and then something
        // else in the request (a listener, a database write) throws.
        $id = $this->seed();
        $request = $this->requestOn($id);

        $this->routeTo(function () use ($request) {
            $request->session()->forget('user');
            $request->session()->invalidate();

            throw new RuntimeException('boom');
        });

        try {
            $this->app->dispatch($request);
            $this->fail('The exception must still propagate.');
        } catch (RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertNull($this->store->read($id), 'The logout was dropped: the old session still works.');
    }

    public function testDestroyingTheSessionIsAppliedEvenWhenTheRequestThenThrows(): void
    {
        $id = $this->seed();
        $request = $this->requestOn($id);

        $this->routeTo(function () use ($request) {
            $request->session()->destroy();

            throw new RuntimeException('boom');
        });

        try {
            $this->app->dispatch($request);
        } catch (RuntimeException) {
        }

        $this->assertNull($this->store->read($id));
    }

    public function testFlushingTheSessionIsAppliedEvenWhenTheRequestThenThrows(): void
    {
        $id = $this->seed();
        $request = $this->requestOn($id);

        $this->routeTo(function () use ($request) {
            $request->session()->flush();
            $request->session()->put('flash', 'ignored');

            throw new RuntimeException('boom');
        });

        try {
            $this->app->dispatch($request);
        } catch (RuntimeException) {
        }

        $this->assertNull($this->store->read($id));
    }

    public function testOrdinaryChangesAreStillDroppedWhenTheRequestThrows(): void
    {
        $id = $this->seed();
        $request = $this->requestOn($id);

        $this->routeTo(function () use ($request) {
            $request->session()->put('cart', ['apple']);
            $request->session()->forget('user');

            throw new RuntimeException('boom');
        });

        try {
            $this->app->dispatch($request);
        } catch (RuntimeException) {
        }

        $stored = $this->store->read($id)['data'];

        $this->assertSame(7, $stored['user'], 'A plain write was saved by a failed request.');
        $this->assertArrayNotHasKey('cart', $stored);
    }

    public function testALoginThatIsInterruptedDoesNotEndTheSessionItWasMoving(): void
    {
        // regenerate() moves a session to a new id. If the request then throws,
        // the old session must stay valid: only an explicit end is applied.
        $id = $this->seed();
        $request = $this->requestOn($id);

        $this->routeTo(function () use ($request) {
            $request->session()->regenerate();
            $request->session()->put('user', 8);

            throw new RuntimeException('boom');
        });

        try {
            $this->app->dispatch($request);
        } catch (RuntimeException) {
        }

        $this->assertSame(7, $this->store->read($id)['data']['user']);
    }

    public function testASessionThatCannotBeSavedIsLoggedAndTheOriginalErrorStillWins(): void
    {
        $app = $this->loggingApplication();

        $request = $this->request();
        $request->session = new Session(new class extends ArrayStore {
            public function write(string $id, array $data): string
            {
                throw new RuntimeException('store down');
            }
        }, ['cookie' => self::COOKIE, 'lifetime' => 120, 'stateless' => [], 'lottery' => [0, 100]]);
        $request->session->attach($request);

        $redirect = new Response('', 302);
        $router = $this->createMock(Router::class);
        $router->method('resolve')->willReturnCallback(function () use ($request, $redirect) {
            $request->session()->put('a', 1);

            throw (new HttpResponseException('invalid', 422))->setResponse($redirect);
        });
        $app->router = $router;

        try {
            $app->dispatch($request);
            $this->fail('The original exception must propagate.');
        } catch (HttpResponseException) {
        }

        $this->assertSame(['store down'], $app->logged, 'The lost save was not reported.');
    }

    public function testASessionChangedBeforeAnHttpErrorIsStillSavedWhenTheErrorPageCannotRender(): void
    {
        // Rendering the page needs a bootstrapped application, so here it fails.
        // The session is saved around it, and no output buffer is left behind.
        $id = $this->seed();
        $request = $this->requestOn($id);

        $this->routeTo(function () use ($request) {
            $request->session()->put('flash', 'kept');

            throw new HttpException(403, 'Forbidden');
        });

        $level = ob_get_level();

        // In the CLI, PHP warns when a status is set after another test already
        // sent one in the same process. That is noise here, not the subject.
        set_error_handler(static fn () => true);

        try {
            $this->app->dispatch($request);
        } catch (\Throwable) {
        } finally {
            restore_error_handler();
        }

        $this->assertSame($level, ob_get_level(), 'An output buffer was left open.');
        $this->assertSame('kept', $this->store->read($id)['data']['flash'] ?? null);
    }

    public function testAChangeMadeWhileAStreamedResponseIsSentIsNotSaved(): void
    {
        // The session is saved just before the response is sent, because that is
        // when its cookie is added, so what a streamed response writes while it
        // is produced comes too late. This documents that limit.
        $id = $this->seed();
        $request = $this->requestOn($id);

        $this->routeTo(function () use ($request) {
            return new \Phaseolies\Http\Response\Stream\StreamedResponse(function () use ($request) {
                $request->session()->put('streamed', 'late');

                echo 'chunk';
            });
        });

        ob_start();

        try {
            $this->app->dispatch($request);
        } finally {
            ob_end_clean();
        }

        $this->assertArrayNotHasKey('streamed', $this->store->read($id)['data']);
    }
}
