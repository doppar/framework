<?php

namespace Tests\Support\Model;

use Phaseolies\Database\Entity\Model;
use Phaseolies\Database\Temporal\Attributes\Temporal;

#[Temporal(suffix: '_audit', trackActor: true)]
class MockTemporalRecord extends Model
{
    protected $table = 'temporal_records';
    protected $primaryKey = 'id';
    protected $connection = 'default';
    protected $timeStamps = true;
    protected $creatable = ['title', 'status', 'body'];
}
