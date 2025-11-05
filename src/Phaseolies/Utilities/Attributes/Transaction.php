<?php

namespace Phaseolies\Utilities\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD)]
final class Transaction
{
    public function __construct(
        public ?string $connection = null,
        public int $attempts = 1
    ) {}
}
