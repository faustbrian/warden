<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Database;

use Cline\Warden\Database\Concerns\IsAbility;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
    use IsAbility;

    /**
     * The attributes that are mass assignable.
     *
     * Includes the ability's unique identifier (name) and human-readable
     * display title. Additional attributes like subject_type, subject_id,
     * and constraints are typically set via relationships or methods.
     *
     * @var list<string>
     */
    protected $fillable = ['name', 'title'];

    /**
     * The attributes that should be cast to native types.
     *
     * Ensures proper type conversion when reading from and writing to
     * the database. The only_owned flag controls whether the ability
     * is restricted to entities owned by the authority.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'int',
        'subject_id' => 'int',
        'only_owned' => 'boolean',
    ];

    /**
     * Create a new ability model instance.
     *
     * Dynamically sets the table name from configuration to support
     * custom table naming conventions in different applications.
     *
     * @param array<string, mixed> $attributes Initial model attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->table = Models::table('abilities');

        parent::__construct($attributes);
    }
}
