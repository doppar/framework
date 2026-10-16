<?php

namespace Tests\Support\Model;

use Phaseolies\Database\Entity\Casts\Attributes\ToInteger;
use Phaseolies\Database\Entity\Casts\Attributes\ToArray;
use Phaseolies\Database\Entity\Casts\Attributes\ToBoolean;
use Phaseolies\Database\Entity\Model;
use Phaseolies\Database\Temporal\Attributes\Temporal;

#[Temporal(suffix: '_history', branches: true)]
class MockBranchableRecord extends Model
{
    protected $table = 'branchable_records';
    protected $primaryKey = 'id';
    protected $connection = 'default';
    protected $timeStamps = true;
    protected $creatable = ['title', 'price', 'internal_note', 'metadata', 'enabled'];
    protected $unexposable = ['internal_note'];

    #[ToInteger]
    protected string $price;

    #[ToArray]
    protected array $metadata;

    #[ToBoolean]
    protected bool $enabled;
}
