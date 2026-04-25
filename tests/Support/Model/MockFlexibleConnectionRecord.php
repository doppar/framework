<?php

namespace Tests\Support\Model;

use Phaseolies\Database\Entity\Model;

class MockFlexibleConnectionRecord extends Model
{
    protected $table = 'connection_records';
    protected $primaryKey = 'id';
    protected $connection = null;
    protected $timeStamps = false;
    protected $creatable = ['name', 'status', 'amount'];
}
