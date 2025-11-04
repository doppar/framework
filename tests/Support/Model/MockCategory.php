<?php

namespace Tests\Support\Model;

use Phaseolies\Database\Entity\Model;

class MockCategory extends Model
{
    protected $table = 'categories';

    protected $primaryKey = 'id';

    protected $connection = 'default';
}
