<?php

namespace Phaseolies\Support\Facades;

use Phaseolies\Facade\BaseFacade;
use Phaseolies\Auth\Contracts\Authable;
use Phaseolies\Database\Entity\Model;

/**
 * @method static bool try(array $credentials = [], bool $remember = false)
 * @method static void login(Authable $user, bool $remember = false)
 * @method static Authable|null loginUsingId(int $id, bool $remember = false)
 * @method static Authable|null onceUsingId(int $id)
 * @method static Authable|null user()
 * @method static bool check()
 * @method static void logout()
 * @method static int|null id()
 * @method static array enableTwoFactorAuth()
 * @method static bool disableTwoFactorAuth()
 * @method static bool verifyTwoFactorCode(string $code)
 * @method static bool verifyRecoveryCode(Model $user, string $code)
 * @method static array generateNewRecoveryCodes()
 * @method static bool hasTwoFactorEnabled(Model $user)
 * @method static bool completeTwoFactorLogin()
 * @method static string generateTwoFactorQrCode(string $qrCodeUrl)
 *
 * @see \Phaseolies\Auth\Security\Authenticate
 */
class Auth extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'auth';
    }
}
