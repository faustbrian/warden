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
 * Constraint comparing entity and authority model columns.
 *
 * This constraint enables authorization rules based on comparing attributes
 * between the resource being accessed (entity) and the user/role attempting
 * access (authority). Commonly used for ownership checks where entity.user_id
 * is compared to authority.id to ensure the authority owns the resource.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ColumnConstraint extends Constraint
{
    /**
     * Create a new column constraint instance.
     *
     * @param string $a        Column name on the entity model to compare from
     * @param string $operator Comparison operator (=, !=, >, <, >=, <=) to apply
     *                         between the two column values
     * @param string $b        Column name on the authority model to compare against
     */
    public function __construct(
        private readonly string $a,
        public readonly string $operator,
        private readonly string $b,
    ) {}

    /**
     * Create a new instance from the raw data.
     *
     * Hydrates a constraint from serialized data, typically retrieved from the
     * database where constraints are stored as JSON. Used during authorization
     * checks to reconstruct the constraint from its stored representation.
     *
     * @param array<string, mixed> $data Associative array containing constraint parameters
     *                                   including column names ('a', 'b'), comparison operator,
     *                                   and optional logical operator for chaining
     *
     * @throws InvalidArgumentException If columns or operator are not strings
     *
     * @return static Hydrated column constraint instance
     */
    public static function fromData(array $data): static
    {
        $a = $data['a'];
        $operator = $data['operator'];
        $b = $data['b'];
        $logicalOp = $data['logicalOperator'] ?? null;

        throw_if(!is_string($a) || !is_string($operator) || !is_string($b), InvalidArgumentException::class, 'Columns and operator must be strings');

        $constraint = new self($a, $operator, $b);

        if (is_string($logicalOp) || $logicalOp === null) {
            $constraint->logicalOperator($logicalOp);
        }

        return $constraint;
    }

    /**
     * Determine whether the given entity/authority passes the constraint.
     *
     * Evaluates the constraint by extracting the specified column values from
     * both models and comparing them using the configured operator. Returns false
     * if no authority is provided, as column comparison requires both models.
     *
     * @param  Model      $entity    The resource model being accessed (e.g., a Post or Document)
     * @param  null|Model $authority The authority attempting access (e.g., a User or Role).
     *                               When null, the constraint fails as both models are required.
     * @return bool       True if the constraint is satisfied, false otherwise
     */
    public function check(Model $entity, ?Model $authority = null): bool
    {
        if (!$authority instanceof Model) {
            return false;
        }

        return $this->compare($entity->{$this->a}, $authority->{$this->b});
    }

    /**
     * Get the JSON-able data of this object.
     *
     * Serializes the constraint to an array for storage in the database. The
     * resulting structure can be passed to fromData() to reconstruct this
     * constraint instance later during authorization evaluation.
     *
     * @return array<string, mixed> Array representation suitable for JSON serialization,
     *                              containing the class name and all constraint parameters
     */
    public function data(): array
    {
        return [
            'class' => self::class,
            'params' => [
                'a' => $this->a,
                'operator' => $this->operator,
                'b' => $this->b,
                'logicalOperator' => $this->logicalOperator,
            ],
        ];
    }
}
