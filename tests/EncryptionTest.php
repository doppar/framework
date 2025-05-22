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
}
