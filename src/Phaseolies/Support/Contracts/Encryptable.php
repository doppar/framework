<?php

namespace Phaseolies\Support\Contracts;

/**
 * @package Phaseolies\Support\Contracts
 *
 * This interface should be implemented by any Model that needs to specify
 * which of its properties should be encrypted.
 */
interface Encryptable
{
    /**
     * Returns an array of property names that should be encrypted.
     *
     * @return array
     */
    public function getEncryptedProperties(): array;
}
