<?php

namespace Tests\Support\Model;

use Phaseolies\Database\Entity\Model;

class MockAnotherUser extends Model
{
    protected $table = 'userss';
    protected $primaryKey = 'id';
    protected $connection = 'default';
    protected $timeStamps = true;
}
