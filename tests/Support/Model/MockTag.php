<?php

namespace Tests\Support\Model;

use Phaseolies\Database\Entity\Model;

class MockTag extends Model
{
    protected $table = 'tags';

    protected $primaryKey = 'id';

    protected $connection = 'default';

    protected $timeStamps = false;

    protected $creatable = ['name'];
}
