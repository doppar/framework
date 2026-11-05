<?php

namespace Tests\Support\Model;

use Phaseolies\Database\Entity\Model;
use Phaseolies\Database\Temporal\Attributes\Temporal;

#[Temporal(suffix: '_history', trackActor: true, branches: true)]
class MockMigratableRecord extends Model
{
    protected $table = 'migratable_records';
    protected $primaryKey = 'id';
    protected $connection = 'default';
    protected $timeStamps = false;
    protected $creatable = ['title'];
}
