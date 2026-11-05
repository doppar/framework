<?php

namespace Phaseolies\Session\Contracts;

interface UserSessionStore extends SessionStoreInterface
{
    /**
     * The live sessions of a user, most recently active first.
     *
     * @param int|string $userId
     * @return array<int, array{id: string, ip_address: string|null, user_agent: string|null, last_activity: int}>
     */
    public function userSessions(int|string $userId): array;

    /**
     * End every session of a user, except the one given.
     *
     * @param int|string $userId
     * @param string|null $exceptId
     * @return int Number of ended sessions
     */
    public function destroyUserSessions(int|string $userId, ?string $exceptId = null): int;
}
