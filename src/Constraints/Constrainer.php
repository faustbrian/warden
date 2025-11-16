<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Constraints;

use Illuminate\Database\Eloquent\Model;

/**
 * Defines the contract for authorization constraint implementations.
 *
 * Constrainers provide a flexible way to define conditions that must be met
 * for authorization checks to pass. They support logical operators (AND/OR)
 * to combine multiple constraints and can be serialized to/from array data
 * for storage or caching purposes.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface Constrainer
{
    /**
     * Create a new constrainer instance from serialized array data.
     *
     * Hydrates a constrainer from its array representation, typically used
     * when loading constraints from storage or cache. The data structure
     * must match the format returned by the data() method.
     *
     * @param  array<string, mixed> $data Serialized constraint data including class and parameters
     * @return static               The reconstituted constrainer instance
     */
    public static function fromData(array $data): static;

    /**
     * Serialize this constrainer to an array representation.
     *
     * Returns the constrainer's configuration in a format that can be
     * stored, cached, or transmitted, and later reconstituted via fromData().
     * Includes the class name and all parameters needed for reconstruction.
     *
     * @return array<string, mixed> Array containing 'class' and 'params' keys
     */
    public function data(): array;

    /**
     * Evaluate whether the given entity satisfies this constraint.
     *
     * Performs the constraint check against the entity and optionally the authority
     * (authenticated user). The authority parameter enables constraints that compare
     * entity attributes to the current user's attributes (e.g., matching user IDs).
     *
     * @param  Model      $entity    The model being authorized (e.g., a Post, Comment, etc.)
     * @param  null|Model $authority The authenticated user or authority model
     * @return bool       True if the constraint passes, false otherwise
     */
    public function check(Model $entity, ?Model $authority = null): bool;

    /**
     * Get or set the logical operator for combining with previous constraints.
     *
     * When multiple constraints are chained, this operator determines how they
     * are combined: 'and' requires all constraints to pass, 'or' requires at
     * least one to pass. Called with no argument to retrieve current operator.
     *
     * @param  null|string  $operator The operator to set ('and' or 'or'), or null to retrieve
     * @return $this|string Returns $this when setting, current operator when getting
     *
     * @codeCoverageIgnore Coverage tool cannot track interface method signatures
     */
    public function logicalOperator($operator = null): self|string;

    /**
     * Check if this constraint uses AND logic when combined with others.
     *
     * @return bool True if the logical operator is 'and'
     */
    public function isAnd(): bool;

    /**
     * Check if this constraint uses OR logic when combined with others.
     *
     * @return bool True if the logical operator is 'or'
     */
    public function isOr(): bool;

    /**
     * Determine if this constrainer is functionally equivalent to another.
     *
     * Compares the constraint parameters to check for equality. Used to
     * deduplicate constraints or verify constraint sets match expected values.
     *
     * @param  self $constrainer The constrainer to compare against
     * @return bool True if both constrainers represent the same constraint
     */
    public function equals(self $constrainer): bool;
}
