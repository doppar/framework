<?php

namespace Tests\Support\Model;

use Phaseolies\Database\Entity\Model;
use Phaseolies\Database\Entity\Attributes\Watches;
use Tests\Support\Watches\SpyListener;
use Tests\Support\Watches\AnotherSpyListener;
use Tests\Support\Watches\FailCondition;

class MockWatchableOrder extends Model
{
    protected $table      = 'orders';
    protected $primaryKey = 'id';
    protected $connection = 'default';
    protected $timeStamps = false;
    protected $creatable  = ['status', 'total', 'notes'];

    /** Fires SpyListener on every status change */
    #[Watches(SpyListener::class)]
    protected $status;

    /** Fires SpyListener only when $new > 10 000; AnotherSpyListener always */
    #[Watches(SpyListener::class, when: 'isFraudRisk')]
    #[Watches(AnotherSpyListener::class)]
    protected $total;

    /** FailCondition always returns false → SpyListener never fires for notes */
    #[Watches(SpyListener::class, when: FailCondition::class)]
    protected $notes;

    /**
     * Used as a method-based condition on $total.
     * Fires only when the new total exceeds 10 000.
     */
    public function isFraudRisk(mixed $old, mixed $new): bool
    {
        return (float) $new > 10_000;
    }
}
