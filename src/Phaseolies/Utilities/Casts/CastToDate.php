<?php

namespace Phaseolies\Utilities\Casts;

/**
 * @deprecated Use the #[ToDate] attribute cast instead:
 *
 *   use Phaseolies\Database\Entity\Casts\Attributes\ToDate;
 *
 *   #[ToDate]
 *   public bool $timeStamps = true;
 *
 * CastToDate will be removed in a future major version.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class CastToDate
{
    public function __construct(
        public string $format = 'Y-m-d'
    ) {}
}
