<?php

namespace Tests\Support\Model;

use Phaseolies\Database\Entity\Model;

class MockPrimaryConnectionRecord extends Model
{
    protected $table = 'connection_records';
    protected $primaryKey = 'id';
    protected $connection = 'primary';
    protected $timeStamps = false;
    protected $creatable = ['name', 'status', 'amount'];
}
