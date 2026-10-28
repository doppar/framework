<?php

namespace Phaseolies\Auth\Security\Contracts;

interface HashingInterface
{
    /**
     * Hash a password using the configured algorithm and options.
     *
     * @param string $password
     * @return string
     * @throws \RuntimeException
     */
    public function make(string $password): string;

    /**
     * Verify if a given password matches the stored hash.
     *
     * A missing password or hash (for example an account without a password)
     * never matches and returns false instead of throwing.
     *
     * @param ?string $password
     * @param ?string $hashedPassword
     * @return bool
     */
    public function check(?string $password, ?string $hashedPassword): bool;

    /**
     * Check if a password hash needs rehashing.
     *
     * A missing hash (null) always needs rehashing and returns true.
     *
     * @param ?string $hashedPassword
     * @return bool
     * @throws \RuntimeException
     */
    public function needsRehash(?string $hashedPassword): bool;
}
