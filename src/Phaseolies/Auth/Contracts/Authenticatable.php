<?php

namespace Phaseolies\Auth\Contracts;

/**
 * Contract for models that can be authenticated.
 *
 * Application auth models (e.g. User) should implement this so Auth
 * is not locked to a single concrete class.
 */
interface Authenticatable
{
    /**
     * Get the authentication key name used for identifying the user.
     *
     * @return string
     */
    public function getAuthKeyName(): string;
}
