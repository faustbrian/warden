<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Database;

use Cline\Warden\Database\Concerns\HasWardenPrimaryKey;
use Cline\Warden\Database\Concerns\IsAbility;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * Represents an authorization ability in the system.
 *
 * Abilities define specific actions that can be granted or forbidden for
 * authorities (users, roles, etc.). Each ability can be scoped to specific
 * entity types and includes optional constraints for fine-grained control.
 *
 * @property int         $id
 * @property string      $name
 * @property bool        $only_owned
 * @property null|string $options
 * @property null|int    $subject_id
 * @property null|string $subject_type
 * @property null|string $title
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Ability extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasWardenPrimaryKey;
    use IsAbility;

    /**
     * The attributes that are mass assignable.
     *
     * Includes the ability's unique identifier (name), human-readable
     * display title, and guard_name. Additional attributes like subject_type,
     * subject_id, and constraints are typically set via relationships or methods.
     *
     * @var list<string>
     */
    protected $fillable = ['name', 'title', 'guard_name'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'only_owned' => 'boolean',
    ];

    /**
     * Get the table associated with the model.
     *
     * Returns the configured table name from the Models registry, allowing
     * custom table name configuration via Models::setTables().
     *
     * @return string The physical database table name for abilities
     */
    #[Override()]
    public function getTable(): string
    {
        return Models::table('abilities');
    }
}
