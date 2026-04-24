<?php

namespace Tests\Support\Model;

use Phaseolies\Database\Entity\Model;
use Phaseolies\Database\Temporal\Attributes\Temporal;

#[Temporal]
class MockTemporalDefaultRecord extends Model
{
    protected $table = 'temporal_default_records';
    protected $primaryKey = 'id';
    protected $connection = 'default';
    protected $timeStamps = false;
    protected $creatable = ['title'];
}
