<?php

namespace Phaseolies\Database\Entity\Attributes;

/**
 * Marks a model method as a computed (virtual) property.
 *
 * Computed properties are derived values — they are never persisted to the
 * database, never included in dirty-checking, and are automatically appended
 * to toArray() / toJson() output alongside real attributes.
 *
 * Usage:
 *
 *   #[Computed]
 *   public function fullName(): string
 *   {
 *       return "{$this->first_name} {$this->last_name}";
 *   }
 *
 * Access:
 *
 *   $user->fullName          // calls the method transparently
 *   $user->full_name         // calls the method transparently
 *   $user->toArray()         // includes 'fullName' automatically
 *   json_encode($user)       // includes 'fullName' automatically
 *
 * Hiding a computed property from serialization:
 *
 *   $user->makeHidden(['fullName'])->toArray();
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class Computed {}
