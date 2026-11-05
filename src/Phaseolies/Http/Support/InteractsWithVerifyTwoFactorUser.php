<?php

namespace Phaseolies\Http\Support;

use Phaseolies\Session\Contracts\SessionInterface;

trait InteractsWithVerifyTwoFactorUser
{
    /**
     * Validate a 2FA token stored in the session.
     *
     * @param SessionInterface $session
     * @return array|false
     */
    protected function validateTwoFactorSession(SessionInterface $session): array|false
    {
        if (!$session->has('2fa_token')) {
            return false;
        }

        $token = $session->get('2fa_token');
        $segments = explode('|', $token);

        if (count($segments) !== 4) {
            return false;
        }

        [$id, $rawToken, $signature, $timestamp] = $segments;

        if ((time() - (int) $timestamp) > 300) {
            $session->forget('2fa_token');
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $id . '|' . $rawToken . '|' . $timestamp, config('app.key'));

        if (!hash_equals($expectedSignature, $signature)) {
            $session->forget('2fa_token');
            return false;
        }

        return [$id, $rawToken];
    }
}
