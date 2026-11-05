<?php

namespace Tests\Support\Model;

use Phaseolies\Database\Entity\Attributes\SoftDeletes;
use Phaseolies\Database\Entity\Model;
use Phaseolies\Database\Temporal\Attributes\Temporal;

#[Temporal]
#[SoftDeletes]
class MockSoftTemporalRecord extends Model
{
    protected $table = 'soft_temporal_records';
    protected $primaryKey = 'id';
    protected $connection = 'default';
    protected $timeStamps = true;
    protected $creatable = ['title'];
}
