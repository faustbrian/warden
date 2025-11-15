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
 * Pivot model for role assignments.
 *
 * Represents the many-to-many relationship between actors (users, etc.) and roles.
 * Includes support for ULID/UUID primary keys based on configuration.
 *
 * @property int|string      $actor_id   Polymorphic ID of the assigned entity
 * @property string          $actor_type Polymorphic type of the assigned entity
 * @property int|string      $id         Primary key (int, ULID, or UUID based on config)
 * @property int|string      $role_id    Foreign key to roles table
 * @property null|int|string $scope      Tenant scope identifier
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class AssignedRole extends MorphPivot
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
     * Create a new AssignedRole pivot instance.
     *
     * Dynamically sets the table name from the Models registry.
     *
     * @param array<string, mixed> $attributes Initial attribute values
     */
    public function __construct(array $attributes = [])
    {
        $this->setTable(Models::table('assigned_roles'));

        parent::__construct($attributes);
    }
}
