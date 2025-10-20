<?php

namespace Phaseolies\Session\Contracts;

interface SessionHandlerInterface
{
    /**
     * Initializes the session environment.
     *
     * @return void
     */
    public function initialize(): void;

    /**
     * Starts the session.
     *
     * @return void
     */
    public function start(): void;

    /**
     * Regenerates the session ID.
     *
     * @return void
     */
    public function regenerate(): void;

    /**
     * Validates the current session.
     *
     * @return void
     */
    public function validate(): void;

    /**
     * Generates a CSRF or session token.
     *
     * @return void
     */
    public function generateToken(): void;
}
