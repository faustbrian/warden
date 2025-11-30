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
use Override;

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
     * Get the table associated with the model.
     *
     * Returns the configured table name from the Models registry, allowing
     * custom table name configuration via Models::setTables().
     *
     * @return string The physical database table name for roles
     */
    #[Override()]
    public function getTable(): string
    {
        return Models::table('roles');
    }
}
