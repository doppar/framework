<?php

namespace Tests\Unit;

use Phaseolies\Support\Encryption;
use Phaseolies\Support\Contracts\EncryptionInterface;
use Phaseolies\Application;
use Phaseolies\Launchers\FacadeLauncher;
use RuntimeException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class EncryptionTest extends TestCase
{
    private Encryption $encryption;

    protected function setUp(): void
    {
        $this->encryption = new Encryption();
    }

    /**
     * Build a real Application without booting it
     *
     * @return Application
     */
    private function makeApplication(): Application
    {
        return (new ReflectionClass(Application::class))->newInstanceWithoutConstructor();
    }

    public function testEncryptionImplementationConformsToTheEncryptionContract(): void
    {
        $this->assertInstanceOf(EncryptionInterface::class, $this->encryption);
    }

    public function testApplicationBindsTheEncryptionContractAndLegacyServiceNameToTheSameSingleton(): void
    {
        $app = $this->makeApplication();

        (new FacadeLauncher($app))->register();

        $contractEncryption = $app->get(EncryptionInterface::class);
        $legacyEncryption = $app->get('crypt');

        $this->assertInstanceOf(EncryptionInterface::class, $contractEncryption);
        $this->assertInstanceOf(Encryption::class, $contractEncryption);
        $this->assertSame($contractEncryption, $legacyEncryption);

        $encrypted = $contractEncryption->encrypt('contract value');

        $this->assertSame('contract value', $legacyEncryption->decrypt($encrypted));
    }

    public function testInstanceOverrideByLegacyKeyReachesTheContract(): void
    {
        $app = $this->makeApplication();

        (new FacadeLauncher($app))->register();

        $fake = new Encryption();
        $app->instance('crypt', $fake);

        $this->assertSame($fake, $app->get(EncryptionInterface::class));
        $this->assertSame($fake, $app->get('crypt'));
    }

    public function testSingletonOverrideByLegacyKeyReachesTheContract(): void
    {
        $app = $this->makeApplication();

        (new FacadeLauncher($app))->register();

        $custom = new Encryption();
        $app->singleton('crypt', fn() => $custom);

        $this->assertSame($custom, $app->get('crypt'));
        $this->assertSame($custom, $app->get(EncryptionInterface::class));
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

    public function test_legacy_ciphertext_is_not_decrypted_to_garbage_by_the_other_key()
    {
        // Without a MAC, the wrong CBC key yields valid padding about 1 time
        // in 256, so enough random IVs are guaranteed to hit that case for
        // both the correct key and the pre-fix buggy key.
        $keys = [
            $this->encryption->getAppKey(),
            base64_decode(getenv('APP_KEY') ?: 'base64:ImGTGoQ6ZhM7yBlMvp41ejp0nt8juImy1aIbf6shQCI='),
        ];

        foreach ($keys as $key) {
            for ($i = 0; $i < 3000; $i++) {
                $iv = openssl_random_pseudo_bytes(16);
                $legacyBlob = base64_encode(openssl_encrypt('legacy secret', 'AES-256-CBC', $key, 0, $iv) . '::' . $iv);

                $this->assertSame('legacy secret', $this->encryption->decrypt($legacyBlob));
            }
        }
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

    public function test_uses_app_key_loaded_from_env_toml()
    {
        // env.toml only populates $_ENV and $_SERVER, never putenv()
        $tomlKey = 'base64:' . base64_encode(random_bytes(32));

        $this->withAppKey($tomlKey, function () use ($tomlKey) {
            $this->assertSame(base64_decode(substr($tomlKey, 7)), $this->encryption->getAppKey());

            $encrypted = $this->encryption->encrypt('secret');

            $this->assertSame('secret', $this->encryption->decrypt($encrypted));
        }, putenv: false);
    }

    public function test_ciphertext_cannot_be_decrypted_with_another_key()
    {
        $encrypted = null;

        $this->withAppKey('base64:' . base64_encode(random_bytes(32)), function () use (&$encrypted) {
            $encrypted = $this->encryption->encrypt('secret');
        });

        $this->withAppKey('base64:' . base64_encode(random_bytes(32)), function () use ($encrypted) {
            $this->assertFalse($this->encryption->decrypt($encrypted));
        });
    }

    public function test_throws_when_app_key_is_missing()
    {
        $this->withAppKey(null, function () {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('No application encryption key has been specified');

            $this->encryption->encrypt('secret');
        });
    }

    public function test_throws_when_app_key_is_empty()
    {
        $this->withAppKey('', function () {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('No application encryption key has been specified');

            $this->encryption->decrypt('v2:anything');
        });
    }

    /**
     * Run the callback with APP_KEY set to the given value (null unsets it),
     * restoring the original environment afterwards.
     */
    private function withAppKey(?string $key, callable $callback, bool $putenv = true): void
    {
        $original = [
            'env' => $_ENV['APP_KEY'] ?? null,
            'server' => $_SERVER['APP_KEY'] ?? null,
            'getenv' => getenv('APP_KEY'),
        ];

        unset($_ENV['APP_KEY'], $_SERVER['APP_KEY']);
        putenv('APP_KEY');

        if ($key !== null) {
            $_ENV['APP_KEY'] = $key;
            $_SERVER['APP_KEY'] = $key;

            if ($putenv) {
                putenv("APP_KEY={$key}");
            }
        }

        try {
            $callback();
        } finally {
            unset($_ENV['APP_KEY'], $_SERVER['APP_KEY']);
            putenv('APP_KEY');

            if ($original['env'] !== null) {
                $_ENV['APP_KEY'] = $original['env'];
            }

            if ($original['server'] !== null) {
                $_SERVER['APP_KEY'] = $original['server'];
            }

            if ($original['getenv'] !== false) {
                putenv("APP_KEY={$original['getenv']}");
            }
        }
    }
}
