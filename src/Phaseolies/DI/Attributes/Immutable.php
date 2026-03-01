<?php

namespace Phaseolies\DI\Attributes;

use Attribute;

/**
 * #[Immutable] — Freeze a Service After Boot
 *
 * When applied to a class, the container will wrap the resolved instance
 * in an ImmutableProxy. Any attempt to mutate a property at runtime will
 * throw an ImmutableViolationException.
 *
 * Usage:
 *   #[Immutable]
 *   class PaymentService { ... }
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Immutable
{
    public function __construct() {}
}
