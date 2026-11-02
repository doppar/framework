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
}
