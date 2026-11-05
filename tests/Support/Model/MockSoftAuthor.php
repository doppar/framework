<?php

namespace Tests\Support\Model;

use Phaseolies\Database\Entity\Model;

class MockSoftAuthor extends Model
{
    protected $table = 'authors';
    protected $primaryKey = 'id';
    protected $connection = 'default';
    protected $timeStamps = false;
    protected $creatable = ['name'];

    public function articles()
    {
        return $this->linkMany(MockSoftArticle::class, 'author_id', 'id');
    }
}
