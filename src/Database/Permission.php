<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Database;

use Cline\Warden\Database\Concerns\HasWardenPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphPivot;

/**
 * Pivot model for ability permissions.
 *
 * Represents the many-to-many relationship between actors (users, roles, etc.)
 * and abilities. Includes support for ULID/UUID primary keys based on configuration.
 *
 * @property int|string      $ability_id   Foreign key to abilities table
 * @property null|int|string $actor_id     Polymorphic ID of the authorized entity (null for everyone)
 * @property null|string     $actor_type   Polymorphic type of the authorized entity (null for everyone)
 * @property null|int|string $context_id   Polymorphic ID of the context entity
 * @property null|string     $context_type Polymorphic type of the context entity
 * @property bool            $forbidden    Whether this is a forbidden permission
 * @property int|string      $id           Primary key (int, ULID, or UUID based on config)
 * @property null|int|string $scope        Tenant scope identifier
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Permission extends MorphPivot
{
    use HasFactory;
    use HasWardenPrimaryKey;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * This will be overridden by HasWardenPrimaryKey for ULID/UUID configs.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * Create a new Permission pivot instance.
     *
     * Dynamically sets the table name from the Models registry.
     *
     * @param array<string, mixed> $attributes Initial attribute values
     */
    public function __construct(array $attributes = [])
    {
        $this->setTable(Models::table('permissions'));

        parent::__construct($attributes);
    }
}
