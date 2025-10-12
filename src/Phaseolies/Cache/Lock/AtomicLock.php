<?php

namespace Phaseolies\Cache\Lock;

use Phaseolies\Cache\CacheStore;

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
     * Create a new lock instance.
     *
     * @param CacheStore $store
     * @param string $name
     * @param int $seconds
     * @param string|null $owner
     * @return void
     */
    public function __construct(CacheStore $store, string $name, int $seconds, ?string $owner = null)
    {
        $this->store = $store;
        $this->name = $name;
        $this->seconds = $seconds;
        $this->owner = $owner ?: $this->generateOwner();
    }

    /**
     * Attempt to acquire the lock.
     *
     * @return bool
     */
    public function get(): bool
    {
        $lockData = [
            'owner' => $this->owner,
            'duration' => $this->seconds,
            'acquired_at' => time()
        ];

        $serializedData = json_encode($lockData);

        return $this->store->add(
            $this->name,
            $serializedData,
            $this->seconds
        );
    }

    /**
     * Attempt to acquire the lock for the given number of seconds.
     *
     * @param int $seconds
     * @return bool
     */
    public function block(int $seconds): bool
    {
        $end = time() + $seconds;

        while (time() < $end) {
            if ($this->get()) {
                return true;
            }
            usleep(250000);
        }

        return false;
    }

    /**
     * Release the lock.
     *
     * @return bool
     */
    public function release(): bool
    {
        $currentOwner = $this->owner();

        if ($currentOwner === $this->owner) {
            return $this->store->forget($this->name);
        }

        return false;
    }

    /**
     * Returns the current owner of the lock.
     *
     * @return string
     */
    public function owner(): string
    {
        $lockData = $this->store->get($this->name);

        if ($lockData) {
            $data = json_decode($lockData, true);
            return $data['owner'] ?? '';
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
        return $this->owner === $this->owner();
    }

    /**
     * Generate a random owner identifier.
     *
     * @return string
     */
    protected function generateOwner(): string
    {
        return uniqid('', true) . '_' . getmypid();
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
}
