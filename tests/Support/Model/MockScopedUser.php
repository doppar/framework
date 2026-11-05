<?php

namespace Tests\Support\Model;

use Phaseolies\Database\Entity\Builder;
use Phaseolies\Database\Entity\Model;

class MockScopedUser extends Model
{
    protected $table      = 'scoped_users';
    protected $connection = 'default';
    protected $timeStamps = false;

    protected $creatable = ['name', 'role'];

    /**
     * Global-scope style override: hides rows with id <= 2
     *
     * @param string|null $connection
     * @return Builder
     */
    public static function query(?string $connection = null): Builder
    {
        return parent::query($connection)->where('id', '>', 2);
    }

    /**
     * Bind method, resolved as admin()
     *
     * @param Builder $query
     * @param string $role
     * @return Builder
     */
    public function __admin(Builder $query, string $role = 'admin'): Builder
    {
        return $query->where('role', $role);
    }
}
