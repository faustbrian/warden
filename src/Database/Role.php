<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Database;

use Carbon\Carbon;
use Cline\Warden\Database\Concerns\HasWardenPrimaryKey;
use Cline\Warden\Database\Concerns\IsRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model representing a Warden role.
 *
 * Roles group related abilities and can be assigned to users or other entities.
 * This model provides tenant scoping, automatic title generation, and manages
 * relationships with users and abilities through polymorphic pivot tables.
 *
 * @property Carbon   $created_at Timestamp when the role was created
 * @property int      $id         Auto-incrementing primary key identifier
 * @property string   $name       Machine-readable role identifier (e.g., 'admin', 'editor')
 * @property null|int $scope      Tenant scope identifier for multi-tenancy support
 * @property string   $title      Human-readable role display name (auto-generated if not provided)
 * @property Carbon   $updated_at Timestamp when the role was last updated
 *
 * @see IsRole For role-specific behavior and relationship methods
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Role extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasWardenPrimaryKey;
    use IsRole;

    /**
     * The attributes that are mass assignable.
     *
     * Allows 'name', 'title', and 'guard_name' to be set via create() or fill() methods.
     * The 'scope' attribute is managed automatically by the scoping system.
     *
     * @var list<string>
     */
    protected $fillable = ['name', 'title', 'guard_name'];

    /**
     * The attributes that should be cast to native types.
     *
     * Ensures the ID is always returned as an integer when accessed,
     * regardless of the underlying database driver's type handling.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'int',
    ];

    /**
     * Create a new Role model instance.
     *
     * Dynamically sets the table name from the Models registry to support
     * custom table name configuration. This allows the table name to be
     * changed without modifying the model class.
     *
     * @param array<string, mixed> $attributes Initial attribute values
     */
    public function __construct(array $attributes = [])
    {
        $this->table = Models::table('roles');

        parent::__construct($attributes);
    }
}
