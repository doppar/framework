<?php

namespace Phaseolies\Auth\Security {
    function config(string $key, mixed $default = null): mixed
    {
        $map = $GLOBALS['doppar_hashing_test_config'] ?? [
            'hashing.driver'        => 'bcrypt',
            'hashing.bcrypt.rounds' => 10,
        ];
        return array_key_exists($key, $map) ? $map[$key] : $default;
    }
}

namespace Tests\Unit\Auth {

    use Phaseolies\Auth\Security\PasswordHashing;
    use PHPUnit\Framework\TestCase;
    use RuntimeException;

    class PasswordHashingTest extends TestCase
    {
        private PasswordHashing $hashing;

        protected function setUp(): void
        {
            $GLOBALS['doppar_hashing_test_config'] = [
                'hashing.driver' => 'bcrypt',
                'hashing.bcrypt.rounds' => 10,
            ];
            $this->hashing = new PasswordHashing();
        }

        protected function tearDown(): void
        {
            unset($GLOBALS['doppar_hashing_test_config']);
        }

        public function testMakeReturnsBcryptHash()
        {
            $hash = $this->hashing->make('secret123');

            $this->assertNotEmpty($hash);
            $this->assertTrue(str_starts_with($hash, '$2y$'));
        }

        public function testMakeProducesDifferentHashesForSamePassword()
        {
            $hash1 = $this->hashing->make('same_password');
            $hash2 = $this->hashing->make('same_password');

            $this->assertNotEquals($hash1, $hash2);
        }

        public function testCheckReturnsTrueForCorrectPassword()
        {
            $hash = $this->hashing->make('my_password');

            $this->assertTrue($this->hashing->check('my_password', $hash));
        }

        public function testCheckReturnsFalseForWrongPassword()
        {
            $hash = $this->hashing->make('correct_password');

            $this->assertFalse($this->hashing->check('wrong_password', $hash));
        }

        public function testCheckReturnsFalseForEmptyPassword()
        {
            $hash = $this->hashing->make('some_password');

            $this->assertFalse($this->hashing->check('', $hash));
        }

        public function testCheckWorksWithPasswordVerify()
        {
            $raw      = 'test_raw_password';
            $bcrypt   = password_hash($raw, PASSWORD_BCRYPT, ['cost' => 10]);

            $this->assertTrue($this->hashing->check($raw, $bcrypt));
        }

        public function testNeedsRehashReturnsFalseForCurrentHash()
        {
            $hash = $this->hashing->make('password');

            $this->assertFalse($this->hashing->needsRehash($hash));
        }

        public function testNeedsRehashReturnsTrueForLowerCostHash()
        {
            $lowCostHash = password_hash('password', PASSWORD_BCRYPT, ['cost' => 4]);

            $this->assertTrue($this->hashing->needsRehash($lowCostHash));
        }

        public function testMakeReturnsStringType()
        {
            $hash = $this->hashing->make('password');

            $this->assertIsString($hash);
        }

        public function testHashLengthIsReasonable()
        {
            $hash = $this->hashing->make('password');

            $this->assertGreaterThan(50, strlen($hash));
        }

        public function testMakeThrowsForInvalidBcryptRounds()
        {
            $GLOBALS['doppar_hashing_test_config'] = [
                'hashing.driver' => 'bcrypt',
                'hashing.bcrypt.rounds' => 3,
            ];

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Bcrypt rounds must be between 4 and 31.');

            $this->hashing->make('password');
        }

        public function testMakeThrowsForUnsupportedDriver()
        {
            $GLOBALS['doppar_hashing_test_config'] = [
                'hashing.driver' => 'sha1',
            ];

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Unsupported hashing driver: sha1');

            $this->hashing->make('password');
        }

        public function testNeedsRehashThrowsForUnsupportedDriver()
        {
            $GLOBALS['doppar_hashing_test_config'] = [
                'hashing.driver' => 'sha1',
            ];

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Unsupported hashing driver: sha1');

            $this->hashing->needsRehash(password_hash('password', PASSWORD_BCRYPT, ['cost' => 10]));
        }
    }
}
