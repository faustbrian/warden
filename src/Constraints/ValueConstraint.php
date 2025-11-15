<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Constraints;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

use function is_string;
use function throw_if;

/**
 * Constraint that compares an entity's column value to a static value.
 *
 * Evaluates authorization rules based on entity attributes matching specific
 * values. For example, restricting access to posts with status='published'
 * or limiting actions on items created after a certain date.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ValueConstraint extends Constraint
{
    /**
     * Create a new value constraint.
     *
     * @param string $column   The name of the column on the entity model to evaluate for authorization.
     *                         Must be an accessible property or attribute on the entity. Commonly used
     *                         for status fields, ownership flags, timestamps, or any entity attribute
     *                         that determines access rights. The column value is retrieved dynamically
     *                         during constraint evaluation and compared against the target value.
     * @param string $operator The comparison operator used to evaluate the constraint (=, !=, <, >, <=, >=).
     *                         Determines the comparison logic between the entity's column value and the
     *                         target value. All comparisons use strict type checking for security. When
     *                         using the Constraint::where() factory, defaults to '=' for equality checks.
     * @param mixed  $value    The static target value to compare against the entity's column value during
     *                         authorization checks. Can be any type matching the column's data type: string,
     *                         integer, boolean, datetime, enum, etc. The comparison is strictly typed, so
     *                         '1' (string) will not match 1 (integer), ensuring predictable authorization logic.
     */
    public function __construct(
        private readonly string $column,
        public readonly string $operator,
        private readonly mixed $value,
    ) {}

    /**
     * Reconstitute a value constraint from serialized array data.
     *
     * @param  array<string, mixed> $data Serialized constraint data with column, operator, value, and logicalOperator
     * @return static               The reconstituted value constraint instance
     */
    public static function fromData(array $data): static
    {
        $column = $data['column'];
        $operator = $data['operator'];
        $logicalOp = $data['logicalOperator'] ?? null;

        throw_if(!is_string($column) || !is_string($operator), InvalidArgumentException::class, 'Column and operator must be strings');

        $constraint = new self($column, $operator, $data['value']);

        if (is_string($logicalOp) || $logicalOp === null) {
            $constraint->logicalOperator($logicalOp);
        }

        return $constraint;
    }

    /**
     * Evaluate whether the entity's column value satisfies this constraint.
     *
     * Retrieves the entity's column value and compares it to the constraint's
     * target value using the configured operator. The authority parameter is
     * not used by value constraints but is required by the interface.
     *
     * @param  Model      $entity    The model being authorized
     * @param  null|Model $authority The authenticated user (unused for value constraints)
     * @return bool       True if the entity's column value passes the constraint
     */
    public function check(Model $entity, ?Model $authority = null): bool
    {
        return $this->compare($entity->{$this->column}, $this->value);
    }

    /**
     * Serialize this constraint to an array representation.
     *
     * @return array<string, mixed> Array with 'class' and 'params' keys
     */
    public function data(): array
    {
        return [
            'class' => self::class,
            'params' => [
                'column' => $this->column,
                'operator' => $this->operator,
                'value' => $this->value,
                'logicalOperator' => $this->logicalOperator,
            ],
        ];
    }
}
