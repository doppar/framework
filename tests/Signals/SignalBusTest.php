<?php

namespace Tests\Unit\Signals;

use PHPUnit\Framework\TestCase;
use Phaseolies\Signals\SignalBus;

interface TracksOrders {}

class OrderSignal implements TracksOrders
{
    public function __construct(public string $id = 'o-1') {}
}

class ShippedOrderSignal extends OrderSignal {}

class InvokableListener
{
    public static array $seen = [];

    public function __invoke(OrderSignal $signal): void
    {
        self::$seen[] = ['invoke', $signal->id];
    }

    public function handle(OrderSignal $signal): void
    {
        self::$seen[] = ['handle', $signal->id];
    }
}

final class SignalBusTest extends TestCase
{
    private SignalBus $bus;

    protected function setUp(): void
    {
        $this->bus = new SignalBus();
        InvokableListener::$seen = [];
    }

    public function testHigherPriorityListenersRunFirst(): void
    {
        $order = [];

        $this->bus->on(OrderSignal::class, function () use (&$order) { $order[] = 'low'; }, -5);
        $this->bus->on(OrderSignal::class, function () use (&$order) { $order[] = 'high'; }, 10);
        $this->bus->on(OrderSignal::class, function () use (&$order) { $order[] = 'normal'; });

        $this->bus->emit(new OrderSignal());

        $this->assertSame(['high', 'normal', 'low'], $order);
    }

    public function testEqualPrioritiesRunInRegistrationOrder(): void
    {
        $order = [];

        foreach (['a', 'b', 'c'] as $name) {
            $this->bus->on(OrderSignal::class, function () use (&$order, $name) { $order[] = $name; }, 3);
        }

        $this->bus->emit(new OrderSignal());

        $this->assertSame(['a', 'b', 'c'], $order);
    }

    public function testListenersOfParentClassesAndInterfacesReceiveTheSignal(): void
    {
        $order = [];

        $this->bus->on(ShippedOrderSignal::class, function () use (&$order) { $order[] = 'class'; }, 1);
        $this->bus->on(OrderSignal::class, function () use (&$order) { $order[] = 'parent'; }, 2);
        $this->bus->on(TracksOrders::class, function () use (&$order) { $order[] = 'interface'; }, 3);

        $this->bus->emit(new ShippedOrderSignal());
        $this->assertSame(['interface', 'parent', 'class'], $order);

        $order = [];
        $this->bus->emit(new OrderSignal());
        $this->assertSame(['interface', 'parent'], $order);
    }

    public function testListenerRegisteredLaterIsPickedUpAfterTheFirstEmit(): void
    {
        $order = [];

        $this->bus->on(OrderSignal::class, function () use (&$order) { $order[] = 'first'; });
        $this->bus->emit(new OrderSignal());

        $this->bus->on(OrderSignal::class, function () use (&$order) { $order[] = 'second'; });
        $this->bus->emit(new OrderSignal());

        $this->assertSame(['first', 'first', 'second'], $order);
    }

    public function testInvokableClassAndClassMethodListenersAreResolved(): void
    {
        $this->bus->on(OrderSignal::class, InvokableListener::class, 2);
        $this->bus->on(OrderSignal::class, [InvokableListener::class, 'handle'], 1);

        $this->bus->emit(new OrderSignal('o-9'));

        $this->assertSame([['invoke', 'o-9'], ['handle', 'o-9']], InvokableListener::$seen);
    }

    public function testObjectMethodListenerIsCalled(): void
    {
        $listener = new InvokableListener();
        $this->bus->on(OrderSignal::class, [$listener, 'handle']);

        $this->bus->emit(new OrderSignal('o-2'));

        $this->assertSame([['handle', 'o-2']], InvokableListener::$seen);
    }

    public function testInvalidListenersAreRejectedWhenRegistered(): void
    {
        foreach (['not_a_function_or_class', [InvokableListener::class, 'missing'], [1, 2], ['Nope\\Missing', 'x']] as $bad) {
            try {
                $this->bus->on(OrderSignal::class, $bad);
                $this->fail('Expected an InvalidArgumentException for ' . json_encode($bad));
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('signal listener', $e->getMessage());
            }
        }
    }

    public function testImmediateEmitPropagatesTheListenerExceptionAndStops(): void
    {
        $ran = [];

        $this->bus->on(OrderSignal::class, function () { throw new \RuntimeException('listener failed'); }, 2);
        $this->bus->on(OrderSignal::class, function () use (&$ran) { $ran[] = 'later'; }, 1);

        try {
            $this->bus->emit(new OrderSignal());
            $this->fail('The listener exception should propagate.');
        } catch (\RuntimeException $e) {
            $this->assertSame('listener failed', $e->getMessage());
        }

        $this->assertSame([], $ran);
    }

    public function testEmitWithoutListenersIsANoop(): void
    {
        $this->bus->emit(new OrderSignal());
        $this->assertFalse($this->bus->hasListeners(OrderSignal::class));
    }

    public function testForgetRemovesListeners(): void
    {
        $ran = 0;
        $this->bus->on(OrderSignal::class, function () use (&$ran) { $ran++; });
        $this->bus->on(TracksOrders::class, function () use (&$ran) { $ran++; });

        $this->assertTrue($this->bus->hasListeners(new OrderSignal()));

        $this->bus->forget(OrderSignal::class);
        $this->bus->emit(new OrderSignal());
        $this->assertSame(1, $ran);

        $this->bus->forget();
        $this->bus->emit(new OrderSignal());
        $this->assertSame(1, $ran);
        $this->assertFalse($this->bus->hasListeners(OrderSignal::class));
    }

    public function testLeadingBackslashInSignalNamesIsIgnored(): void
    {
        $ran = 0;
        $this->bus->on('\\' . OrderSignal::class, function () use (&$ran) { $ran++; });

        $this->bus->emit(new OrderSignal());

        $this->assertSame(1, $ran);
    }

    public function testOutsideATransactionEmitOnSettleDeliversImmediately(): void
    {
        $ran = 0;
        $this->bus->on(OrderSignal::class, function () use (&$ran) { $ran++; });

        $held = $this->bus->emitOnSettle(new OrderSignal(), 'no-such-connection');

        $this->assertFalse($held);
        $this->assertSame(1, $ran);
    }
}
