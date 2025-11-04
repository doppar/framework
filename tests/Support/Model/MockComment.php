<?php

namespace Tests\Support\Model;

use Phaseolies\Database\Entity\Model;

class MockComment extends Model
{
    protected $table = 'comments';
    protected $primaryKey = 'id';
    protected $connection = 'default';

    public function post()
    {
        $this->setLastRelationType('bindTo');
        $this->setLastRelatedModel(MockPost::class);
        $this->setLastForeignKey('post_id');
        $this->setLastLocalKey('post_id');
        return $this->bindTo(MockPost::class, 'post_id', 'id');
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
