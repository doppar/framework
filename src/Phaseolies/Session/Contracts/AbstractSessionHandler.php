<?php

namespace Phaseolies\Session\Contracts;

use Phaseolies\Session\Contracts\SessionHandlerInterface;

/**
 * Abstract base class for session handlers providing common functionality
 * for session management including token generation and session regeneration.
 */
abstract class AbstractSessionHandler implements SessionHandlerInterface
{
    /**
     * Configuration options for the session handler.
     * 
     * @var array
     */
    protected array $config;

    /**
     * Constructor - initializes the session handler with configuration.
     * 
     * @param array $config Configuration options for the session
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Generates a CSRF token and stores it in the session if not already present.
     * Uses a cryptographically secure random_bytes function for token generation.
     *
     * @return void
     */
    public function generateToken(): void
    {
        $token = session('_token', bin2hex(random_bytes(16))) ?? null;

        if (!session()->has('_token')) {
            session()->put('_token', $token);
        }
    }

    /**
     * Regenerates the session ID to prevent session fixation attacks.
     * Only regenerates if the session is currently active.
     *
     * @return void
     */
    public function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /**
     * Determines if the session should be regenerated based on the configured lifetime.
     *
     * @return bool True if session should be regenerated, false otherwise
     */
    protected function shouldRegenerate(): bool
    {
        return !isset($_SESSION['last_regenerated']) ||
            (time() - $_SESSION['last_regenerated']) > ($this->config['lifetime'] * 60);
    }
}
