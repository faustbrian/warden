<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Constraints;

use Closure;
use Illuminate\Support\Collection;

use function func_get_args;

/**
 * Fluent builder for creating ability authorization constraints.
 *
 * This builder provides a query-builder-like interface for constructing complex
 * permission constraints that determine when an authority can exercise an ability.
 * Supports where clauses, column comparisons, nested groups, and logical operators
 * for building sophisticated authorization rules.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Builder
{
    /**
     * The list of constraints.
     *
     * Collection of individual constraint objects or nested builder instances
     * that will be combined into a composite constraint when build() is called.
     *
     * @var Collection<int, Constrainer>
     */
    private Collection $constraints;

    /**
     * Create a new constraint builder instance.
     */
    public function __construct()
    {
        $this->constraints = new Collection();
    }

    /**
     * Create a new builder instance.
     *
     * Static factory method for fluent instantiation without using 'new'.
     * Enables syntax like: Builder::make()->where(...)->build()
     *
     * @return self Fresh builder instance
     */
    public static function make(): self
    {
        return new self();
    }

    /**
     * Add a "where" constraint.
     *
     * Creates an AND condition comparing an entity column to a value. When a
     * closure is provided, creates a nested group of constraints combined with
     * AND logic for complex conditional logic.
     *
     * @param  Closure|string $column   Column name on the entity model, or closure
     *                                  for nested constraint group
     * @param  mixed          $operator comparison operator (=, !=, >, <, >=, <=) or the value
     *                                  if operator is omitted (defaults to =)
     * @param  mixed          $value    Value to compare against when operator is provided
     * @return $this
     */
    public function where(Closure|string $column, mixed $operator = null, mixed $value = null): self
    {
        if ($column instanceof Closure) {
            return $this->whereNested('and', $column);
        }

        /** @phpstan-ignore-next-line Constraint::where() validates parameter types */
        return $this->addConstraint(Constraint::where(...func_get_args()));
    }

    /**
     * Add an "or where" constraint.
     *
     * Creates an OR condition comparing an entity column to a value. When a
     * closure is provided, creates a nested group of constraints combined with
     * OR logic for alternative authorization paths.
     *
     * @param  Closure|string $column   Column name on the entity model, or closure
     *                                  for nested constraint group
     * @param  mixed          $operator comparison operator (=, !=, >, <, >=, <=) or the value
     *                                  if operator is omitted (defaults to =)
     * @param  mixed          $value    Value to compare against when operator is provided
     * @return $this
     */
    public function orWhere(Closure|string $column, mixed $operator = null, mixed $value = null): self
    {
        if ($column instanceof Closure) {
            return $this->whereNested('or', $column);
        }

        /** @phpstan-ignore-next-line Constraint::orWhere() validates parameter types */
        return $this->addConstraint(Constraint::orWhere(...func_get_args()));
    }

    /**
     * Add a "where column" constraint.
     *
     * Creates an AND condition comparing a column on the entity model to a column
     * on the authority model. Useful for ownership checks like comparing entity.user_id
     * to authority.id to ensure the authority owns the entity.
     *
     * @param  string $a        Column name on the entity model
     * @param  mixed  $operator comparison operator (=, !=, >, <, >=, <=) or column name
     *                          on the authority model if operator is omitted (defaults to =)
     * @param  mixed  $b        Column name on the authority model when operator is provided
     * @return $this
     */
    public function whereColumn(string $a, mixed $operator, mixed $b = null): self
    {
        /** @phpstan-ignore-next-line Constraint::whereColumn() validates parameter types */
        return $this->addConstraint(Constraint::whereColumn(...func_get_args()));
    }

    /**
     * Add an "or where column" constraint.
     *
     * Creates an OR condition comparing a column on the entity model to a column
     * on the authority model. Provides alternative authorization path when direct
     * ownership or another column comparison might authorize the action.
     *
     * @param  string $a        Column name on the entity model
     * @param  mixed  $operator comparison operator (=, !=, >, <, >=, <=) or column name
     *                          on the authority model if operator is omitted (defaults to =)
     * @param  mixed  $b        Column name on the authority model when operator is provided
     * @return $this
     */
    public function orWhereColumn(string $a, mixed $operator, mixed $b = null): self
    {
        /** @phpstan-ignore-next-line Constraint::orWhereColumn() validates parameter types */
        return $this->addConstraint(Constraint::orWhereColumn(...func_get_args()));
    }

    /**
     * Build the compiled list of constraints.
     *
     * Converts the collection of constraint objects into a single executable
     * constraint. Single constraints are returned as-is, while multiple constraints
     * are wrapped in a Group for combined evaluation.
     *
     * @return Constrainer Single constraint or group of constraints
     */
    public function build(): Constrainer
    {
        if ($this->constraints->count() === 1) {
            /** @var Constrainer */
            return $this->constraints->first();
        }

        return new Group($this->constraints);
    }

    /**
     * Add a nested "where" clause.
     *
     * Creates a sub-builder and passes it to the callback for defining a group
     * of constraints. The resulting group is added to the main builder with the
     * specified logical operator (AND/OR), enabling complex nested authorization rules.
     *
     * @param  string  $logicalOperator 'and' or 'or' - determines how this group connects
     *                                  to the preceding constraint
     * @param  Closure $callback        Receives a fresh Builder instance for defining the
     *                                  nested constraints group
     * @return $this
     */
    private function whereNested(string $logicalOperator, Closure $callback): self
    {
        $callback($builder = new self());

        /** @var Constrainer $constraint */
        $constraint = $builder->build()->logicalOperator($logicalOperator);

        return $this->addConstraint($constraint);
    }

    /**
     * Add a constraint to the list of constraints.
     *
     * Appends a constraint object to the internal collection. This method is the
     * final step of all public constraint methods (where, orWhere, etc.) and
     * maintains the builder's fluent interface.
     *
     * @param  Constrainer $constraint The constraint object to add to the collection
     * @return $this
     */
    private function addConstraint(Constrainer $constraint): self
    {
        $this->constraints[] = $constraint;

        return $this;
    }
}
