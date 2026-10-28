<?php

namespace Phaseolies\Auth\Security\Contracts;

interface HashingInterface
{
    /**
     * Hash a password using the configured algorithm and options.
     *
     * @param string $password
     * @return string
     */
    public function make(string $password): string;

    /**
     * Verify if a given password matches the stored hash.
     *
     * @param ?string $password
     * @param ?string $hashedPassword
     * @return bool
     */
    public function check(?string $password, ?string $hashedPassword): bool;

    /**
     * Check if a password hash needs rehashing.
     *
     * @param ?string $hashedPassword
     * @return bool
     */
    public function needsRehash(?string $hashedPassword): bool;
}
