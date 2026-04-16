<?php

namespace Tests\Support\Model;

use Phaseolies\Database\Entity\Model;
use Phaseolies\Database\Entity\Attributes\Computed;

class MockComputedUser extends Model
{
    protected $table      = 'users';
    protected $connection = 'default';
    protected $timeStamps = false;

    protected $unexposable = ['password'];

    #[Computed]
    public function fullName(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    #[Computed]
    public function initials(): string
    {
        return strtoupper(
            substr($this->first_name, 0, 1) . substr($this->last_name, 0, 1)
        );
    }

    #[Computed]
    public function emailDomain(): string
    {
        return substr(strrchr($this->email, '@'), 1);
    }

    public function notComputed(): string
    {
        return 'plain method';
    }
}
