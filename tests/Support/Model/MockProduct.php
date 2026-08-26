<?php

namespace Tests\Support\Model;

use Phaseolies\Database\Entity\Model;

class MockProduct extends Model
{
    protected $table = 'product';

    protected $primaryKey = 'id';

    protected $connection = 'default';

    protected $timeStamps = false;

    protected $creatable = ['price'];
}
