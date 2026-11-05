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
     * Apply only what ends the session, for a request that is failing
     *
     * @param Response|null $response
     * @return void
     */
    public function commitEnd(?Response $response = null): void;

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
     * The id the session is stored under right now, if it is stored
     *
     * @return string|null
     */
    public function storedId(): ?string;

    /**
     * Run a callback while no other request can save this session
     *
     * @param callable $callback
     * @return mixed
     */
    public function exclusively(callable $callback): mixed;

    /**
     * A value as the stored session holds it right now
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function storedValue(string $key, mixed $default = null): mixed;

    /**
     * Put a value, and save it into the stored session right away
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function putThrough(string $key, mixed $value): void;

    /**
     * Run the housekeeping that must not delay the response
     *
     * @return void
     */
    public function terminate(): void;
}
