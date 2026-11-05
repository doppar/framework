<?php

namespace Tests\Unit;

use Phaseolies\Application;
use Phaseolies\DI\Container;
use Phaseolies\Launchers\FacadeLauncher;
use Phaseolies\Support\Mail\Contracts\MailInterface;
use Phaseolies\Support\Mail\MailService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class MailServiceTest extends TestCase
{
    public function testMailServiceImplementationConformsToTheMailContract(): void
    {
        $this->assertInstanceOf(MailInterface::class, new MailService());
    }

    public function testApplicationBindsTheMailContractAndLegacyServiceNameToTheSameSingleton(): void
    {
        $app = $this->getMockBuilder(Application::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $containerConstructor = (new ReflectionClass(Container::class))->getConstructor();
        $containerConstructor->invoke($app);

        (new FacadeLauncher($app))->register();

        $contractMail = $app->get(MailInterface::class);
        $legacyMail = $app->get('mail');

        $this->assertInstanceOf(MailInterface::class, $contractMail);
        $this->assertInstanceOf(MailService::class, $contractMail);
        $this->assertSame($contractMail, $legacyMail);
        $this->assertSame($contractMail, $contractMail->to('mail@example.com'));
    }
}
