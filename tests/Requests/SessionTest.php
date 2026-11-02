<?php

namespace Tests\Unit\Requests;

use Phaseolies\Session\Stores\ArrayStore;
use Phaseolies\Support\Session;
use Phaseolies\Session\Contracts\SessionInterface;
use Phaseolies\Application;
use Phaseolies\DI\Container;
use Phaseolies\Launchers\FacadeLauncher;
use ReflectionClass;
use PHPUnit\Framework\TestCase;

class SessionTest extends TestCase
{
    protected Session $session;
    protected array $mockSession = [];

    protected function setUp(): void
    {
        $this->session = new Session(new ArrayStore(), [
            'cookie' => 'test_session',
            'lifetime' => 120,
            'stateless' => [],
            'lottery' => [0, 100],
        ]);
    }

    public function testConstructorInitializesAnEmptySession()
    {
        $this->assertSame([], $this->session->all());
    }

    public function testConstructorDoesNotTouchTheStoreOrConfig(): void
    {
        // No arguments, no application: creating a session must have no side
        // effects until it is used.
        $this->assertInstanceOf(Session::class, new Session());
    }

    public function testSessionImplementationConformsToTheSessionContract(): void
    {
        $this->assertInstanceOf(SessionInterface::class, $this->session);
    }

    public function testApplicationBindsTheSessionContractAndLegacyServiceNameToTheSameSingleton(): void
    {
        $app = $this->getMockBuilder(Application::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $containerConstructor = (new ReflectionClass(Container::class))->getConstructor();
        $containerConstructor->invoke($app);

        (new FacadeLauncher($app))->register();

        $contractSession = $app->get(SessionInterface::class);
        $legacySession = $app->get('session');

        $this->assertInstanceOf(SessionInterface::class, $contractSession);
        $this->assertInstanceOf(Session::class, $contractSession);
        $this->assertSame($contractSession, $legacySession);

    }

    public function testSessionIsResetBetweenRequestsAndOverridableByLegacyKey(): void
    {
        $app = $this->getMockBuilder(Application::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        (new ReflectionClass(Container::class))->getConstructor()->invoke($app);
        (new FacadeLauncher($app))->register();
        $app->snapshotBootBindings();

        $first = $app->get('session');

        // Same sweep Application::cleanupRequestScopedServices() performs.
        $app->forgetResolved('session');
        $app->forgetRequestScopedInstances();

        $second = $app->get('session');
        $this->assertNotSame($first, $second);

        $app->forgetResolved('session');
        $this->assertNotSame($second, $app->get('session'));

        $fake = new Session();
        $app->instance('session', $fake);
        $this->assertSame($fake, $app->get(SessionInterface::class));
    }

    public function testPutAndGetSingleValue()
    {
        $this->session->put('test_key', 'test_value');
        $this->assertEquals('test_value', $this->session->get('test_key'));
        $this->assertArrayHasKey('test_key', $this->session->all());
    }

    public function testPutAndGetArray()
    {
        $data = [
            'key1' => 'value1',
            'key2' => 'value2'
        ];
        $this->session->put($data);

        $this->assertEquals('value1', $this->session->get('key1'));
        $this->assertEquals('value2', $this->session->get('key2'));
    }

    public function testGetWithDefaultValue()
    {
        $this->assertEquals('default', $this->session->get('non_existent', 'default'));
    }

    public function testHas()
    {
        $this->assertFalse($this->session->has('test_key'));

        $this->session->put('test_key', 'value');
        $this->assertTrue($this->session->has('test_key'));
    }

    public function testForget()
    {
        $this->session->put('test_key', 'value');
        $this->assertTrue($this->session->has('test_key'));

        $this->session->forget('test_key');
        $this->assertFalse($this->session->has('test_key'));
    }

    public function testPull()
    {
        $this->session->put('test_key', 'value');
        $value = $this->session->pull('test_key');

        $this->assertEquals('value', $value);
        $this->assertFalse($this->session->has('test_key'));
    }

    public function testFlush()
    {
        $this->session->put('key1', 'value1');
        $this->session->put('key2', 'value2');

        $this->session->flush();
        $this->assertEmpty($this->session->all());
    }

    public function testAll()
    {
        $data = [
            'key1' => 'value1',
            'key2' => 'value2'
        ];
        $this->session->put($data);

        $this->assertEquals($data, $this->session->all());
    }

    public function testPeekOperations()
    {
        $this->session->putPeek('peek_key', 'peek_value');
        $this->assertEquals('peek_value', $this->session->getPeek('peek_key'));
        $this->assertFalse($this->session->has('peek_key')); // Shouldn't be in main data

        $this->session->flushPeek();
        $this->assertNull($this->session->getPeek('peek_key'));
    }

    public function testSetAndGetId()
    {
        $newId = bin2hex(random_bytes(20));
        $this->session->setId($newId);

        $this->assertEquals($newId, $this->session->getId());
    }

    public function testSetIdIgnoresAnIdThatWasNotGeneratedByTheFramework()
    {
        $before = $this->session->getId();
        $this->session->setId('test_session_id');

        $this->assertSame($before, $this->session->getId());
    }

    // public function testDestroy()
    // {
    //     $this->session->put('key1', 'value1');
    //     $this->session->putPeek('peek_key', 'peek_value');

    //     $this->session->destroy();

    //     $this->assertEmpty($this->session->all());
    //     $this->assertEmpty($this->mockSession);
    // }

    public function testToken()
    {
        $token = $this->session->token();

        $this->assertSame(32, strlen($token));
        $this->assertSame($token, $this->session->token());

        $this->session->put('_token', 'test_token');
        $this->assertEquals('test_token', $this->session->token());
    }

    public function testFlash()
    {
        $this->session->flash('flash_key', 'flash_value');
        $this->assertEquals('flash_value', $this->session->get('flash_key'));
    }
}
