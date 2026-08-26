<?php

namespace Phaseolies\Database\Entity\Attributes;

/**
 * Declares a model method as a lifecycle hook handler.
 *
 * Usage — basic:
 *
 *   #[Hook('before_created')]
 *   public function setDefaults(): void { ... }
 *
 * Usage — with a string condition (method name on the model):
 *
 *   #[Hook('before_updated', when: 'isPublished')]
 *   public function generateSlug(): void { ... }
 *
 * Usage — with multiple events on the same method:
 *
 *   #[Hook('before_created')]
 *   #[Hook('before_updated')]
 *   public function generateSlug(): void { ... }
 *
 * Supported events:
 *   booting, booted,
 *   before_created, after_created,
 *   before_updated, after_updated,
 *   before_deleted, after_deleted
 *
 * The 'when' parameter (optional):
 *   - A string → name of a public/protected method on the model that returns bool
 *   - Omitted  → hook always fires
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Hook
{
    /**
     * @param string $event Lifecycle event name e.g. 'before_updated'
     * @param string|null $when Optional method name on the model returning bool
     */
    public function __construct(
        public readonly string $event,
        public readonly ?string $when = null,
    ) {}
}
