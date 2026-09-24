<?php

namespace Phaseolies\Support\Facades;

use Phaseolies\Facade\BaseFacade;

/**
 * @method static try(array $credentials = [], bool $remember = false): bool
 * @method static login(\Phaseolies\Auth\Contracts\Authenticatable $user, bool $remember = false): void
 * @method static loginUsingId(int $id, bool $remember = false): ?\Phaseolies\Auth\Contracts\Authenticatable
 * @method static onceUsingId(int $id): ?\Phaseolies\Auth\Contracts\Authenticatable
 * @method static user(): ?\Phaseolies\Auth\Contracts\Authenticatable
 * @method static check(): bool
 * @method static logout()
 * @method static id(): ?int
 * @method static enableTwoFactorAuth(): array
 * @method static disableTwoFactorAuth(): bool
 * @method static verifyTwoFactorCode(string $code): bool
 * @method static verifyRecoveryCode(\Phaseolies\Database\Entity\Model $user, string $code): bool
 * @method static generateNewRecoveryCodes(): array
 * @method static hasTwoFactorEnabled(\Phaseolies\Database\Entity\Model $user): bool
 * @method static completeTwoFactorLogin(): bool
 * @method static generateTwoFactorQrCode(string $qrCodeUrl): string
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
