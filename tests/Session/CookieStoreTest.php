<?php

namespace Tests\Unit\Session;

use Phaseolies\Session\Exceptions\SessionException;
use Phaseolies\Session\Stores\CookieStore;
use Phaseolies\Support\Encryption;
use PHPUnit\Framework\TestCase;

class CookieStoreTest extends TestCase
{
    private function id(): string
    {
        return bin2hex(random_bytes(20));
    }

    public function test_it_round_trips_session_data_through_an_encrypted_value(): void
    {
        $store = new CookieStore(new Encryption());
        $id = $this->id();

        $value = $store->write($id, ['user' => 7]);

        $this->assertStringNotContainsString('user', $value);
        $this->assertSame(['id' => $id, 'data' => ['user' => 7]], $store->read($value));
    }

    public function test_it_rejects_tampered_garbage_and_legacy_values(): void
    {
        $store = new CookieStore(new Encryption());
        $value = $store->write($this->id(), ['user' => 7]);

        $this->assertNull($store->read(substr($value, 0, -4) . 'AAAA'));
        $this->assertNull($store->read('garbage'));
        $this->assertNull($store->read(base64_encode(random_bytes(64))));
    }

    public function test_it_rejects_expired_values(): void
    {
        $value = (new CookieStore(new Encryption(), -10))->write($this->id(), ['a' => 1]);

        $this->assertNull((new CookieStore(new Encryption()))->read($value));
    }

    public function test_it_refuses_to_emit_an_oversized_cookie(): void
    {
        $this->expectException(SessionException::class);

        (new CookieStore(new Encryption()))->write($this->id(), ['blob' => str_repeat('x', 5000)]);
    }

    public function test_the_overflow_error_names_the_largest_keys_but_not_their_values(): void
    {
        try {
            (new CookieStore(new Encryption()))->write($this->id(), ['small' => 'a', 'big_key' => str_repeat('secret', 900)]);
            $this->fail('Expected an overflow.');
        } catch (SessionException $e) {
            $this->assertStringContainsString('big_key', $e->getMessage());
            $this->assertStringNotContainsString('secret', $e->getMessage());
        }
    }

    public function test_it_is_client_side(): void
    {
        $this->assertTrue((new CookieStore(new Encryption()))->isClientSide());
    }
}
