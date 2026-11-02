<?php

namespace Phaseolies\Session\Contracts;

use Phaseolies\Http\Request;
use Phaseolies\Http\Response;

interface CommittableSession
{
    /**
     * Bind the session to the request it belongs to
     *
     * @param Request $request
     * @return void
     */
    public function attach(Request $request): void;

    /**
     * Persist the session and queue its cookie
     *
     * @param Response|null $response
     * @return void
     */
    public function commit(?Response $response = null): void;

    /**
     * Turn persistence off for the rest of the request
     *
     * @return void
     */
    public function disable(): void;

    /**
     * Whether this request is not allowed to persist a session
     *
     * @return bool
     */
    public function isStateless(): bool;

    /**
     * Whether the session was loaded during this request
     *
     * @return bool
     */
    public function isLoaded(): bool;

    /**
     * Run the housekeeping that must not delay the response
     *
     * @return void
     */
    public function terminate(): void;
}
