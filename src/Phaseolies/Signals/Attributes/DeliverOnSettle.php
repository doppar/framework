<?php

namespace Phaseolies\Signals\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
class DeliverOnSettle
{
    /**
     * @param string|null $connection Connection whose transaction to wait for, null for the default one
     */
    public function __construct(public readonly ?string $connection = null) {}
}
