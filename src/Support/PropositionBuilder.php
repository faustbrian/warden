<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Support;

use ArrayAccess;
use BadMethodCallException;
use Cline\Ruler\Builder\RuleBuilder;
use Cline\Ruler\Builder\Variable;
use Cline\Ruler\Core\Proposition;
use InvalidArgumentException;

use function array_shift;
use function throw_if;

/**
 * Fluent builder for creating conditional permission propositions.
 *
 * Provides a convenient interface for building Ruler propositions that can be
 * attached to abilities for conditional permission checking. Wraps the Ruler
 * RuleBuilder with domain-specific helpers for common authorization patterns.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @implements ArrayAccess<string, Variable>
 *
 * @example
 * ```php
 * // Resource ownership check
 * $builder = new PropositionBuilder();
 * $proposition = $builder->resourceOwnedBy('authority');
 *
 * // Time-based access
 * $proposition = $builder->timeBetween('09:00', '17:00');
 *
 * // Complex conditions
 * $proposition = $builder->logicalAnd(
 *     $builder['resource']['status']->equalTo('active'),
 *     $builder['authority']['department']->equalTo('engineering')
 * );
 * ```
 */
final class PropositionBuilder implements ArrayAccess
{
    /**
     * The underlying RuleBuilder instance.
     */
    private RuleBuilder $builder;

    /**
     * Create a new PropositionBuilder instance.
     */
    public function __construct()
    {
        $this->builder = new RuleBuilder();
    }

    /**
     * Magic method to access variables.
     *
     * @param string $name The variable name
     *
     * @return Variable The variable instance
     */
    public function __get(string $name): Variable
    {
        return $this->builder[$name];
    }

    /**
     * Get a variable from the rule builder.
     *
     * @param string $name The variable name
     *
     * @return Variable The variable instance
     */
    public function offsetGet(mixed $name): Variable
    {
        return $this->builder[$name];
    }

    /**
     * Check if a variable exists.
     *
     * @param string $name The variable name
     *
     * @return bool Always returns true
     */
    public function offsetExists(mixed $name): bool
    {
        return true;
    }

    /**
     * Set a variable value.
     *
     * @param string $name  The variable name
     * @param mixed  $value The variable value
     */
    public function offsetSet(mixed $name, mixed $value): void
    {
        // Not supported - variables are created via offsetGet
        throw new BadMethodCallException('Cannot set variables directly on PropositionBuilder');
    }

    /**
     * Unset a variable.
     *
     * @param string $name The variable name
     */
    public function offsetUnset(mixed $name): void
    {
        // Not supported - variables are managed by RuleBuilder
        throw new BadMethodCallException('Cannot unset variables on PropositionBuilder');
    }

    /**
     * Create logical AND proposition.
     *
     * @param Proposition ...$propositions The propositions to combine with AND logic
     *
     * @return Proposition The combined AND proposition
     */
    public function logicalAnd(Proposition ...$propositions): Proposition
    {
        return $this->builder->logicalAnd(...$propositions);
    }

    /**
     * Create logical OR proposition.
     *
     * @param Proposition ...$propositions The propositions to combine with OR logic
     *
     * @return Proposition The combined OR proposition
     */
    public function logicalOr(Proposition ...$propositions): Proposition
    {
        return $this->builder->logicalOr(...$propositions);
    }

    /**
     * Create logical NOT proposition.
     *
     * @param Proposition $proposition The proposition to negate
     *
     * @return Proposition The negated proposition
     */
    public function logicalNot(Proposition $proposition): Proposition
    {
        return $this->builder->logicalNot($proposition);
    }

    /**
     * Create a proposition that checks if the resource is owned by the authority.
     *
     * Compares the resource's user_id (or configured ownership field) against
     * the authority's primary key. Useful for restricting actions to owned resources.
     *
     * @param string $authorityKey   The context key for the authority (default: 'authority')
     * @param string $resourceKey    The context key for the resource (default: 'resource')
     * @param string $ownershipField The field on the resource that identifies the owner (default: 'user_id')
     *
     * @return Proposition The ownership check proposition
     */
    public function resourceOwnedBy(
        string $authorityKey = 'authority',
        string $resourceKey = 'resource',
        string $ownershipField = 'user_id',
    ): Proposition {
        return $this->builder[$resourceKey][$ownershipField]->equalTo($this->builder[$authorityKey]['id']);
    }

    /**
     * Create a proposition that checks if the current time falls within a range.
     *
     * Useful for implementing business hours restrictions or time-limited permissions.
     * Time strings should be in 24-hour format (e.g., '09:00', '17:30').
     *
     * @param string $start   Start time in 24-hour format (e.g., '09:00')
     * @param string $end     End time in 24-hour format (e.g., '17:00')
     * @param string $timeKey The context key for current time (default: 'now')
     *
     * @return Proposition The time range check proposition
     */
    public function timeBetween(string $start, string $end, string $timeKey = 'now'): Proposition
    {
        return $this->logicalAnd(
            $this->builder[$timeKey]->greaterThanOrEqualTo($start),
            $this->builder[$timeKey]->lessThanOrEqualTo($end),
        );
    }

    /**
     * Create a proposition that checks if a numeric value is within a limit.
     *
     * Useful for implementing approval limits, spending caps, or other
     * numeric threshold checks.
     *
     * @param string $valueKey  The context key for the value to check
     * @param string $limitKey  The context key for the limit to compare against
     * @param bool   $inclusive Whether to include the limit value (default: true)
     *
     * @return Proposition The limit check proposition
     */
    public function withinLimit(string $valueKey, string $limitKey, bool $inclusive = true): Proposition
    {
        return $inclusive
            ? $this->builder[$valueKey]->lessThanOrEqualTo($this->builder[$limitKey])
            : $this->builder[$valueKey]->lessThan($this->builder[$limitKey]);
    }

    /**
     * Create a proposition that checks if two values match.
     *
     * Useful for department matching, status checks, or any equality comparison.
     *
     * @param string $leftKey  The context key for the left operand
     * @param string $rightKey The context key for the right operand
     *
     * @return Proposition The equality check proposition
     */
    public function matches(string $leftKey, string $rightKey): Proposition
    {
        return $this->builder[$leftKey]->equalTo($this->builder[$rightKey]);
    }

    /**
     * Create a proposition that combines multiple conditions with AND logic.
     *
     * All propositions must evaluate to true for the combined proposition to pass.
     *
     * @param Proposition ...$propositions The propositions to combine
     *
     * @return Proposition The combined AND proposition
     */
    public function allOf(Proposition ...$propositions): Proposition
    {
        throw_if($propositions === [], InvalidArgumentException::class, 'At least one proposition is required');

        $result = array_shift($propositions);

        foreach ($propositions as $proposition) {
            $result = $this->logicalAnd($result, $proposition);
        }

        return $result;
    }

    /**
     * Create a proposition that combines multiple conditions with OR logic.
     *
     * At least one proposition must evaluate to true for the combined proposition to pass.
     *
     * @param Proposition ...$propositions The propositions to combine
     *
     * @return Proposition The combined OR proposition
     */
    public function anyOf(Proposition ...$propositions): Proposition
    {
        throw_if($propositions === [], InvalidArgumentException::class, 'At least one proposition is required');

        $result = array_shift($propositions);

        foreach ($propositions as $proposition) {
            $result = $this->logicalOr($result, $proposition);
        }

        return $result;
    }
}
