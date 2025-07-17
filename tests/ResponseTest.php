<?php

namespace Tests\Unit;

use Phaseolies\Http\Response;
use Phaseolies\Http\Request;
use Phaseolies\Http\Response\ResponseHeaderBag;
use Phaseolies\Http\Exceptions\HttpException;
use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase
{
    private Response $response;

    protected function setUp(): void
    {
        $this->response = new Response();
    }

    public function testInitialization()
    {
        $this->assertEquals(200, $this->response->getStatusCode());
        $this->assertInstanceOf(ResponseHeaderBag::class, $this->response->headers);
        $this->assertEquals('1.0', $this->response->getProtocolVersion());
    }

    public function testConstructorWithParameters()
    {
        $response = new Response('Test body', 404, ['X-Test' => 'Doppar']);
        $this->assertEquals('Test body', $response->getBody());
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Doppar', $response->headers->get('X-Test'));
    }

    public function testSetAndGetBody()
    {
        $this->response->setBody('New body');
        $this->assertEquals('New body', $this->response->getBody());
    }

    public function testSetStatusCode()
    {
        $this->response->setStatusCode(201);
        $this->assertEquals(201, $this->response->getStatusCode());

        $this->response->setStatusCode(404, 'Custom Not Found');
        $this->assertEquals(404, $this->response->getStatusCode());
    }

    public function testSetHeader()
    {
        $this->response->setHeader('X-Test', 'Doppar');
        $this->assertEquals('Doppar', $this->response->headers->get('X-Test'));
    }

    public function testWithHeaders()
    {
        $this->response->withHeaders(['X-Test' => 'Value', 'X-Another' => 'Test']);
        $this->assertEquals('Value', $this->response->headers->get('X-Test'));
        $this->assertEquals('Test', $this->response->headers->get('X-Another'));
    }

    public function testWithException()
    {
        $exception = new \RuntimeException('Test');
        $this->response->withException($exception);
        $this->assertSame($exception, $this->response->exception);
    }

    public function testIsInformational()
    {
        $this->assertFalse($this->response->isInformational());
        $this->response->setStatusCode(100);
        $this->assertTrue($this->response->isInformational());
    }

    public function testIsEmpty()
    {
        $this->assertFalse($this->response->isEmpty());
        $this->response->setStatusCode(204);
        $this->assertTrue($this->response->isEmpty());
    }

    public function testSetProtocolVersion()
    {
        $this->response->setProtocolVersion('1.1');
        $this->assertEquals('1.1', $this->response->getProtocolVersion());
    }

    public function testPrepare()
    {
        $request = new Request();
        $request->server->set('SERVER_PROTOCOL', 'HTTP/1.1');

        $this->response->setBody('Test');
        $this->response->prepare($request);

        $this->assertEquals('1.1', $this->response->getProtocolVersion());
        $this->assertStringContainsString('text/html', $this->response->headers->get('Content-Type'));
    }

    public function testSendContent()
    {
        $this->response->setBody('Test content');
        ob_start();
        $this->response->sendContent();
        $output = ob_get_clean();

        $this->assertEquals('Test content', $output);
    }

    public function testSend()
    {
        $this->response->setBody('Test send');
        ob_start();
        $this->response->send();
        $output = ob_get_clean();

        $this->assertEquals('Test send', $output);
    }

    public function testRender()
    {
        $this->response->setBody('Render test');
        $this->assertEquals('Render test', $this->response->render());
    }

    public function testJson()
    {
        $jsonResponse = $this->response->json(['test' => 'value'], 201);
        $this->assertInstanceOf(Response\JsonResponse::class, $jsonResponse);
    }

    public function testText()
    {
        $response = $this->response->text('Plain text', 200, ['X-Test' => 'Value']);
        $this->assertEquals('Plain text', $response->getBody());
        $this->assertEquals('Value', $response->headers->get('X-Test'));
        $this->assertEquals('text/plain', $response->headers->get('Content-Type'));
    }

    public function testIsNotCacheable()
    {
        $this->response->setStatusCode(500);
        $this->assertFalse($this->response->isCacheable());
    }

    public function testIsFresh()
    {
        $this->assertFalse($this->response->isFresh());

        $this->response->headers->set('Cache-Control', 'max-age=60');
        $this->assertTrue($this->response->isFresh());
    }

    public function testIsValidateable()
    {
        $this->assertFalse($this->response->isValidateable());

        $this->response->headers->set('ETag', 'test');
        $this->assertTrue($this->response->isValidateable());
    }

    public function testSetPrivateAndPublic()
    {
        $this->response->setPrivate();
        $this->assertStringContainsString('private', $this->response->headers->get('Cache-Control'));

        $this->response->setPublic();
        $this->assertStringContainsString('public', $this->response->headers->get('Cache-Control'));
    }

    public function testSetImmutable()
    {
        $this->response->setImmutable(true);
        $this->assertTrue($this->response->isImmutable());

        $this->response->setImmutable(false);
        $this->assertFalse($this->response->isImmutable());
    }

    public function testMustRevalidate()
    {
        $this->assertFalse($this->response->mustRevalidate());

        $this->response->headers->set('Cache-Control', 'must-revalidate');
        $this->assertTrue($this->response->mustRevalidate());
    }

    public function testSetAndGetDate()
    {
        $date = new \DateTime('2023-01-01');
        $this->response->setDate($date);
        $this->assertEquals('Sun, 01 Jan 2023 00:00:00 GMT', $this->response->headers->get('Date'));
    }

    public function testGetAge()
    {
        $date = new \DateTime('-1 hour');
        $this->response->setDate($date);
        $this->assertGreaterThanOrEqual(3600, $this->response->getAge());
    }

    public function testSetNotModified()
    {
        $this->response->setNotModified();
        $this->assertEquals(304, $this->response->getStatusCode());
        $this->response->setBody("");
        $this->assertEmpty($this->response->getBody());
        $this->assertNull($this->response->headers->get('Content-Type'));
    }

    public function testIsNotModified()
    {
        $request = new Request();
        $this->assertFalse($this->response->isNotModified($request));

        $lastModified = new \DateTime('-1 hour');
        $this->response->setLastModified($lastModified);
        $request->headers->set('If-Modified-Since', $lastModified->format('D, d M Y H:i:s') . ' GMT');
        $this->assertTrue($this->response->isNotModified($request));
    }

    public function testStatusCodeTexts()
    {
        $this->assertEquals('OK', $this->response->getStatusCodeText(200));
        $this->assertEquals('Not Found', $this->response->getStatusCodeText(404));
        $this->assertEquals('Unknown Status', $this->response->getStatusCodeText(999));
    }

    public function testCloseOutputBuffers()
    {
        $statusBefore = ob_get_status();
        $this->assertIsArray($statusBefore);
        $this->assertNotEmpty($statusBefore);
    }

    public function testStatusCheckMethods()
    {
        $this->assertTrue($this->response->isSuccessful());
        $this->assertTrue($this->response->isOk());

        $this->response->setStatusCode(404);
        $this->assertTrue($this->response->isClientError());
        $this->assertTrue($this->response->isNotFound());

        $this->response->setStatusCode(500);
        $this->assertTrue($this->response->isServerError());

        $this->response->setStatusCode(301);
        $this->assertTrue($this->response->isRedirection());
    }

    public function testSetContentSafe()
    {
        $this->response->setContentSafe(true);
        $this->assertEquals('safe', $this->response->headers->get('Preference-Applied'));
        $this->assertContains('Prefer', $this->response->getVary());
    }

    public function testToString()
    {
        $this->response->setBody('Test');
        $string = (string)$this->response;
        $this->assertStringContainsString('HTTP/1.0 200 OK', $string);
        $this->assertStringContainsString('Test', $string);
    }

    public function testClone()
    {
        $this->response->setHeader('X-Test', 'Value');
        $clone = clone $this->response;

        $this->assertEquals('Value', $clone->headers->get('X-Test'));
        $this->assertNotSame($this->response->headers, $clone->headers);
    }
}
