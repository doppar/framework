<?php

namespace Tests\Application\Mock\Services;

use Phaseolies\DI\Attributes\Immutable;
use Phaseolies\DI\Concerns\EnforcesImmutability;

#[Immutable]
class MockPaymentService
{
    use EnforcesImmutability;

    public string $gateway  = 'stripe';
    public float  $taxRate  = 0.08;
    public bool   $liveMode = false;
    public int    $retries  = 3;
    public array  $methods  = ['card', 'bank'];

    public function charge(float $amount): array
    {
        $tax   = round($amount * $this->taxRate, 2);
        $total = round($amount + $tax, 2);
        return [
            'gateway' => $this->gateway,
            'amount'  => $amount,
            'tax'     => $tax,
            'total'   => $total,
            'status'  => 'charged',
        ];
    }

    public function getGateway(): string
    {
        return $this->gateway;
    }
    public function isLive(): bool
    {
        return $this->liveMode;
    }
    public function getRetries(): int
    {
        return $this->retries;
    }
}
