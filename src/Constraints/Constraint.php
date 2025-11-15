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
use InvalidArgumentException;

use function func_num_args;
use function get_debug_type;
use function in_array;
use function is_string;
use function throw_unless;

/**
 * Base implementation for authorization constraints with comparison operators.
 *
 * Provides common functionality for constraint types that perform comparisons
 * using operators (=, !=, <, >, <=, >=). Handles logical operator management
 * and provides factory methods for creating value and column-based constraints.
 *
 * @author Brian Faust <brian@cline.sh>
 */
abstract class Constraint implements Constrainer
{
    /**
     * The comparison operator for this constraint.
     *
     * Defines the comparison operation (=, !=, <, >, <=, >=) used to evaluate
     * the constraint. Child classes must initialize this in their constructors.
     *
     * @phpstan-ignore property.uninitializedReadonly
     */
    public readonly string $operator;

    /**
     * The logical operator for combining this constraint with previous ones.
     *
     * Determines how this constraint is combined with preceding constraints
     * in a chain: 'and' requires all constraints to pass, 'or' requires only
     * one. Defaults to 'and' for conjunctive constraint evaluation.
     */
    protected string $logicalOperator = 'and';

    /**
     * Create a value constraint comparing an entity column to a static value.
     *
     * Supports both two-argument form (assumes '=' operator) and three-argument
     * form with explicit operator. The constraint checks if the entity's column
     * value matches the given value using the specified comparison operator.
     *
     * ```php
     * // Check if status equals 'published'
     * Constraint::where('status', 'published');
     *
     * // Check if created_at is greater than a date
     * Constraint::where('created_at', '>', '2024-01-01');
     * ```
     *
     * @param  string          $column   The entity column name to check
     * @param  mixed           $operator The comparison operator, or the value if using two-argument form
     * @param  mixed           $value    The value to compare against (null if using two-argument form)
     * @return ValueConstraint The configured value constraint instance
     */
    public static function where(string $column, mixed $operator, mixed $value = null): ValueConstraint
    {
        [$operator, $value] = static::prepareOperatorAndValue(
            $operator,
            $value,
            func_num_args() === 2,
        );

        return new ValueConstraint($column, $operator, $value);
    }

    /**
     * Create an OR value constraint as an alternative to previous constraints.
     *
     * Identical to where() but sets the logical operator to 'or', making this
     * constraint an alternative condition rather than an additional requirement.
     *
     * @param  string          $column   The entity column name to check
     * @param  mixed           $operator The comparison operator, or the value if using two-argument form
     * @param  mixed           $value    The value to compare against (null if using two-argument form)
     * @return ValueConstraint The configured value constraint with OR logic
     */
    public static function orWhere(string $column, mixed $operator, mixed $value = null): ValueConstraint
    {
        $constraint = static::where($column, $operator, $value);
        $constraint->logicalOperator('or');

        return $constraint;
    }

    /**
     * Create a constraint comparing entity and authority column values.
     *
     * Enables authorization rules that depend on relationships between the entity
     * and the authenticated user. For example, checking if a post's user_id matches
     * the current user's id, or if ownership is indicated by matching attributes.
     *
     * ```php
     * // Check if entity's user_id matches authority's id
     * Constraint::whereColumn('user_id', 'id');
     *
     * // Check if entity's actor_id doesn't match authority's id
     * Constraint::whereColumn('actor_id', '!=', 'id');
     * ```
     *
     * @param  string           $a        The column on the entity to compare
     * @param  mixed            $operator The comparison operator, or the authority column if using two-argument form
     * @param  mixed            $b        The column on the authority to compare against
     * @return ColumnConstraint The configured column constraint instance
     */
    public static function whereColumn(string $a, mixed $operator, mixed $b = null): ColumnConstraint
    {
        [$operator, $b] = static::prepareOperatorAndValue(
            $operator,
            $b,
            func_num_args() === 2,
        );

        throw_unless(is_string($b), InvalidArgumentException::class, 'Column name must be a string');

        return new ColumnConstraint($a, $operator, $b);
    }

    /**
     * Create an OR column constraint as an alternative to previous constraints.
     *
     * Identical to whereColumn() but sets the logical operator to 'or', making
     * this constraint an alternative condition rather than an additional requirement.
     *
     * @param  string           $a        The column on the entity to compare
     * @param  mixed            $operator The comparison operator, or the authority column if using two-argument form
     * @param  mixed            $b        The column on the authority to compare against
     * @return ColumnConstraint The configured column constraint with OR logic
     */
    public static function orWhereColumn(string $a, mixed $operator, mixed $b = null): ColumnConstraint
    {
        $constraint = static::whereColumn($a, $operator, $b);
        $constraint->logicalOperator('or');

        return $constraint;
    }

    /**
     * Get or set the logical operator for combining with previous constraints.
     *
     * @param mixed $operator The operator to set ('and' or 'or'), or null to retrieve
     *
     * @throws InvalidArgumentException If the operator is not 'and' or 'or'
     *
     * @return $this|string Returns $this when setting, current operator when getting
     */
    public function logicalOperator(mixed $operator = null): static|string
    {
        if (null === $operator) {
            return $this->logicalOperator;
        }

        if (!is_string($operator)) {
            throw new InvalidArgumentException('Logical operator must be a string, '.get_debug_type($operator).' given');
        }

        Helpers::ensureValidLogicalOperator($operator);

        $this->logicalOperator = $operator;

        return $this;
    }

    /**
     * Check if this constraint uses AND logic when combined with others.
     *
     * @return bool True if the logical operator is 'and'
     */
    public function isAnd(): bool
    {
        return $this->logicalOperator === 'and';
    }

    /**
     * Check if this constraint uses OR logic when combined with others.
     *
     * @return bool True if the logical operator is 'or'
     */
    public function isOr(): bool
    {
        return $this->logicalOperator === 'or';
    }

    /**
     * Determine if this constraint is functionally equivalent to another.
     *
     * Compares both the constraint type and parameters. Two constraints are
     * equal if they are the same class and have identical parameter values.
     *
     * @param  Constrainer $constrainer The constrainer to compare against
     * @return bool        True if both constraints represent the same rule
     */
    public function equals(Constrainer $constrainer): bool
    {
        if (!$constrainer instanceof static) {
            return false;
        }

        return $this->data()['params'] === $constrainer->data()['params'];
    }

    /**
     * Evaluate whether the given entity satisfies this constraint.
     *
     * @param  Model      $entity    The model being authorized
     * @param  null|Model $authority The authenticated user or authority model
     * @return bool       True if the constraint passes
     */
    abstract public function check(Model $entity, ?Model $authority = null): bool;

    /**
     * Normalize operator and value arguments into consistent format.
     *
     * Supports Laravel-style where clause syntax with optional operator.
     * When only two arguments are provided (column, value), the operator
     * defaults to '='. Validates that provided operators are supported.
     *
     * @param mixed $operator    The operator or value when using two-argument form
     * @param mixed $value       The value to compare against (null if two-argument form)
     * @param mixed $usesDefault Whether the default operator should be used
     *
     * @throws InvalidArgumentException If the operator is not a valid comparison operator
     *
     * @return array{0: string, 1: mixed} Array with normalized [operator, value]
     */
    protected static function prepareOperatorAndValue(mixed $operator, mixed $value, mixed $usesDefault): array
    {
        if ($usesDefault) {
            return ['=', $operator];
        }

        throw_unless(is_string($operator), InvalidArgumentException::class, 'Operator must be a string');

        throw_unless(in_array($operator, ['=', '==', '!=', '<', '>', '<=', '>='], true), InvalidArgumentException::class, $operator.' is not a valid operator');

        return [$operator, $value];
    }

    /**
     * Execute the comparison operation using the constraint's operator.
     *
     * Performs strict comparisons for all operators. Uses PHP's match expression
     * to map the operator string to the appropriate comparison logic. Throws an
     * exception for unsupported operators.
     *
     * @param mixed $a The first value to compare
     * @param mixed $b The second value to compare
     *
     * @throws InvalidArgumentException If the operator is not a valid comparison operator
     *
     * @return bool The comparison result
     */
    protected function compare(mixed $a, mixed $b): bool
    {
        return match ($this->operator) {
            '=', '==' => $a === $b,
            '!=' => $a !== $b,
            '<' => $a < $b,
            '>' => $a > $b,
            '<=' => $a <= $b,
            '>=' => $a >= $b,
            default => throw new InvalidArgumentException('Invalid operator: '.$this->operator),
        };
    }
}
