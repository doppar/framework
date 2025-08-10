<?php

namespace Phaseolies\Auth\Security;

use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\QRCode;
use Symfony\Component\Clock\NativeClock;
use Psr\Clock\ClockInterface;
use Phaseolies\Support\Facades\Crypt;
use Phaseolies\Support\Facades\Auth;
use Phaseolies\Database\Eloquent\Model;
use ParagonIE\ConstantTime\Base32;
use OTPHP\TOTP;

trait InteractsWithTwoFactorAuth
{
    /**
     * Get a clock instance for TOTP
     *
     * @return ClockInterface
     */
    protected function getClock(): ClockInterface
    {
        return new NativeClock();
    }

    /**
     * Enable 2FA for the current user
     *
     * @return array Contains secret, QR code URL, and recovery codes
     */
    public function enableTwoFactorAuth(): array
    {
        $user = Auth::user();

        if (!is_null($user->two_factor_secret)) {
            throw new \Exception("2FA Already enabled");
        }

        $secret = Base32::encodeUpper(random_bytes(20));

        $totp = TOTP::create(
            $secret,
            30,
            'sha1',
            6,
            auth()->id(),
            $this->getClock()
        );

        $host = parse_url(config('app.url'), PHP_URL_HOST);
        $issuer = preg_replace('/[^a-zA-Z0-9.\-_]/', '', $host);

        $totp->setLabel(strtolower(trim(config('app.name'))));
        $totp->setIssuer($issuer);

        $recoveryCodes = $this->generateRecoveryCodes();

        $user->two_factor_secret = Crypt::encrypt($secret);
        $user->two_factor_recovery_codes = Crypt::encrypt(json_encode($recoveryCodes));
        $user->save();

        return [
            'secret' => $secret,
            'qr_code_url' => $totp->getProvisioningUri(),
            'recovery_codes' => $recoveryCodes,
        ];
    }

    /**
     * Disable 2FA for the current user
     *
     * @return bool
     */
    public function disableTwoFactorAuth(): bool
    {
        $user = Auth::user();

        $user->two_factor_secret = null;
        $user->two_factor_recovery_codes = null;

        return $user->save();
    }

    /**
     * Generate recovery codes
     *
     * @return array
     */
    protected function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(5)));
        }

        return $codes;
    }

    /**
     * Verify a 2FA code
     *
     * @param string $code
     * @return bool
     */
    public function verifyTwoFactorCode(string $code): bool
    {
        $authModel = app(config('auth.model'));
        $user = $authModel::find(session('2fa_user_id'));

        if (is_null($user->two_factor_secret)) {
            return false;
        }

        try {
            $secret = Crypt::decrypt($user->two_factor_secret);

            $totp = TOTP::create(
                $secret,
                30,
                'sha1',
                6,
                session('2fa_user_id'),
                $this->getClock()
            );

            return $totp->verify($code, null, 1);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Verify a recovery code
     *
     * @param Model $user
     * @param string $code
     * @return bool
     */
    public function verifyRecoveryCode(Model $user, string $code): bool
    {
        if (is_null($user->two_factor_recovery_codes)) {
            return false;
        }

        $codes = Crypt::decrypt($user->two_factor_recovery_codes);

        foreach ($codes as $key => $recoveryCode) {
            if (strtoupper(trim($code)) === $recoveryCode) {
                unset($codes[$key]);

                if (!empty($codes)) {
                    $user->two_factor_recovery_codes = Crypt::encrypt(json_encode($codes));
                } else {
                    $user->two_factor_recovery_codes = null;
                }

                $user->save();
                return true;
            }
        }

        return false;
    }

    /**
     * Generate new recovery codes
     *
     * @return array
     */
    public function generateNewRecoveryCodes(): array
    {
        $recoveryCodes = $this->generateRecoveryCodes();

        $user = Auth::user();
        $user->two_factor_recovery_codes = Crypt::encrypt(json_encode($recoveryCodes));
        $user->save();

        return $recoveryCodes;
    }

    /**
     * Check if user has 2FA enabled
     *
     * @param Model $user
     * @return bool
     */
    public function hasTwoFactorEnabled(Model $user): bool
    {
        return !is_null($user->two_factor_secret);
    }

    /**
     * Complete 2FA login after code verification
     *
     * @return bool
     */
    public function completeTwoFactorLogin(): bool
    {
        $authModel = app(config('auth.model'));
        $user = $authModel::find(session('2fa_user_id'));
        $remember = session('2fa_remember');

        $this->setUser($user);

        session()->forget('2fa_user_id');
        session()->forget('2fa_remember');

        if ($remember) {
            $this->setRememberToken($user);
        }

        return true;
    }

    /**
     * Generate QR code image for 2FA setup
     *
     * @param string $qrCodeUrl The TOTP provisioning URL
     * @param int $size QR code size in pixels (default 200)
     * @return string Base64 encoded PNG image data
     */
    public function generateTwoFactorQrCode(string $qrCodeUrl): string
    {
        $options = new QROptions([
            'version'      => 10,
            'outputType'  => QRCode::OUTPUT_MARKUP_SVG,
            'eccLevel'    => QRCode::ECC_M,
            'addQuietzone' => true,
        ]);

        return (new QRCode($options))->render($qrCodeUrl);
    }
}
