<?php

namespace Phaseolies\Support\Facades;

/**
 * @method static try(array $credentials = [], bool $remember = false): bool
 * @method static login(User $user, bool $remember = false): void
 * @method static loginUsingId(int $id, bool $remember = false): ?User
 * @method static onceUsingId(int $id): ?User
 * @method static user(): ?User
 * @method static check(): bool
 * @method static logout()
 * @method static id(): ?int
 * @method static enableTwoFactorAuth(): array
 * @method static disableTwoFactorAuth(): bool
 * @method static verifyTwoFactorCode(string $code): bool
 * @method static verifyRecoveryCode(Model $user, string $code): bool
 * @method static generateNewRecoveryCodes(): array
 * @method static hasTwoFactorEnabled(Model $user): bool
 * @method static completeTwoFactorLogin(): bool
 * @method static generateTwoFactorQrCode(string $qrCodeUrl): string
 *
 * @see \Phaseolies\Auth\Security\Authenticate
 */

use Phaseolies\Facade\BaseFacade;

class Auth extends BaseFacade
{
    protected static function getFacadeAccessor()
    {
        return 'auth';
    }
}
