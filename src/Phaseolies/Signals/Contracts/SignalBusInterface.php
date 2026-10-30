<?php

namespace Phaseolies\Signals\Contracts;

interface SignalBusInterface
{
    /**
     * Register a listener for a signal class or interface.
     *
     * @param string $signal
     * @param callable|array|string $listener
     * @param int $priority
     * @return self
     * @throws \InvalidArgumentException
     */
    public function on(string $signal, callable|array|string $listener, int $priority = 0): self;

    /**
     * Remove every listener of a signal, or all listeners when none is given.
     *
     * @param string|null $signal
     * @return self
     */
    public function forget(?string $signal = null): self;

    /**
     * Determine if any listener would receive the given signal.
     *
     * @param object|string $signal
     * @return bool
     */
    public function hasListeners(object|string $signal): bool;

    /**
     * Emit a signal.
     *
     * @param object $signal
     * @return void
     */
    public function emit(object $signal): void;

    /**
     * Emit a signal right away, ignoring #[DeliverOnSettle] and any open transaction.
     *
     * @param object $signal
     * @return void
     */
    public function emitNow(object $signal): void;

    /**
     * Emit a signal once the open transaction on a connection settles.
     *
     * @param object $signal
     * @param string|null $connection
     * @return bool
     */
    public function emitOnSettle(object $signal, ?string $connection = null): bool;

    /**
     * Deliver a signal whose transaction has just committed.
     *
     * @param object $signal
     * @return void
     * @internal Called by the database layer
     */
    public function settle(object $signal): void;

    /**
     * Set a custom reporter for listener failures that happen after a commit.
     *
     * @param callable(\Throwable, object): void|null $reporter
     * @return self
     */
    public function reportFailuresUsing(?callable $reporter): self;
}
