<?php

namespace Phaseolies\Support\Contracts;

interface EncryptionInterface
{
    /**
     * Encrypt data using the configured encryption key.
     *
     * @param mixed $data
     * @return string
     */
    public function encrypt(mixed $data): string;

    /**
     * Decrypt encrypted data.
     *
     * @param string $encryptedData
     * @return mixed
     */
    public function decrypt(string $encryptedData): mixed;
}
