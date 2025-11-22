<?php

namespace Tests\Support\Model;

use Phaseolies\Database\Entity\Model;

class MockRole extends Model
{
    protected $table = 'roles';

    protected $primaryKey = 'id';

    protected $connection = 'default';
}
