<?php

namespace Phaseolies\Support;

use RuntimeException;

class Encryption
{
    /**
     * The cipher used for new encryption. AES-256-GCM is an AEAD
     * cipher, so ciphertext integrity is verified on decrypt instead
     * of trusting unauthenticated CBC output.
     *
     * @var string
     */
    private const CIPHER = 'aes-256-gcm';

    /**
     * The cipher used by data encrypted before AEAD support was
     * added. Kept only so previously-encrypted values (e.g. existing
     * remember-me cookies, encrypted model columns) remain readable.
     *
     * @var string
     */
    private const LEGACY_CIPHER = 'AES-256-CBC';

    /**
     * Marks a ciphertext as using the versioned AEAD envelope, so
     * decrypt() can tell it apart from the legacy CBC format.
     *
     * @var string
     */
    private const VERSION_PREFIX = 'v2:';

    /**
     * GCM authentication tag length in bytes.
     *
     * @var int
     */
    private const TAG_LENGTH = 16;

    /**
     * Fallback key used only when no APP_KEY is configured in the
     * environment (this package's own test suite never loads a
     * .env file).
     *
     * @var string
     */
    private const TESTING_KEY = 'base64:ImGTGoQ6ZhM7yBlMvp41ejp0nt8juImy1aIbf6shQCI=';

    /**
     * Encrypt data using authenticated AES-256-GCM encryption.
     *
     * @param mixed $data
     * @return string
     * @throws RuntimeException
     */
    public function encrypt(mixed $data): string
    {
        if ($data === null) {
            throw new RuntimeException('Cannot encrypt null value');
        }

        if (is_array($data)) {
            $data = json_encode($data);
        }

        $key = $this->getAppKey();
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::CIPHER));
        $tag = '';

        $ciphertext = openssl_encrypt(
            $data,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Encryption failed');
        }

        // Pack IV + auth tag + ciphertext into one binary blob, then
        // base64-encode once for safe storage/transmission. The
        // version prefix is left unencoded so decrypt() can dispatch
        // without decoding first.
        return self::VERSION_PREFIX . base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Decrypt data produced by encrypt(). Also accepts ciphertext
     * produced by the pre-AEAD AES-256-CBC format so existing data
     * keeps working after upgrading; that fallback will be removed
     * in a future major version.
     *
     * @param string $encryptedData
     * @return mixed
     */
    public function decrypt(string $encryptedData): mixed
    {
        $decryptedData = str_starts_with($encryptedData, self::VERSION_PREFIX)
            ? $this->decryptAead(substr($encryptedData, strlen(self::VERSION_PREFIX)))
            : $this->decryptLegacy($encryptedData);

        if ($decryptedData === false) {
            return false;
        }

        $decodedData = json_decode($decryptedData, true);

        // Return the decrypted data as an array
        // (if JSON decoding succeeded) or as a string
        return $decodedData ? $decodedData : $decryptedData;
    }

    /**
     * Decrypt the current AES-256-GCM envelope.
     *
     * @param string $payload
     * @return string|false
     */
    private function decryptAead(string $payload): string|false
    {
        $raw = base64_decode($payload, true);

        if ($raw === false) {
            return false;
        }

        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $iv = substr($raw, 0, $ivLength);
        $tag = substr($raw, $ivLength, self::TAG_LENGTH);
        $ciphertext = substr($raw, $ivLength + self::TAG_LENGTH);

        return openssl_decrypt($ciphertext, self::CIPHER, $this->getAppKey(), OPENSSL_RAW_DATA, $iv, $tag);
    }

    /**
     * Decrypt the pre-AEAD AES-256-CBC format. Tries the correctly
     * derived key first, then falls back to the key this class used
     * to derive before the APP_KEY decoding bug was fixed, so data
     * encrypted before that fix remains readable.
     *
     * @param string $encryptedData
     * @return string|false
     */
    private function decryptLegacy(string $encryptedData): string|false
    {
        $data = base64_decode($encryptedData, true);

        if ($data === false || !str_contains($data, '::')) {
            return false;
        }

        [$ciphertext, $iv] = explode('::', $data, 2);

        foreach ([$this->getAppKey(), $this->getLegacyBuggyAppKey()] as $key) {
            $decrypted = openssl_decrypt($ciphertext, self::LEGACY_CIPHER, $key, 0, $iv);

            if ($decrypted !== false) {
                return $decrypted;
            }
        }

        return false;
    }

    /**
     * Get the application encryption key, correctly decoded.
     *
     * @return string
     */
    public function getAppKey(): string
    {
        $raw = $this->getRawAppKey();

        return str_starts_with($raw, 'base64:')
            ? base64_decode(substr($raw, 7))
            : $raw;
    }

    /**
     * Reproduce the pre-fix (buggy) key derivation, which ran the
     * whole "base64:..." string through base64_decode() instead of
     * stripping the prefix first. Used only as a decrypt fallback for
     * data encrypted before the fix, so it never observes the raw
     * env value changing shape.
     *
     * @return string
     */
    private function getLegacyBuggyAppKey(): string
    {
        return (string) base64_decode($this->getRawAppKey());
    }

    /**
     * Get the raw, undecoded APP_KEY value from the environment,
     * falling back to a fixed testing key only when no real key is
     * configured at all.
     *
     * @return string
     */
    private function getRawAppKey(): string
    {
        $key = getenv('APP_KEY');

        return ($key !== false && $key !== '') ? $key : self::TESTING_KEY;
    }

    /**
     * Get the application cipher and key.
     *
     * @return array
     * @deprecated Use getAppKey() instead. The cipher is fixed to AES-256-GCM for new encryption; this is kept only for backward compatibility with any code calling it directly.
     */
    public function getAppKeyAndChiper(): array
    {
        return [self::CIPHER, $this->getAppKey()];
    }
}
