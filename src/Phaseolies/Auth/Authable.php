<?php

namespace Phaseolies\Auth;

use Phaseolies\Database\Entity\Model;

/**
 * @property int|string|null $id
 * @property string|null $password
 * @property string|null $remember_token
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 */
abstract class Authable extends Model
{
    /**
     * Get the authentication key name used for identifying the user.
     *
     * @return string
     */
    public function getAuthKeyName(): string
    {
        return 'email';
    }

    /**
     * Get the unique identifier for the user.
     *
     * @return int|string|null
     */
    public function getAuthIdentifier(): int|string|null
    {
        return $this->{$this->getKeyName()};
    }

    /**
     * Get the password for the user.
     *
     * @return string|null
     */
    public function getAuthPassword(): ?string
    {
        return $this->password;
    }

    /**
     * Get the remember token value.
     *
     * @return string|null
     */
    public function getRememberToken(): ?string
    {
        return $this->remember_token;
    }

    /**
     * Set the remember token value.
     *
     * @param string|null $value
     * @return void
     */
    public function setRememberToken(?string $value): void
    {
        $this->remember_token = $value;
    }

    /**
     * Get the two-factor authentication secret.
     *
     * @return string|null
     */
    public function getTwoFactorSecret(): ?string
    {
        return $this->two_factor_secret;
    }

    /**
     * Set the two-factor authentication secret.
     *
     * @param string|null $value
     * @return void
     */
    public function setTwoFactorSecret(?string $value): void
    {
        $this->two_factor_secret = $value;
    }

    /**
     * Get the two-factor recovery codes.
     *
     * @return string|null
     */
    public function getTwoFactorRecoveryCodes(): ?string
    {
        return $this->two_factor_recovery_codes;
    }

    /**
     * Set the two-factor recovery codes.
     *
     * @param string|null $value
     * @return void
     */
    public function setTwoFactorRecoveryCodes(?string $value): void
    {
        $this->two_factor_recovery_codes = $value;
    }
}
