<?php

namespace Tests\Unit\Session;

use Phaseolies\Session\SessionManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SessionManagerTest extends TestCase
{
    #[DataProvider('names')]
    public function test_cookie_names_survive_phps_cookie_name_mangling(string $input, string $expected): void
    {
        $name = SessionManager::cookieName($input);

        $this->assertSame($expected, $name);

        // PHP turns dots and spaces into underscores when it builds $_COOKIE,
        // so the name must come back unchanged after that rewrite.
        $this->assertSame($name, str_replace(['.', ' '], '_', $name));
    }

    public static function names(): array
    {
        return [
            'plain' => ['doppar_session', 'doppar_session'],
            'space' => ['my app_session', 'my_app_session'],
            'dot' => ['my.app_session', 'my_app_session'],
            'hyphen kept' => ['my-app_session', 'my-app_session'],
            'unicode and separators' => ["caf\u{e9} \"x\";y", 'caf_x_y'],
            'empty falls back' => ['', 'doppar_session'],
            'only junk falls back' => ['   ', 'doppar_session'],
        ];
    }

    #[DataProvider('lotteries')]
    public function test_the_lottery_option_is_normalized(mixed $input, array $expected): void
    {
        $this->assertSame($expected, SessionManager::lottery($input));
        $this->assertSame($expected, SessionManager::normalize(['lottery' => $input])['lottery']);
    }

    public static function lotteries(): array
    {
        return [
            'default' => [[2, 100], [2, 100]],
            'null never sweeps' => [null, [0, 100]],
            'false never sweeps' => [false, [0, 100]],
            'empty array never sweeps' => [[], [0, 100]],
            'zero chances never sweeps' => [[0, 100], [0, 100]],
            'not an array never sweeps' => ['2/100', [0, 100]],
            'a single number keeps the default total' => [[5], [5, 100]],
            'a bad total falls back' => [[2, 0], [2, 100]],
            'negative chances never sweep' => [[-3, 100], [0, 100]],
            'string numbers are read' => [['3', '50'], [3, 50]],
            'every request' => [[1, 1], [1, 1]],
        ];
    }

    public function test_a_config_without_a_lottery_gets_the_default(): void
    {
        $this->assertSame([2, 100], SessionManager::normalize([])['lottery']);
    }
}
