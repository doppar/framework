<?php

namespace Phaseolies\Auth\Security;

use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\Common\EccLevel;
use Symfony\Component\Clock\NativeClock;
use Psr\Clock\ClockInterface;
use Phaseolies\Support\Facades\Crypt;
use Phaseolies\Auth\Authable;
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
     * @return array
     */
    public function enableTwoFactorAuth(): array
    {
        $user = $this->user();

        if (!is_null($user->getTwoFactorSecret())) {
            throw new \Exception("2FA Already enabled");
        }

        $secret = Base32::encodeUpper(random_bytes(20));

        $totp = TOTP::create(
            $secret,
            30,
            'sha1',
            6,
            $this->id(),
            $this->getClock()
        );

        $host   = parse_url(config('app.url'), PHP_URL_HOST);
        $issuer = preg_replace('/[^a-zA-Z0-9.\-_]/', '', $host);

        $totp->setLabel(strtolower(trim(config('app.name'))));
        $totp->setIssuer($issuer);

        $recoveryCodes = $this->generateRecoveryCodes();

        $user->setTwoFactorSecret(Crypt::encrypt($secret));
        $user->setTwoFactorRecoveryCodes(Crypt::encrypt(json_encode($recoveryCodes)));
        $user->save();

        return [
            'secret'         => $secret,
            'qr_code_url'    => $totp->getProvisioningUri(),
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
        $user = $this->user();

        $user->setTwoFactorSecret(null);
        $user->setTwoFactorRecoveryCodes(null);

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
        $authModel = $this->getModel();
        $user      = $authModel::find(session($this->getTwoFactorUserKey()));

        if (is_null($user->getTwoFactorSecret())) {
            return false;
        }

        try {
            $secret = Crypt::decrypt($user->getTwoFactorSecret());

            $totp = TOTP::create(
                $secret,
                30,
                'sha1',
                6,
                session($this->getTwoFactorUserKey()),
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
     * @param Authable $user
     * @param string $code
     * @return bool
     */
    public function verifyRecoveryCode(Authable $user, string $code): bool
    {
        if (is_null($user->getTwoFactorRecoveryCodes())) {
            return false;
        }

        $codes = Crypt::decrypt($user->getTwoFactorRecoveryCodes());

        foreach ($codes as $key => $recoveryCode) {
            if (strtoupper(trim($code)) === $recoveryCode) {
                unset($codes[$key]);

                if (!empty($codes)) {
                    $user->setTwoFactorRecoveryCodes(Crypt::encrypt(json_encode($codes)));
                } else {
                    $user->setTwoFactorRecoveryCodes(null);
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

        $user = $this->user();
        $user->setTwoFactorRecoveryCodes(Crypt::encrypt(json_encode($recoveryCodes)));
        $user->save();

        return $recoveryCodes;
    }

    /**
     * Check if user has 2FA enabled
     *
     * @param Authable $user
     * @return bool
     */
    public function hasTwoFactorEnabled(Authable $user): bool
    {
        return !is_null($user->getTwoFactorSecret());
    }

    /**
     * Complete 2FA login after code verification
     *
     * @return bool
     */
    public function completeTwoFactorLogin(): bool
    {
        $authModel = $this->getModel();
        $user      = $authModel::find(session($this->getTwoFactorUserKey()));
        $remember  = session($this->getTwoFactorRememberKey());

        $this->setUser($user);

        session()->forget($this->getTwoFactorUserKey());
        session()->forget($this->getTwoFactorRememberKey());

        if ($remember) {
            if (!$this->viaRemember()) {
                $this->setRememberToken($user);
            }
        }

        return true;
    }

    /**
     * Generate QR code image for 2FA setup
     *
     * @param string $qrCodeUrl
     * @param int $size
     * @return string
     */
    public function generateTwoFactorQrCode(string $qrCodeUrl): string
    {
        $options = new QROptions([
            'version'         => 10,
            'outputInterface' => QRMarkupSVG::class,
            'eccLevel'        => EccLevel::M,
            'addQuietzone'    => true,
        ]);

        return (new QRCode($options))->render($qrCodeUrl);
    }
}
