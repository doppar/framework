<?php

namespace Tests\Unit\Http;

require_once __DIR__ . '/../Support/MockContainer.php';

use Phaseolies\Database\Entity\Model;
use Phaseolies\DI\Container;
use Phaseolies\Error\Handlers\JsonErrorHandler;
use Phaseolies\Http\Exceptions\HttpException;
use Phaseolies\Http\Exceptions\TooManyRequestsHttpException;
use Phaseolies\Http\Request;
use Phaseolies\Http\Response;
use Phaseolies\Http\Response\RedirectResponse;
use Phaseolies\Http\Response\Stream\StreamedJsonResponse;
use Phaseolies\Support\Collection;
use Phaseolies\Support\StringService;
use Phaseolies\Translation\FileLoader;
use Phaseolies\Translation\Translator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use Tests\Support\MockContainer;

class HiddenFieldsPost extends Model
{
    public function __construct()
    {
    }

    public function jsonSerialize(): array
    {
        return ['only' => 'public'];
    }

    public function toArray(): array
    {
        return ['secret' => 'hidden'];
    }
}

class OverridingUser extends Model
{
    public function __construct()
    {
    }

    public function jsonSerialize(): array
    {
        $data = $this->toArray();
        unset($data['password']);

        return $data;
    }
}

class ResponseEdgeCasesTest extends TestCase
{
    private MockContainer $container;

    protected function setUp(): void
    {
        $this->container = new MockContainer();
        Container::setInstance($this->container);
        $this->container->bind('str', StringService::class);
    }

    protected function tearDown(): void
    {
        Container::forgetInstance();
    }

    private function request(array $server = [], array $post = []): Request
    {
        $_GET = $_COOKIE = $_FILES = [];
        $_POST = $post;
        $_SERVER = $server + ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'HTTP_HOST' => 'example.com', 'REMOTE_ADDR' => '127.0.0.1', 'SCRIPT_NAME' => '/index.php', 'SERVER_NAME' => 'example.com', 'SERVER_PORT' => '80'];

        $request = Request::createFromGlobals();
        $this->container->bind('request', fn() => $request);

        return $request;
    }

    public function testBackUsesTheFallbackForABlankReferer(): void
    {
        $this->request(['HTTP_REFERER' => '']);

        $response = (new RedirectResponse('/'))->back(302, [], '/home');

        $this->assertSame('/home', $response->headers->get('Location'));
    }

    public function testBackStillUsesTheReferer(): void
    {
        $this->request(['HTTP_REFERER' => 'https://example.com/form']);

        $response = (new RedirectResponse('/'))->back(302, [], '/home');

        $this->assertSame('https://example.com/form', $response->headers->get('Location'));
    }

    public function testAnyIsNotAValidRequestMethod(): void
    {
        $request = $this->request(['REQUEST_METHOD' => 'POST'], ['_method' => 'ANY']);
        $this->assertNotNull($request->getMalformedRequestError());

        $request = $this->request(['REQUEST_METHOD' => 'ANY']);
        $this->assertNotNull($request->getMalformedRequestError());
    }

    public static function statusProvider(): array
    {
        return [
            'small code' => [new RuntimeException('x', 2), 500],
            'no code' => [new RuntimeException('x'), 500],
            'database driver code' => [new RuntimeException('x', 1045), 500],
            'client error code' => [new RuntimeException('x', 422), 422],
            'server error code' => [new RuntimeException('x', 503), 503],
            'http exception' => [new HttpException(404, 'x'), 404],
        ];
    }

    #[DataProvider('statusProvider')]
    public function testJsonErrorHandlerOnlyUsesHttpStatuses(\Throwable $exception, int $expected): void
    {
        $method = new ReflectionMethod(JsonErrorHandler::class, 'resolveStatusCode');

        $this->assertSame($expected, $method->invoke(new JsonErrorHandler(), $exception));
    }

    public function testJsonErrorHandlerKeepsTheHttpExceptionHeaders(): void
    {
        $lang = sys_get_temp_dir() . '/doppar-json-error-lang-' . bin2hex(random_bytes(5));
        mkdir($lang . '/en', 0755, true);
        file_put_contents($lang . '/en/validation.php', "<?php return ['default' => 'x', 'rate_limit' => ['message' => 'x'], 'unauthorized' => ['message' => 'x']];");
        $this->container->bind('translator', fn() => new Translator(new FileLoader($lang), 'en'));


        $response = (new ReflectionMethod(JsonErrorHandler::class, 'buildResponse'))
            ->invoke(new JsonErrorHandler(), new TooManyRequestsHttpException(60, 'Slow down'));

        unlink($lang . '/en/validation.php');
        rmdir($lang . '/en');
        rmdir($lang);

        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame('60', $response->headers->get('Retry-After'));
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
    }

    public function testJsonResponseUsesRelationOverrideWhenTheParentCallsToArray(): void
    {
        $json = (new ReflectionMethod(Response::class, 'encodeJsonContent'))->invoke(new Response(), $this->userWithRelations());

        $this->assertSame(['posts' => [['only' => 'public']], 'post' => ['only' => 'public']], json_decode($json, true));
    }

    /**
     * @return array<string, array{callable(OverridingUser): iterable<mixed>, callable(array): mixed}>
     */
    public static function streamedDataProvider(): array
    {
        return [
            'model in an array' => [fn(OverridingUser $user) => ['user' => $user], fn(array $json) => $json['user']],
            'model in a generator' => [
                function (OverridingUser $user) {
                    yield $user;
                },
                fn(array $json) => $json[0],
            ],
        ];
    }

    #[DataProvider('streamedDataProvider')]
    public function testStreamedJsonResponseUsesRelationOverrideWhenTheParentCallsToArray(callable $data, callable $user): void
    {
        ob_start();

        try {
            (new StreamedJsonResponse($data($this->userWithRelations())))->sendContent();
        } finally {
            $json = ob_get_clean();
        }

        $this->assertSame(
            ['posts' => [['only' => 'public']], 'post' => ['only' => 'public']],
            $user(json_decode($json, true))
        );
    }

    public function testStreamedJsonResponseGeneratorSeesPlainToArray(): void
    {
        $user = $this->userWithRelations();
        $seen = null;

        $data = (function () use ($user, &$seen) {
            $seen = $user->toArray()['post'];

            yield $user;
        })();

        ob_start();
        (new StreamedJsonResponse($data))->sendContent();
        ob_end_clean();

        $this->assertSame(['secret' => 'hidden'], $seen);
    }

    public function testStreamedJsonResponseLeavesToArrayAloneAfterSending(): void
    {
        $user = $this->userWithRelations();

        ob_start();
        (new StreamedJsonResponse(['user' => $user]))->sendContent();
        ob_end_clean();

        $this->assertSame(['secret' => 'hidden'], $user->toArray()['post']);
    }

    private function userWithRelations(): OverridingUser
    {
        $user = new OverridingUser();

        foreach ((new \ReflectionClass(Model::class))->getProperties() as $property) {
            if (!$property->isStatic() && !$property->isInitialized($user) && $property->getType()?->allowsNull()) {
                $property->setValue($user, null);
            }
        }

        (new ReflectionProperty(Model::class, 'relations'))->setValue($user, [
            'posts' => new Collection(HiddenFieldsPost::class, [new HiddenFieldsPost()]),
            'post' => new HiddenFieldsPost(),
        ]);

        return $user;
    }
}
