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
     * End every session of a user, except the ones given and what they were rotated into.
     *
     * @param int|string $userId
     * @param array<int, string> $keep
     * @return int
     */
    public function destroyUserSessions(int|string $userId, array $keep = []): int;
}
