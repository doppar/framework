<?php

namespace Phaseolies\Cache\Lock;

use Phaseolies\Cache\CacheStore;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\PhpExecutableFinder;

class AtomicLock
{
    /**
     * The cache store implementation.
     *
     * @var CacheStore
     */
    protected $store;

    /**
     * The name of the lock.
     *
     * @var string
     */
    protected $name;

    /**
     * The number of seconds the lock should be maintained.
     *
     * @var int
     */
    protected $seconds;

    /**
     * The owner identifier of the lock.
     *
     * @var string
     */
    protected $owner;

    /**
     * @var bool
     */
    private $shouldRelease = true;

    /**
     * @var bool
     */
    private $isOwned = false;

    /**
     * @var Process|null
     */
    private $heartbeatProcess = null;

    /**
     * @var int
     */
    private $heartbeatInterval;

    /**
     * @var bool
     */
    private $isRestored = false;

    /**
     * Create a new lock instance.
     *
     * @param CacheStore $store
     * @param string $name
     * @param int $seconds
     * @param string|null $owner
     * @return void
     */
    public function __construct(CacheStore $store, string $name, int $seconds, ?string $owner = null, bool $isRestored = false)
    {
        if ($seconds <= 0) {
            throw new \InvalidArgumentException('Lock seconds must be greater than 0');
        }

        $this->store = $store;
        $this->name = $name;
        $this->seconds = $seconds;
        $this->owner = $owner ?: $this->generateOwner();
        $this->isRestored = $isRestored;

        // If this is a restored lock
        // Checking if we still own it
        if ($isRestored) {
            $this->isOwned = $this->validateRestoredOwnership();
        }

        // Heartbeat interval: refresh at 1/3 of lock duration
        $this->heartbeatInterval = max(1, (int) floor($seconds / 3));
    }

    /**
     * Attempt to acquire the lock.
     *
     * @return bool
     */
    public function get(): bool
    {
        if ($this->isOwned) {
            return true;
        }

        try {
            $this->checkAndReleaseStaleLock();

            $lockData = [
                'owner' => $this->owner,
                'duration' => $this->seconds,
                'acquired_at' => time(),
                'expires_at' => time() + $this->seconds,
                'version' => 1,
                'heartbeat_interval' => $this->heartbeatInterval
            ];

            $serializedData = json_encode($lockData);
            if ($serializedData === false) {
                throw new \RuntimeException('Failed to encode lock data');
            }

            $acquired = $this->store->add($this->name, $serializedData, $this->seconds);

            if ($acquired) {
                $this->isOwned = true;
                if (!$this->isRunningInTest()) {
                    $this->startHeartbeat();
                }
            }

            return $acquired;
        } catch (\Exception $e) {
            error("Lock acquisition failed for '{$this->name}': " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if we're running in a test environment.
     *
     * @return bool
     */
    private function isRunningInTest(): bool
    {
        return defined('PHPUNIT_COMPOSER_INSTALL') ||
            getenv('APP_ENV') === 'testing' ||
            getenv('PHPUNIT') === '1' ||
            (isset($_SERVER['argv']) && in_array('--test', $_SERVER['argv']));
    }

    /**
     * Start heartbeat to refresh lock.
     *
     * @return void
     */
    private function startHeartbeat(): void
    {
        if ($this->heartbeatProcess !== null && $this->heartbeatProcess->isRunning()) {
            return;
        }

        try {
            $phpBinary = (new PhpExecutableFinder())->find();

            if (!$phpBinary) {
                throw new \RuntimeException('Could not find PHP executable');
            }

            $inlineCode = $this->buildHeartbeatCode();

            $command = [$phpBinary, '-r', $inlineCode];

            $this->heartbeatProcess = new Process($command);
            $this->heartbeatProcess->setTimeout(null);

            if (!$this->isRunningInTest()) {
                $this->heartbeatProcess->disableOutput();
            }

            $this->heartbeatProcess->start();

            $maxChecks = 5;
            $checks = 0;

            while ($checks < $maxChecks) {
                usleep(100000);
                if ($this->heartbeatProcess->isRunning()) {
                    error("Heartbeat started for lock '{$this->name}' with PID: " . $this->heartbeatProcess->getPid());
                    return;
                }
                $checks++;
            }

            // If we get here, the process didn't start properly
            $errorOutput = $this->heartbeatProcess->getErrorOutput();
            $exitCode = $this->heartbeatProcess->getExitCode();

            throw new \RuntimeException(
                "Heartbeat process exited immediately. " .
                    "Exit code: {$exitCode}, " .
                    "Error: {$errorOutput}"
            );
        } catch (\Exception $e) {
            error("Failed to start heartbeat for lock '{$this->name}': " . $e->getMessage());
            $this->heartbeatProcess = null;
        }

        // Fallback shutdown function
        register_shutdown_function([$this, 'release']);
    }

    /**
     * Build the heartbeat PHP code.
     *
     * @return string
     */
    private function buildHeartbeatCode(): string
    {
        return sprintf(
            '<?php
            $lockName = "%s";
            $owner = "%s";
            $duration = %d;
            $interval = %d;
            $maxRuntime = %d;

            // Use the same cache directory as Symfony FilesystemAdapter
            $cacheDir = sys_get_temp_dir() . "/phaseolies_cache";
            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0777, true);
            }

            $endTime = time() + $maxRuntime;
            $key = md5($lockName);
            $filename = $cacheDir . "/" . $key;

            while (time() < $endTime) {
                if (file_exists($filename)) {
                    $content = file_get_contents($filename);
                    if ($content !== false) {
                        $data = unserialize($content);
                        if (is_array($data) && isset($data["value"])) {
                            $lockData = json_decode($data["value"], true);
                            if (is_array($lockData) && ($lockData["owner"] ?? "") === $owner) {
                                // Update the lock with new expiration
                                $newLockData = $lockData;
                                $newLockData["expires_at"] = time() + $duration;
                                $newLockData["last_heartbeat"] = time();
                                $newLockData["version"] = ($lockData["version"] ?? 0) + 1;

                                $newData = [
                                    "value" => json_encode($newLockData),
                                    "expires" => time() + $duration
                                ];

                                $tempFile = $filename . "." . uniqid();
                                if (file_put_contents($tempFile, serialize($newData))) {
                                    rename($tempFile, $filename);
                                }
                            } else {
                                // Lock ownership changed or invalid
                                exit(0);
                            }
                        } else {
                            // Invalid data format
                            exit(0);
                        }
                    } else {
                        // Cannot read file
                        exit(0);
                    }
                } else {
                    // Lock file doesn\'t exist
                    exit(0);
                }
                sleep($interval);
            }
            exit(0);',
            addslashes($this->name),
            addslashes($this->owner),
            $this->seconds,
            $this->heartbeatInterval,
            $this->seconds + 10
        );
    }

    /**
     * Stop heartbeat process.
     *
     * @return void
     */
    private function stopHeartbeat(): void
    {
        if ($this->heartbeatProcess !== null) {
            try {
                if ($this->heartbeatProcess->isRunning()) {
                    // Give it a moment to terminate gracefully
                    $this->heartbeatProcess->stop(2, SIGTERM);

                    // Force kill if still running
                    if ($this->heartbeatProcess->isRunning()) {
                        $this->heartbeatProcess->stop(0, SIGKILL);
                    }
                }
            } catch (\Exception $e) {
                // Ignore errors during shutdown
            }

            $this->heartbeatProcess = null;
        }
    }

    /**
     * Release the lock.
     *
     * @return bool
     */
    public function release(): bool
    {
        if (!$this->isOwned) {
            return false;
        }

        try {
            $this->stopHeartbeat();

            $lockData = $this->store->get($this->name);

            if (!$lockData) {
                $this->isOwned = false;
                // Lock doesn't exist - return false
                return false;
            }

            $data = json_decode($lockData, true);
            if (!is_array($data) || ($data['owner'] ?? '') !== $this->owner) {
                $this->isOwned = false;
                // Not owned by current process
                return false;
            }

            $success = $this->store->delete($this->name);

            if ($success) {
                $this->isOwned = false;
            }

            return $success;
        } catch (\Exception $e) {
            error("Lock release failed for '{$this->name}': " . $e->getMessage());
            $this->isOwned = false;
            return false;
        }
    }

    /**
     * Check and release stale lock.
     *
     * @return void
     */
    private function checkAndReleaseStaleLock(): void
    {
        try {
            $lockData = $this->store->get($this->name);

            if (!$lockData) {
                return;
            }

            $data = json_decode($lockData, true);

            if (!is_array($data) || !isset($data['expires_at'])) {
                // Corrupted lock data
                $this->store->delete($this->name);
                return;
            }

            $expiresAt = $data['expires_at'];
            $currentTime = time();

            // Add small grace period
            if ($currentTime > $expiresAt + 2) {
                // Lock is expired - release it
                $this->store->delete($this->name);
            }
        } catch (\Exception $e) {
            // Don't log during normal operation to avoid noise
        }
    }

    /**
     * Safely release lock on destruction.
     */
    public function __destruct()
    {
        if ($this->isOwned && $this->shouldRelease) {
            $this->release();
        }
    }

    /**
     * Attempt to acquire the lock for the given number of seconds.
     *
     * @param int $seconds
     * @return bool
     */
    public function block(int $seconds): bool
    {
        if ($seconds <= 0) {
            throw new \InvalidArgumentException('Block seconds must be greater than 0');
        }

        $startTime = time();
        $end = $startTime + $seconds;
        $attempts = 0;
        $maxAttempts = $seconds * 4;

        while (time() < $end && $attempts < $maxAttempts) {
            if ($this->get()) {
                return true;
            }

            // Simpler backoff without jitter for reliability
            // 100ms to 500ms
            $sleepTime = min(500000, 100000 * (1 + $attempts));
            usleep($sleepTime);
            $attempts++;
        }

        return false;
    }

    /**
     * Prevent the lock from being automatically released.
     *
     * @return $this
     */
    public function preventRelease(): self
    {
        $this->shouldRelease = false;

        return $this;
    }

    /**
     * Allow the lock to be automatically released.
     *
     * @return $this
     */
    public function allowRelease(): self
    {
        $this->shouldRelease = true;

        return $this;
    }

    /**
     * Returns the current owner of the lock.
     *
     * @return string
     */
    public function owner(): string
    {
        try {
            $lockData = $this->store->get($this->name);

            if ($lockData) {
                $data = json_decode($lockData, true);
                return is_array($data) ? ($data['owner'] ?? '') : '';
            }
        } catch (\Exception $e) {
            // Silent failure
        }

        return '';
    }

    /**
     * Determines whether this lock is owned by the current process.
     *
     * @return bool
     */
    public function isOwnedByCurrentProcess(): bool
    {
        return $this->isOwned && $this->owner === $this->owner();
    }

    /**
     * Get the remaining time until lock expiration.
     *
     * @return int|null
     */
    public function getRemainingTime(): ?int
    {
        try {
            $lockData = $this->store->get($this->name);

            if ($lockData) {
                $data = json_decode($lockData, true);
                if (is_array($data) && isset($data['expires_at'])) {
                    $remaining = $data['expires_at'] - time();
                    return max(0, $remaining);
                }
            }
        } catch (\Exception $e) {
            // Silent failure
        }

        return null;
    }

    /**
     * Validate that we still own a restored lock.
     *
     * @return bool
     */
    private function validateRestoredOwnership(): bool
    {
        try {
            $lockData = $this->store->get($this->name);

            if (!$lockData) {
                // Lock doesn't exist
                return false;
            }

            $data = json_decode($lockData, true);
            if (!is_array($data) || ($data['owner'] ?? '') !== $this->owner) {
                // Lock exists but we don't own it
                return false;
            }

            // Check if lock is expired
            $expiresAt = $data['expires_at'] ?? 0;
            if (time() > $expiresAt) {
                return false; // Lock expired
            }

            // We still own the lock
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Generate a random owner identifier.
     *
     * @return string
     */
    protected function generateOwner(): string
    {
        return uniqid('', true) . '_' . getmypid() . '_' . bin2hex(random_bytes(4));
    }

    /**
     * Get the owner of the lock.
     *
     * @return string
     */
    public function getOwner(): string
    {
        return $this->owner;
    }

    /**
     * Get the name of the lock.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the seconds of the lock.
     *
     * @return int
     */
    public function getSeconds(): int
    {
        return $this->seconds;
    }

    /**
     * Check if the lock is currently owned by this instance.
     *
     * @return bool
     */
    public function isOwned(): bool
    {
        return $this->isOwned;
    }

    /**
     * Manually set the lock as owned (for restoreLock functionality)
     * 
     * @return $this
     */
    public function setOwned(): self
    {
        $this->isOwned = true;

        return $this;
    }

    /**
     * Check if this lock was restored.
     *
     * @return bool
     */
    public function isRestored(): bool
    {
        return $this->isRestored;
    }

    /**
     * Get the heartbeat interval.
     *
     * @return int
     */
    public function getHeartbeatInterval(): int
    {
        return $this->heartbeatInterval;
    }

    /**
     * Check if heartbeat is running.
     *
     * @return bool
     */
    public function isHeartbeatRunning(): bool
    {
        return $this->heartbeatProcess !== null && $this->heartbeatProcess->isRunning();
    }
}
