<?php

namespace Tests\Support\Model;

use Phaseolies\Database\Entity\Model;

class MockLike extends Model
{
    protected $table = 'likes';

    protected $primaryKey = 'id';

    protected $connection = 'default';
}
