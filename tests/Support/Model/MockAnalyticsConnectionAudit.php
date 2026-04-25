<?php

namespace Tests\Support\Model;

use Phaseolies\Database\Entity\Model;

class MockAnalyticsConnectionAudit extends Model
{
    protected $table = 'connection_audits';
    protected $primaryKey = 'id';
    protected $connection = 'analytics';
    protected $timeStamps = false;
    protected $creatable = ['record_id', 'message'];
}
