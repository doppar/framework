<?php

namespace Tests\Unit\Session;

use Phaseolies\Http\Request;
use Phaseolies\Middleware\CsrfTokenMiddleware;
use Phaseolies\Session\Stores\ArrayStore;
use Phaseolies\Support\Session;
use PHPUnit\Framework\TestCase;

class CsrfCookieTest extends TestCase
{
    private function session(array $config = []): Session
    {
        return new Session(new ArrayStore(), $config + [
            'cookie' => 'test_session',
            'lifetime' => 120,
            'stateless' => [],
            'lottery' => [0, 100],
        ]);
    }

    private function request(Session $session): Request
    {
        $request = new Request();
        $request->session = $session;
        $session->attach($request);

        return $request;
    }

    private function decide(Request $request, bool $always = false): bool
    {
        $middleware = new class($always) extends CsrfTokenMiddleware {
            public function __construct(private bool $always)
            {
            }

            protected function alwaysSendsCookie(): bool
            {
                return $this->always;
            }

            public function decide($request): bool
            {
                return $this->shouldIssueCookie($request);
            }
        };

        return $middleware->decide($request);
    }

    public function test_no_cookie_is_sent_before_a_token_exists(): void
    {
        $session = $this->session();

        $this->assertFalse($this->decide($this->request($session)));
        $this->assertFalse($session->has('_token'), 'Deciding must not create the token.');
    }

    public function test_the_cookie_is_sent_once_a_token_exists(): void
    {
        $session = $this->session();
        $session->token();

        $this->assertTrue($this->decide($this->request($session)));
    }

    public function test_the_always_send_option_sends_the_cookie_on_the_first_response(): void
    {
        $session = $this->session();

        $this->assertTrue($this->decide($this->request($session), always: true));
    }

    public function test_stateless_requests_never_get_the_cookie_even_with_the_option_on(): void
    {
        $session = $this->session(['stateless' => ['api/*']]);
        $request = $this->request($session);
        $session->disable();

        $this->assertFalse($this->decide($request, always: true));
    }
}
