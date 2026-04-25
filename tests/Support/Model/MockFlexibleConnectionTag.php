<?php

namespace Tests\Support\Model;

use Phaseolies\Database\Entity\Model;

class MockFlexibleConnectionTag extends Model
{
    protected $table = 'connection_tags';
    protected $primaryKey = 'id';
    protected $connection = null;
    protected $timeStamps = false;
    protected $creatable = ['record_id', 'name'];
}
