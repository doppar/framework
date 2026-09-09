<?php

namespace Tests\Unit;

use Phaseolies\Support\Encryption;
use RuntimeException;
use PHPUnit\Framework\TestCase;

class EncryptionTest extends TestCase
{
    private Encryption $encryption;

    protected function setUp(): void
    {
        $this->encryption = new Encryption();
    }

    public function testEncryptAndDecryptAString()
    {
        $original = 'This is a secret message';

        $encrypted = $this->encryption->encrypt($original);
        $decrypted = $this->encryption->decrypt($encrypted);

        $this->assertEquals($original, $decrypted);
        $this->assertNotEquals($original, $encrypted);
    }

    public function testEncryptAndDecryptAnArray()
    {
        $original = ['name' => 'Mahedi Hasan', 'email' => 'mahedi@doppar.com'];

        $encrypted = $this->encryption->encrypt($original);
        $decrypted = $this->encryption->decrypt($encrypted);

        $this->assertEquals($original, $decrypted);
        $this->assertIsArray($decrypted);
    }

    public function test_returns_string_when_decrypted_data_is_not_json()
    {
        $original = 'This is not JSON';

        $encrypted = $this->encryption->encrypt($original);
        $decrypted = $this->encryption->decrypt($encrypted);

        $this->assertEquals($original, $decrypted);
        $this->assertIsString($decrypted);
    }

    public function test_encrypted_values_are_different_each_time()
    {
        $original = 'Same message';

        $encrypted1 = $this->encryption->encrypt($original);
        $encrypted2 = $this->encryption->encrypt($original);

        $this->assertNotEquals($encrypted1, $encrypted2);
    }

    // ==================== AEAD / KEY DERIVATION TESTS ====================

    public function test_encrypted_output_uses_versioned_aead_envelope()
    {
        $encrypted = $this->encryption->encrypt('some payload');

        $this->assertStringStartsWith('v2:', $encrypted);
    }

    public function test_tampered_ciphertext_fails_to_decrypt()
    {
        $encrypted = $this->encryption->encrypt('do not tamper with me');

        // Flip one character in the payload after the version prefix.
        $index = strlen('v2:') + 5;
        $encrypted[$index] = $encrypted[$index] === 'A' ? 'B' : 'A';

        $this->assertFalse($this->encryption->decrypt($encrypted));
    }

    public function test_truncated_ciphertext_fails_to_decrypt()
    {
        $encrypted = $this->encryption->encrypt('do not truncate me');

        $this->assertFalse($this->encryption->decrypt(substr($encrypted, 0, -4)));
    }

    public function test_decrypts_legacy_pre_aead_cbc_ciphertext()
    {
        // Simulates data encrypted by the pre-fix code path: raw
        // AES-256-CBC with no authentication tag, no version prefix.
        $key = $this->encryption->getAppKey();
        $cipher = 'AES-256-CBC';
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($cipher));
        $ciphertext = openssl_encrypt('legacy secret', $cipher, $key, 0, $iv);
        $legacyBlob = base64_encode($ciphertext . '::' . $iv);

        $this->assertSame('legacy secret', $this->encryption->decrypt($legacyBlob));
    }

    public function test_decrypts_legacy_ciphertext_encrypted_with_pre_fix_buggy_key()
    {
        // Simulates data encrypted before the APP_KEY decode bug was
        // fixed: the whole "base64:..." string was run through
        // base64_decode() instead of stripping the prefix first.
        $buggyKey = base64_decode(getenv('APP_KEY') ?: 'base64:ImGTGoQ6ZhM7yBlMvp41ejp0nt8juImy1aIbf6shQCI=');
        $cipher = 'AES-256-CBC';
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($cipher));
        $ciphertext = openssl_encrypt('pre-fix secret', $cipher, $buggyKey, 0, $iv);
        $legacyBlob = base64_encode($ciphertext . '::' . $iv);

        $this->assertSame('pre-fix secret', $this->encryption->decrypt($legacyBlob));
    }

    public function test_get_app_key_strips_base64_prefix_correctly()
    {
        $rawEnv = 'base64:ImGTGoQ6ZhM7yBlMvp41ejp0nt8juImy1aIbf6shQCI=';
        $expectedKey = base64_decode(substr($rawEnv, 7));
        $buggyKey = base64_decode($rawEnv);

        $key = $this->encryption->getAppKey();

        $this->assertSame($expectedKey, $key);
        $this->assertSame(32, strlen($key));
        $this->assertNotSame($buggyKey, $key);
    }
}
