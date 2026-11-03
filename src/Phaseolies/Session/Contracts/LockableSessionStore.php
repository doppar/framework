<?php

namespace Phaseolies\Session\Contracts;

interface LockableSessionStore extends SessionStoreInterface
{
    /**
     * Run a callback while no other request can save or destroy the same session
     *
     * @param string $id
     * @param callable $callback
     * @return mixed
     */
    public function synchronized(string $id, callable $callback): mixed;
}
