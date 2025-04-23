<?php

namespace Phaseolies\Auth\Security;

use RuntimeException;

class PasswordHashing
{
    /**
     * Hash a password using the configured algorithm and options.
     *
     * @param string $password
     * @return string
     * @throws RuntimeException
     */
    public function make($password)
    {
        $driver = config('hashing.driver', 'bcrypt');

        switch ($driver) {
            case 'bcrypt':
                $rounds = config('hashing.bcrypt.rounds', 10);
                if ($rounds < 4 || $rounds > 31) {
                    throw new RuntimeException('Bcrypt rounds must be between 4 and 31.');
                }
                return password_hash($password, PASSWORD_BCRYPT, ['cost' => $rounds]);

            case 'argon':
            case 'argon2id':
                $options = [
                    'memory_cost' => config('hashing.argon.memory', PASSWORD_ARGON2_DEFAULT_MEMORY_COST),
                    'time_cost' => config('hashing.argon.time', PASSWORD_ARGON2_DEFAULT_TIME_COST),
                    'threads' => config('hashing.argon.threads', PASSWORD_ARGON2_DEFAULT_THREADS),
                ];
                return password_hash($password, PASSWORD_ARGON2ID, $options);

            default:
                throw new RuntimeException("Unsupported hashing driver: {$driver}");
        }
    }

    /**
     * Verify if a given password matches the stored hash.
     *
     * @param string $password
     * @param string $hashedPassword
     * @return bool
     */
    public function check($password, $hashedPassword)
    {
        return password_verify($password, $hashedPassword);
    }

    /**
     * Check if a password hash needs rehashing (security upgrade).
     *
     * @param string $hashedPassword
     * @return bool
     */
    public function needsRehash($hashedPassword)
    {
        $driver = config('hashing.driver', 'bcrypt');

        switch ($driver) {
            case 'bcrypt':
                $rounds = config('hashing.bcrypt.rounds', 10);
                return password_needs_rehash($hashedPassword, PASSWORD_BCRYPT, ['cost' => $rounds]);

            case 'argon':
            case 'argon2id':
                $options = [
                    'memory_cost' => config('hashing.argon.memory', PASSWORD_ARGON2_DEFAULT_MEMORY_COST),
                    'time_cost' => config('hashing.argon.time', PASSWORD_ARGON2_DEFAULT_TIME_COST),
                    'threads' => config('hashing.argon.threads', PASSWORD_ARGON2_DEFAULT_THREADS),
                ];
                return password_needs_rehash($hashedPassword, PASSWORD_ARGON2ID, $options);

            default:
                throw new RuntimeException("Unsupported hashing driver: {$driver}");
        }
    }
}
