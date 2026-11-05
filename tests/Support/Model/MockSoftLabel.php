<?php

namespace Tests\Support\Model;

use Phaseolies\Database\Entity\Attributes\SoftDeletes;
use Phaseolies\Database\Entity\Model;

#[SoftDeletes]
class MockSoftLabel extends Model
{
    protected $table = 'labels';
    protected $primaryKey = 'id';
    protected $connection = 'default';
    protected $timeStamps = false;
    protected $creatable = ['name'];
}
