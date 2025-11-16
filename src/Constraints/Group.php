<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Constraints;

use Cline\Warden\Support\Helpers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;

use function array_map;
use function gettype;
use function is_string;

/**
 * Groups multiple constraints with configurable logical evaluation.
 *
 * Enables complex authorization rules by combining multiple constraints
 * using AND/OR logic. Groups can be nested to create sophisticated
 * authorization conditions. Empty groups always pass to avoid blocking
 * authorization when no constraints are defined.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Group implements Constrainer
{
    /**
     * The collection of constraints in this group.
     *
     * Contains Constrainer instances (Constraint or nested Group objects)
     * that will be evaluated together. The evaluation order respects each
     * constraint's individual logical operator.
     *
     * @var Collection<int|string, Constrainer>
     */
    private Collection $constraints;

    /**
     * The logical operator for combining this group with previous constrainers.
     *
     * Determines how this entire group is combined with preceding constraints
     * in a parent context: 'and' requires all to pass, 'or' requires only one.
     * Does not affect how constraints within this group are combined.
     */
    private string $logicalOperator = 'and';

    /**
     * Create a new constraint group.
     *
     * @param iterable<int|string, Constrainer> $constraints Initial constraints to include in the group
     */
    public function __construct(iterable $constraints = [])
    {
        $this->constraints = new Collection($constraints);
    }

    /**
     * Create a new constraint group with AND logic.
     *
     * Factory method for creating a group that will be combined with
     * preceding constraints using AND logic (all must pass).
     *
     * @return self The new group instance
     */
    public static function withAnd(): self
    {
        return new self();
    }

    /**
     * Create a new constraint group with OR logic.
     *
     * Factory method for creating a group that will be combined with
     * preceding constraints using OR logic (only one must pass).
     *
     * @return self The new group instance with OR operator
     */
    public static function withOr(): self
    {
        $group = new self();
        $group->logicalOperator('or');

        return $group;
    }

    /**
     * Reconstitute a constraint group from serialized array data.
     *
     * Recursively rebuilds the constraint group by hydrating each child
     * constraint from its serialized form. Restores the group's logical
     * operator and all nested constraints.
     *
     * @param  array<string, mixed> $data Serialized group data with 'constraints' and 'logicalOperator'
     * @return self                 The reconstituted group instance
     */
    public static function fromData(array $data): static
    {
        /** @var array<int, array{class: class-string<Constrainer>, params: array<string, mixed>}> $constraints */
        $constraints = $data['constraints'];

        $group = new self(array_map(
            /** @param array{class: class-string<Constrainer>, params: array<string, mixed>} $constraintData */
            fn (array $constraintData): Constrainer => $constraintData['class']::fromData($constraintData['params']),
            $constraints,
        ));

        /** @var string $operator */
        $operator = $data['logicalOperator'];
        $group->logicalOperator($operator);

        return $group;
    }

    /**
     * Add a constraint to this group.
     *
     * Constraints are evaluated in the order they are added. Each constraint's
     * logical operator determines how it combines with the previous result.
     *
     * @param  Constrainer $constraint The constraint to add (Constraint or nested Group)
     * @return self        Returns $this for method chaining
     */
    public function add(Constrainer $constraint): self
    {
        $this->constraints->push($constraint);

        return $this;
    }

    /**
     * Evaluate whether the entity satisfies all constraints in this group.
     *
     * Evaluates constraints sequentially, respecting each constraint's logical
     * operator to combine results. Empty groups return true to avoid blocking
     * authorization when no constraints are configured. Uses short-circuit
     * evaluation where possible for performance.
     *
     * @param  Model      $entity    The model being authorized
     * @param  null|Model $authority The authenticated user or authority model
     * @return bool       True if the constraint group passes
     */
    public function check(Model $entity, ?Model $authority = null): bool
    {
        if ($this->constraints->isEmpty()) {
            return true;
        }

        $first = $this->constraints->first();
        $initial = !$first->isOr();

        return $this->constraints->reduce(function (bool $carry, Constrainer $constraint) use ($entity, $authority): bool {
            $passes = $constraint->check($entity, $authority);

            return $constraint->isOr() ? ($carry || $passes) : ($carry && $passes);
        }, $initial);
    }

    /**
     * Get or set the logical operator for combining with previous constrainers.
     *
     * @param mixed $operator The operator to set ('and' or 'or'), or null to retrieve
     *
     * @throws InvalidArgumentException If the operator is not 'and' or 'or'
     *
     * @return $this|string Returns $this when setting, current operator when getting
     */
    public function logicalOperator(mixed $operator = null): self|string
    {
        if (null === $operator) {
            return $this->logicalOperator;
        }

        if (!is_string($operator)) {
            throw new InvalidArgumentException('Logical operator must be a string, got '.gettype($operator));
        }

        Helpers::ensureValidLogicalOperator($operator);

        $this->logicalOperator = $operator;

        return $this;
    }

    /**
     * Check if this group uses AND logic when combined with others.
     *
     * @return bool True if the logical operator is 'and'
     */
    public function isAnd(): bool
    {
        return $this->logicalOperator === 'and';
    }

    /**
     * Check if this group uses OR logic when combined with others.
     *
     * @return bool True if the logical operator is 'or'
     */
    public function isOr(): bool
    {
        return $this->logicalOperator === 'or';
    }

    /**
     * Determine if this group is functionally equivalent to another.
     *
     * Two groups are equal if they contain the same number of constraints
     * and each constraint at the same index is equal. The comparison is
     * order-dependent and checks constraints recursively.
     *
     * @param  Constrainer $constrainer The constrainer to compare against
     * @return bool        True if both groups contain equivalent constraints
     */
    public function equals(Constrainer $constrainer): bool
    {
        if (!$constrainer instanceof self) {
            return false;
        }

        if ($this->constraints->count() !== $constrainer->constraints->count()) {
            return false;
        }

        foreach ($this->constraints as $index => $constraint) {
            $otherConstraint = $constrainer->constraints[$index];

            if ($otherConstraint === null || !$otherConstraint->equals($constraint)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Serialize this group to an array representation.
     *
     * Converts the group and all nested constraints to a serializable
     * format suitable for storage or caching. Recursively serializes
     * all contained constraints.
     *
     * @return array<string, mixed> Array with 'class' and 'params' keys
     */
    public function data(): array
    {
        return [
            'class' => self::class,
            'params' => [
                'logicalOperator' => $this->logicalOperator,
                'constraints' => $this->constraints->map(fn (Constrainer $constraint): array => $constraint->data())->all(),
            ],
        ];
    }
}
