<?php

namespace Tests\Support\Model;

use Phaseolies\Database\Entity\Model;

class MockPost extends Model
{
    protected $table = 'posts';

    protected $primaryKey = 'id';

    protected $connection = 'default';

    public function comments()
    {
        $this->setLastRelationType('linkMany');
        $this->setLastRelatedModel(MockComment::class);
        $this->setLastForeignKey('post_id');
        $this->setLastLocalKey('id');
        return $this->linkMany(MockComment::class, 'post_id', 'id');
    }

    public function user()
    {
        $this->setLastRelationType('bindTo');
        $this->setLastRelatedModel(MockUser::class);
        $this->setLastForeignKey('user_id');
        $this->setLastLocalKey('id');
        return $this->bindTo(MockUser::class, 'id', 'user_id');
    }

    public function likes()
    {
        $this->setLastRelationType('linkMany');
        $this->setLastRelatedModel(MockLike::class);
        $this->setLastForeignKey('post_id');
        $this->setLastLocalKey('id');
        return $this->linkMany(MockLike::class, 'post_id', 'id');
    }

    public function tags()
    {
        $this->setLastRelationType('bindToMany');
        $this->setLastRelatedModel(MockTag::class);
        $this->setLastForeignKey('post_id');
        $this->setLastRelatedKey('tag_id');
        $this->setLastPivotTable('post_tag');
        $this->setLastLocalKey('id');
        return $this->bindToMany(MockTag::class, 'post_id', 'tag_id', 'post_tag');
    }

    protected function setLastRelatedKey($key)
    {
        $this->lastRelatedKey = $key;
    }
    protected function setLastPivotTable($table)
    {
        $this->lastPivotTable = $table;
    }

    protected function setLastRelationType($type)
    {
        $this->lastRelationType = $type;
    }
    protected function setLastRelatedModel($model)
    {
        $this->lastRelatedModel = $model;
    }
    protected function setLastForeignKey($key)
    {
        $this->lastForeignKey = $key;
    }
    protected function setLastLocalKey($key)
    {
        $this->lastLocalKey = $key;
    }

    public function getLastRelationType(): string
    {
        return $this->lastRelationType ?? '';
    }
    public function getLastRelatedModel(): string
    {
        return $this->lastRelatedModel ?? '';
    }
    public function getLastForeignKey(): string
    {
        return $this->lastForeignKey ?? '';
    }
    public function getLastLocalKey(): string
    {
        return $this->lastLocalKey ?? '';
    }
}
