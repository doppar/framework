<?php

namespace Tests\Support\Model;

use Phaseolies\Database\Entity\Model;

class MockPost extends Model
{
    protected $table = 'hooks';

    protected $primaryKey = 'id';

    protected $connection = 'default';

    protected $timeStamps = false;

    protected $creatable = ['name'];
}
