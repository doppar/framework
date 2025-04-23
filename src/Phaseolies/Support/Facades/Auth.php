<?php

namespace Phaseolies\Support\Facades;

/**
 * @method static \Phaseolies\Auth\Security\Authenticate try(array $credentials = [], bool $remember = false): bool
 * @method static \Phaseolies\Auth\Security\Authenticate login(User $user, bool $remember = false): void
 * @method static \Phaseolies\Auth\Security\Authenticate loginUsingId(int $id, bool $remember = false): ?User
 * @method static \Phaseolies\Auth\Security\Authenticate onceUsingId(int $id): ?User
 * @method static \Phaseolies\Auth\Security\Authenticate user(): ?User
 * @method static \Phaseolies\Auth\Security\Authenticate check(): bool
 * @method static \Phaseolies\Auth\Security\Authenticate logout()
 * @method static \Phaseolies\Auth\Security\Authenticate id(): ?int
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
