<?php

namespace Phaseolies\Auth;

use Phaseolies\Auth\Security\Authenticate;
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
        return $this->{$this->getAuthPasswordName()};
    }

    /**
     * Get the name of the attribute that holds the password hash.
     *
     * @return string
     */
    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    /**
     * Get the remember token value.
     *
     * @return string|null
     */
    public function getRememberToken(): ?string
    {
        return $this->{$this->getRememberTokenName()};
    }

    /**
     * Set the remember token value.
     *
     * @param string|null $value
     * @return void
     */
    public function setRememberToken(?string $value): void
    {
        $this->{$this->getRememberTokenName()} = $value;
    }

    /**
     * Get the name of the attribute that holds the remember token.
     *
     * @return string
     */
    public function getRememberTokenName(): string
    {
        return 'remember_token';
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

    /**
     * Save the model, keeping this device signed in when it changes the password of the signed-in user.
     *
     * @return bool
     */
    public function save(): bool
    {
        $name = $this->getAuthPasswordName();

        $hash = isset($this->attributes[$this->primaryKey])
            && $this->isDirtyAttr($name)
            && ($this->creatable === [] || in_array($name, $this->creatable, true))
                ? $this->attributes[$name]
                : null;

        return (bool) $this->whileSavingPassword($hash, fn (): bool => parent::save());
    }

    /**
     * Update the model, keeping this device signed in when it changes the password of the signed-in user.
     *
     * @param array $attributes
     * @return bool
     */
    public function update(array $attributes): bool
    {
        $name = $this->getAuthPasswordName();

        $hash = isset($this->attributes[$this->primaryKey])
            && array_key_exists($name, $attributes)
            && in_array($name, $this->creatable, true)
            && $attributes[$name] !== ($this->getOriginalAttributes()[$name] ?? null)
                ? $attributes[$name]
                : null;

        return (bool) $this->whileSavingPassword($hash, fn (): bool => parent::update($attributes));
    }

    /**
     * Run a write, through the auth layer when it saves a new password hash.
     *
     * @param mixed $hash
     * @param callable(): bool $write
     * @return bool
     */
    private function whileSavingPassword(mixed $hash, callable $write): bool
    {
        if (!is_string($hash) || $hash === '') {
            return $write();
        }

        try {
            $manager = app('auth');
            $actor = $manager instanceof ActorManager ? $manager->actor() : null;
        } catch (\Throwable) {
            $actor = null;
        }

        return $actor instanceof Authenticate
            ? (bool) $actor->savePassword($this, $hash, $write)
            : $write();
    }
}
