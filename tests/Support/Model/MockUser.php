<?php

namespace Tests\Support\Model;

use Phaseolies\Database\Entity\Model;

class MockUser extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $connection = 'default';
    protected $timeStamps = false;

    public function posts()
    {
        $this->setLastRelationType('linkMany');
        $this->setLastRelatedModel(MockPost::class);
        $this->setLastForeignKey('user_id');
        $this->setLastLocalKey('id');
        return $this->linkMany(MockPost::class, 'user_id', 'id');
    }

    public function comments()
    {
        $this->setLastRelationType('linkMany');
        $this->setLastRelatedModel(MockComment::class);
        $this->setLastForeignKey('user_id');
        $this->setLastLocalKey('id');
        return $this->linkMany(MockComment::class, 'user_id', 'id');
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
