<?php

namespace Phaseolies\Session;

use Phaseolies\Session\Stores\FileStore;

final class RotationPointer
{
    /**
     * Key holding the id the session moved to.
     */
    public const TO = '_rotated_to';

    /**
     * Key holding the time until which the old id may still be followed.
     */
    public const UNTIL = '_rotated_until';

    /**
     * Build the data of a pointer.
     *
     * @param string $to
     * @param int $until
     * @return array
     */
    public static function make(string $to, int $until): array
    {
        return [self::TO => $to, self::UNTIL => $until];
    }

    /**
     * Whether the session data is a pointer rather than a session.
     *
     * @param array $data
     * @return bool
     */
    public static function isPointer(array $data): bool
    {
        return array_key_exists(self::TO, $data);
    }

    /**
     * The id a pointer leads to, while it is still allowed to.
     *
     * @param array $data
     * @param bool $honourExpiry
     * @return string|null
     */
    public static function target(array $data, bool $honourExpiry = true): ?string
    {
        $target = $data[self::TO] ?? null;

        if (!is_string($target) || !FileStore::isValidId($target)) {
            return null;
        }

        if ($honourExpiry && (int) ($data[self::UNTIL] ?? 0) < time()) {
            return null;
        }

        return $target;
    }
}
