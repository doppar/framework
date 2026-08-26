<?php

namespace Phaseolies\Database\Entity\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)]
class Watches
{
    /**
     * @param string $watcher
     * @param string|null $when
     */
    public function __construct(
        public readonly string $watcher,
        public readonly ?string $when = null,
    ) {}
}
