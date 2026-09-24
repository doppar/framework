<?php

namespace Phaseolies\Auth\Contracts;

use Phaseolies\Database\Entity\Model;

/**
 * Contract for models that can be authenticated.
 *
 * Application auth models (e.g. User) should implement this so Auth
 * is not locked to a single concrete class.
 *
 * @property int|string $id
 * @property string|null $email
 * @property string|null $remember_token
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @phpstan-require-extends Model
 */
interface Authenticatable
{
    /**
     * Get the authentication key name used for identifying the user.
     *
     * @return string
     */
    public function getAuthKeyName(): string;

    /**
     * Get the primary key value for the user.
     *
     * @return string|null
     */
    public function getKey(): ?string;

    /**
     * Persist the user attributes.
     *
     * @return bool
     */
    public function save(): bool;
}
