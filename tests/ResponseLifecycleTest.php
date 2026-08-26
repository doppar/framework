<?php

namespace Tests\Unit;

require_once __DIR__ . '/Support/MockContainer.php';

use Mockery;
use Phaseolies\Application;
use Phaseolies\DI\Container;
use Phaseolies\Error\JsonErrorRenderer;
use Phaseolies\Http\Controllers\Controller;
use Phaseolies\Http\Exceptions\HttpResponseException;
use Phaseolies\Http\Request;
use Phaseolies\Http\Response;
use Phaseolies\Http\Response\RedirectResponse;
use Phaseolies\Http\Support\RequestAbortion;
use Phaseolies\Support\Router;
use Phaseolies\Support\Session;
use Phaseolies\Translation\FileLoader;
use Phaseolies\Translation\Translator;
use PHPUnit\Framework\TestCase;
use Tests\Support\Kernel;
use Tests\Support\MockContainer;

if (!class_exists('App\Http\Kernel')) {
    class_alias(Kernel::class, 'App\Http\Kernel');
}

class LifecycleRouteRequestStub extends Request
{
    private string $testMethod;
    private string $testPath;
    private string $testHost;
    private array $testRouteParams = [];

    public function __construct(string $method, string $path, string $host)
    {
        $this->testMethod = strtoupper($method);
        $this->testPath = $path;
        $this->testHost = $host;
    }

    public function getMethod(): string
    {
        return $this->testMethod;
    }

    public function getPath(): string
    {
        return $this->testPath;
    }

    public function getHost(): string
    {
        return $this->testHost;
    }

    public function getRouteParams(): array
    {
        return $this->testRouteParams;
    }

    public function setRouteParams(array $params): self
    {
        $this->testRouteParams = $params;

        return $this;
    }
}

class LifecycleTestableRouter extends Router
{
    public function handle(Request $request, \Closure $handler): Response
    {
        return $handler($request);
    }
}

class ResponseLifecycleTest extends TestCase
{
    private MockContainer $container;
    private string $translationPath;
    private array $originalServer;
    private array $originalGet;
    private array $originalPost;
    private array $originalSession;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalServer = $_SERVER ?? [];
        $this->originalGet = $_GET ?? [];
        $this->originalPost = $_POST ?? [];
        $this->originalSession = $_SESSION ?? [];

        $_SERVER = [];
        $_GET = [];
        $_POST = [];
        $_SESSION = [];

        $this->container = new MockContainer();
        Container::setInstance($this->container);

        $this->translationPath = sys_get_temp_dir() . '/phaseolies_response_lifecycle_lang_' . uniqid();
        mkdir($this->translationPath . '/en', 0777, true);
        file_put_contents($this->translationPath . '/en/validation.php', <<<'PHP'
<?php

return [
    'default' => 'Validation failed.',
    'rate_limit' => ['message' => 'Too many requests.'],
    'unauthorized' => ['message' => 'Unauthorized.'],
    'required' => 'The :attribute field is required.',
];
PHP);

        $this->container->bind('translator', fn() => new Translator(new FileLoader($this->translationPath), 'en'));
        $this->container->bind('session', Session::class);
        $this->container->instance('session', new Session());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        $this->deleteDir($this->translationPath);

        $_SERVER = $this->originalServer;
        $_GET = $this->originalGet;
        $_POST = $this->originalPost;
        $_SESSION = $this->originalSession;

        parent::tearDown();
    }

    public function testControllerReturnAndResponseHelperProduceSameJsonLifecycleOutput(): void
    {
        $payload = ['framework' => 'Doppar', 'version' => 3];
        $app = $this->createStub(Application::class);
        $router = new LifecycleTestableRouter($app);

        $router->get('/payload', fn() => $payload);

        $request = new LifecycleRouteRequestStub('GET', '/payload', 'localhost');

        $routeResponse = $router->resolve($app, $request);
        $helperResponse = response($payload);

        $this->assertSame(200, $routeResponse->getStatusCode());
        $this->assertSame(200, $helperResponse->getStatusCode());
        $this->assertSame('application/json', $routeResponse->headers->get('Content-Type'));
        $this->assertSame('application/json', $helperResponse->headers->get('Content-Type'));
        $this->assertSame($payload, $routeResponse->getOriginal());
        $this->assertSame($payload, $helperResponse->getOriginal());
        $this->assertSame($helperResponse->getBody(), $routeResponse->getBody());
    }

    public function testHeadPreparationStripsBodyForControllerAndHelperJsonResponses(): void
    {
        $payload = ['framework' => 'Doppar'];
        $app = $this->createStub(Application::class);
        $router = new LifecycleTestableRouter($app);

        $router->get('/payload', fn() => $payload);

        $routeRequest = new LifecycleRouteRequestStub('GET', '/payload', 'localhost');
        $routeResponse = $router->resolve($app, $routeRequest);
        $helperResponse = response($payload);

        $_SERVER['REQUEST_METHOD'] = 'HEAD';
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $headRequest = new Request();

        $routeResponse->prepare($headRequest);
        $helperResponse->prepare($headRequest);

        $this->assertNull($routeResponse->getBody());
        $this->assertNull($helperResponse->getBody());
        $this->assertSame('application/json', $routeResponse->headers->get('Content-Type'));
        $this->assertSame('application/json', $helperResponse->headers->get('Content-Type'));
    }

    public function testAbortJsonBranchReturnsPreparedJsonResponse(): void
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('isAjax')->andReturn(true);
        $request->shouldReceive('isApiRequest')->andReturn(false);
        $this->container->instance('request', $request);

        $abortion = new RequestAbortion();

        try {
            $abortion->abort(403, 'Forbidden');
            $this->fail('Expected HttpResponseException was not thrown.');
        } catch (HttpResponseException $exception) {
            $response = $exception->getResponse();
            $payload = json_decode($response?->getBody() ?? '', true);

            $this->assertNotNull($response);
            $this->assertSame(403, $response->getStatusCode());
            $this->assertSame('application/json', $response->headers->get('Content-Type'));
            $this->assertSame($payload, $response->getOriginal());
            $this->assertSame('Forbidden', $payload['message']);
            $this->assertIsArray($payload['errors']);
        }
    }

    public function testValidationRedirectBranchReturnsPreparedRedirectResponse(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/submit';
        $_SERVER['HTTP_REFERER'] = '/form';
        $_POST = [];

        $request = new Request();
        $this->container->instance('request', $request);

        try {
            $request->sanitize(['email' => 'required']);
            $this->fail('Expected HttpResponseException was not thrown.');
        } catch (HttpResponseException $exception) {
            $response = $exception->getResponse();

            $this->assertInstanceOf(RedirectResponse::class, $response);
            $this->assertSame(302, $response->getStatusCode());
            $this->assertSame('/form', $response->headers->get('Location'));
            $this->assertSame('/form', $response->getOriginal());
            $this->assertArrayHasKey('email', $this->container->get('session')->get('errors'));
            $this->assertSame([], $this->container->get('session')->get('input'));
        }
    }

    public function testViewJsonAndRedirectHelpersDoNotReuseStaleSharedInstances(): void
    {
        $sharedResponse = new Response('stale body', 202, ['X-Leaked' => 'yes']);
        $sharedRedirect = (new RedirectResponse())->to('/old', 302, ['X-Redirect-Leaked' => 'yes']);
        $controller = $this->createMock(Controller::class);

        $controller->expects($this->once())
            ->method('render')
            ->with('demo', ['name' => 'Doppar'], true)
            ->willReturn('<h1>Doppar</h1>');

        $this->container->instance('response', $sharedResponse);
        $this->container->instance('redirect', $sharedRedirect);
        $this->container->instance(Controller::class, $controller);

        $viewResponse = view('demo', ['name' => 'Doppar']);
        $jsonResponse = response(['fresh' => true]);
        $redirectResponse = redirect('/fresh');

        $this->assertNotSame($sharedResponse, $viewResponse);
        $this->assertNotSame($sharedResponse, $jsonResponse);
        $this->assertNotSame($sharedRedirect, $redirectResponse);

        $this->assertNull($viewResponse->headers->get('X-Leaked'));
        $this->assertNull($jsonResponse->headers->get('X-Leaked'));
        $this->assertNull($redirectResponse->headers->get('X-Redirect-Leaked'));

        $this->assertSame([
            'view' => 'demo',
            'data' => ['name' => 'Doppar'],
        ], $viewResponse->getOriginal());
        $this->assertSame(['fresh' => true], $jsonResponse->getOriginal());
        $this->assertSame('/fresh', $redirectResponse->getOriginal());
        $this->assertSame('/fresh', $redirectResponse->headers->get('Location'));
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        @rmdir($dir);
    }
}
