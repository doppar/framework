<?php

namespace Tests\Support\Model;

use Phaseolies\Database\Entity\Model;

class MockAnalyticsConnectionRecord extends Model
{
    protected $table = 'connection_records';
    protected $primaryKey = 'id';
    protected $connection = 'analytics';
    protected $timeStamps = false;
    protected $creatable = ['name', 'status', 'amount'];
}
