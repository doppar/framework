<?php

namespace Phaseolies\Session\Handlers;

use Phaseolies\Session\Contracts\AbstractSessionHandler;
use RuntimeException;

class FileSessionHandler extends AbstractSessionHandler
{
    /**
     * Initializes the file-based session handler by:
     * - Ensuring the session storage directory exists.
     * - Setting necessary PHP INI directives for file-based sessions.
     */
    public function initialize(): void
    {
        $this->ensureSessionDirectoryExists();
        $this->configureFileSession();
    }

    /**
     * Starts the session, applies custom session cache settings,
     * and performs session regeneration and token generation as needed.
     *
     * @throws RuntimeException if the session fails to start.
     */
    public function start(): void
    {
        // Disable PHP's default session cache headers
        // (e.g., "Cache-Control: no-store, no-cache" and "Pragma: no-cache")
        // This allows custom cache headers (like those set by doppar middleware)
        // To take effect without being overridden
        if (session_status() === PHP_SESSION_NONE) {
            session_cache_limiter('');
        }

        if (session_status() === PHP_SESSION_NONE && !session_start()) {
            throw new RuntimeException("Failed to start session.");
        }

        if ($this->shouldRegenerate()) {
            $this->regenerate();
            session()->put('last_regenerated', time());
        }

        $this->generateToken();
    }

    /**
     * Ensures the session save directory exists.
     * If it doesn't, attempts to create it. Fails with an exception if creation fails.
     */
    private function ensureSessionDirectoryExists(): void
    {
        if (!is_dir($this->config['files'])) {
            if (!mkdir($this->config['files'], 0700, true)) {
                throw new RuntimeException("Failed to create session directory: {$this->config['files']}");
            }
        }
    }

    /**
     * Configures PHP to use the filesystem for session storage
     * with specific path and lifetime settings pulled from config.
     */
    private function configureFileSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.save_handler', 'files');
            ini_set('session.save_path', $this->config['files']);
            ini_set('session.gc_maxlifetime', $this->config['lifetime'] * 60);
        }
    }

    /**
     * Placeholder for session validation logic.
     * This could be expanded to implement custom validation checks.
     */
    public function validate(): void {}
}
