<?php

namespace Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;

class EnvHelperTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_ENV['ENV_HELPER_TEST_KEY'], $_SERVER['ENV_HELPER_TEST_KEY']);
        putenv('ENV_HELPER_TEST_KEY');
    }

    public function testFalseValueInEnvIsReturnedAsIs(): void
    {
        // A real `false` (e.g. sourced from env.toml) must not be confused
        // with getenv()'s "key not found" sentinel, which is also false.
        $_ENV['ENV_HELPER_TEST_KEY'] = false;

        $this->assertSame(false, env('ENV_HELPER_TEST_KEY', 'default'));
    }

    public function testFalseValueInServerIsReturnedAsIs(): void
    {
        $_SERVER['ENV_HELPER_TEST_KEY'] = false;

        $this->assertSame(false, env('ENV_HELPER_TEST_KEY', 'default'));
    }

    public function testZeroValueInEnvIsReturnedAsIs(): void
    {
        $_ENV['ENV_HELPER_TEST_KEY'] = 0;

        $this->assertSame(0, env('ENV_HELPER_TEST_KEY', 'default'));
    }

    public function testMissingKeyReturnsDefault(): void
    {
        $this->assertSame('default', env('ENV_HELPER_TEST_KEY_DOES_NOT_EXIST', 'default'));
        $this->assertNull(env('ENV_HELPER_TEST_KEY_DOES_NOT_EXIST'));
    }

    public function testRealProcessEnvironmentVariableIsReturned(): void
    {
        putenv('ENV_HELPER_TEST_KEY=from-getenv');

        $this->assertSame('from-getenv', env('ENV_HELPER_TEST_KEY'));
    }
}
