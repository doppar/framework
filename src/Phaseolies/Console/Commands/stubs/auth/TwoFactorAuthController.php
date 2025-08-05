<?php

namespace App\Http\Controllers\Auth;

use Phaseolies\Utilities\Attributes\Middleware;
use Phaseolies\Support\Facades\Auth;
use Phaseolies\Http\Response;
use Phaseolies\Http\Request;
use App\Http\Middleware\GuestMiddleware;
use App\Http\Middleware\Authenticate;
use App\Http\Controllers\Controller;
use Phaseolies\Http\Response\RedirectResponse;

class TwoFactorAuthController extends Controller
{
    /**
     * Display the profile page view
     *
     * @return Response
     */
    #[Middleware([Authenticate::class])]
    public function toggle2FA(Request $request): Response
    {
        $qrCodeImage = null;
        $secret = null;
        $recoveryCodes = [];

        $enabling = (bool) $request->is_enabled_request;

        if ($enabling) {
            $twoFactorData = Auth::enableTwoFactorAuth();
            $qrCodeImage = Auth::generateTwoFactorQrCode($twoFactorData['qr_code_url']);
            $secret = $twoFactorData['secret'];
            $recoveryCodes = $twoFactorData['recovery_codes'];
            session()->flash('success', '2FA has been enabled successfully');
        } else {
            Auth::disableTwoFactorAuth();
            return redirect('/profile')->withSuccess('2FA has been disabled successfully');
        }

        return view('profile', compact('qrCodeImage', 'secret', 'recoveryCodes'));
    }

    /**
     * Show the verify 2FA form view.
     *
     * @return Response
     */
    #[Middleware([GuestMiddleware::class])]
    public function index(): Response
    {
        return view('auth.2fa');
    }

    /**
     * Verify the requested 2FA token
     *
     * @param Request $request
     * @return RedirectResponse
     */
    #[Middleware([GuestMiddleware::class])]
    public function verifyToken(Request $request): RedirectResponse
    {
        if (Auth::verifyTwoFactorCode($request->token)) {
            if (Auth::completeTwoFactorLogin()) {
                return redirect()->intended('/home')->withSuccess('You are logged in');
            }

            return back()->withError('Something went wrong');
        }

        return back()->withError('Invalid code');
    }
}
