<?php

namespace Phaseolies\Support;

use RuntimeException;

class Encryption
{
    /**
     * Encrypt data using AES-256-CBC encryption.
     *
     * @param mixed $data The data to encrypt. Can be a string or an array.
     * @return string Returns a base64-encoded string containing the encrypted data and IV.
     * @throws RuntimeException If encryption fails.
     */
    public function encrypt(mixed $data): string
    {
        if ($data === null) {
            throw new RuntimeException('Cannot encrypt null value');
        }

        [$cipher, $key] =  $this->getAppKeyAndChiper();

        if (is_array($data)) {
            $data = json_encode($data);
        }

        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($cipher));

        $encryptedData = openssl_encrypt($data, $cipher, $key, 0, $iv);

        if ($encryptedData === false) {
            throw new RuntimeException('Encryption failed');
        }

        // Combine the encrypted data and IV,
        // Then base64-encode the result for safe storage/transmission
        return base64_encode($encryptedData . '::' . $iv);
    }

    /**
     * Decrypt data that was encrypted using AES-256-CBC.
     *
     * @param string $encryptedData The base64-encoded encrypted data string (including IV).
     * @return mixed Returns the decrypted data as a string or array (if the original data was an array).
     * @throws RuntimeException If decryption fails.
     */
    public function decrypt(string $encryptedData): mixed
    {
        [$cipher, $key] =  $this->getAppKeyAndChiper();

        $data = base64_decode($encryptedData);

        [$encryptedData, $iv] = explode('::', $data, 2);

        $decryptedData = openssl_decrypt($encryptedData, $cipher, $key, 0, $iv);

        if ($decryptedData === false) {
            throw new RuntimeException('Decryption failed');
        }

        $decodedData = json_decode($decryptedData, true);

        // Return the decrypted data as an array (if JSON decoding succeeded) or as a string
        return $decodedData ? $decodedData : $decryptedData;
    }

    /**
     * Get the application chipper and app key
     *
     * @return array
     */
    public function getAppKeyAndChiper(): array
    {
        // For UNIT Testing
        if (PHP_SAPI === 'cli' || defined('STDIN')) {
            $cipher = 'AES-256-CBC';
            $key = "base64:ImGTGoQ6ZhM7yBlMvp41ejp0nt8juImy1aIbf6shQCI=";
            return [$cipher, $key];
        }

        $cipher = config('app.cipher') ?? 'AES-256-CBC';
        $key = base64_decode(getenv('APP_KEY'));

        return [$cipher, $key];
    }
}
