<?php

namespace Tests\Support\Model;

use Phaseolies\Database\Entity\Attributes\SoftDeletes;
use Phaseolies\Database\Entity\Model;

#[SoftDeletes(column: 'removed_at')]
class MockSoftNote extends Model
{
    protected $table = 'notes';
    protected $primaryKey = 'id';
    protected $connection = 'default';
    protected $timeStamps = false;
    protected $creatable = ['article_id', 'body'];
}
