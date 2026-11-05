<?php

namespace Tests\Support\Model;

use Phaseolies\Database\Entity\Attributes\SoftDeletes;
use Phaseolies\Database\Entity\Model;

#[SoftDeletes]
class MockSoftArticle extends Model
{
    protected $table = 'articles';
    protected $primaryKey = 'id';
    protected $connection = 'default';
    protected $timeStamps = true;
    protected $creatable = ['author_id', 'title', 'views'];

    public function author()
    {
        return $this->bindTo(MockSoftAuthor::class, 'id', 'author_id');
    }

    public function notes()
    {
        return $this->linkMany(MockSoftNote::class, 'article_id', 'id');
    }

    public function labels()
    {
        return $this->bindToMany(MockSoftLabel::class, 'article_id', 'label_id', 'article_label');
    }
}
