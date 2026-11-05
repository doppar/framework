<?php

namespace Phaseolies\Database\Entity\Attributes;

use Attribute;

/**
 * Marks a model as soft-deletable.
 *
 * Deleting the model stamps the given column instead of removing the row,
 * and every query on the model excludes stamped rows unless asked not to.
 *
 * Usage:
 *
 *   #[SoftDeletes]
 *   class Post extends Model {}
 *
 *   #[SoftDeletes(column: 'removed_at')]
 *   class Post extends Model {}
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class SoftDeletes
{
    /**
     * @param string $column
     */
    public function __construct(
        public readonly string $column = 'deleted_at',
    ) {}
}
