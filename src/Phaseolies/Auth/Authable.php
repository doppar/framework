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
     * Save the model.
     *
     * @return bool
     */
    public function save(): bool
    {
        if ($this->hooksSuppressed) {
            $this->recordPasswordChange($this->changedPasswordHash());
        }

        return parent::save();
    }

    /**
     * Update the model.
     *
     * @param array $attributes
     * @return bool
     */
    public function update(array $attributes): bool
    {
        if ($this->hooksSuppressed) {
            $name = $this->getAuthPasswordName();

            if (
                isset($this->attributes[$this->primaryKey])
                && array_key_exists($name, $attributes)
                && in_array($name, $this->creatable, true)
            ) {
                // Normalise the value exactly as setAttribute() will, so the
                // recorded hash is the one that ends up in the database.
                $value = $this->castForSet($name, $this->sanitize($attributes[$name]));

                $this->recordPasswordChange(
                    $value !== ($this->getOriginalAttributes()[$name] ?? $this->attributes[$name] ?? null) ? $value : null
                );
            }
        }

        return parent::update($attributes);
    }

    /**
     * Run the model's before hooks, then record a password about to change.
     *
     * @param string $event
     * @return bool
     */
    protected function fireBeforeHooks(string $event): bool
    {
        $result = parent::fireBeforeHooks($event);

        if ($event === 'updated') {
            $this->recordPasswordChange($this->changedPasswordHash());
        }

        return $result;
    }

    /**
     * The new password hash the next save writes, if it changes the password.
     *
     * @return mixed
     */
    private function changedPasswordHash(): mixed
    {
        $name = $this->getAuthPasswordName();

        return isset($this->attributes[$this->primaryKey])
            && $this->isDirtyAttr($name)
            && ($this->creatable === [] || in_array($name, $this->creatable, true))
                ? $this->attributes[$name]
                : null;
    }

    /**
     * Tell the auth layer that a password is about to be saved.
     *
     * @param mixed $hash
     * @return void
     */
    private function recordPasswordChange(mixed $hash): void
    {
        if (!is_string($hash) || $hash === '') {
            return;
        }

        try {
            $manager = app('auth');
            $actor = $manager instanceof ActorManager ? $manager->actor() : null;
        } catch (\Throwable) {
            $actor = null;
        }

        if ($actor instanceof Authenticate) {
            $actor->recordPendingPassword($this, $hash);
        }
    }

}
