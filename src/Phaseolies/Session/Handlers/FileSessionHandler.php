<?php

namespace Phaseolies\Session\Handlers;

use Phaseolies\Session\Contracts\AbstractSessionHandler;
use RuntimeException;

class FileSessionHandler extends AbstractSessionHandler
{
    public function initialize(): void
    {
        $this->ensureSessionDirectoryExists();
        $this->configureFileSession();
    }

    public function start(): void
    {
        // Disable PHP's default session cache headers
        // (e.g., "Cache-Control: no-store, no-cache" and "Pragma: no-cache")
        // This allows custom cache headers (like those set by doppar middleware)
        // To take effect without being overridden
        session_cache_limiter('');
        if (session_status() === PHP_SESSION_NONE && !session_start()) {
            throw new RuntimeException("Failed to start session.");
        }

        if ($this->shouldRegenerate()) {
            $this->regenerate();
            $_SESSION['last_regenerated'] = time();
        }

        $this->generateToken();
    }

    private function ensureSessionDirectoryExists(): void
    {
        if (!is_dir($this->config['files'])) {
            if (!mkdir($this->config['files'], 0700, true)) {
                throw new RuntimeException("Failed to create session directory: {$this->config['files']}");
            }
        }
    }

    private function configureFileSession(): void
    {
        @ini_set('session.save_handler', 'files');
        @ini_set('session.save_path', $this->config['files']);
        @ini_set('session.gc_maxlifetime', $this->config['lifetime'] * 60);
    }

    public function validate(): void {}
}
