<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Database;

use Cline\Ruler\Core\Proposition;
use Cline\VariableKeys\Database\Concerns\HasVariablePrimaryKey;
use Cline\Warden\Database\Concerns\IsAbility;
use Cline\Warden\Exceptions\InvalidPropositionGetterFormatException;
use Cline\Warden\Exceptions\InvalidPropositionSetterFormatException;
use Cline\Warden\Support\PropositionBuilder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Override;

use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function throw_if;

/**
 * Represents an authorization ability in the system.
 *
 * Abilities define specific actions that can be granted or forbidden for
 * authorities (users, roles, etc.). Each ability can be scoped to specific
 * entity types and includes optional constraints for fine-grained control.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @property int              $id
 * @property string           $name
 * @property bool             $only_owned
 * @property null|string      $options
 * @property null|Proposition $proposition
 * @property null|int         $subject_id
 * @property null|string      $subject_type
 * @property null|string      $title
 */
final class Ability extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;
    use HasVariablePrimaryKey;
    use IsAbility;

    /**
     * The attributes that are mass assignable.
     *
     * Includes the ability's unique identifier (name), human-readable
     * display title, guard_name, subject scoping (subject_type, subject_id),
     * and proposition for conditional permissions.
     *
     * @var list<string>
     */
    protected $fillable = ['name', 'title', 'guard_name', 'subject_type', 'subject_id', 'proposition'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'only_owned' => 'boolean',
        'proposition' => 'array',
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

    /**
     * Get the proposition attribute - rebuild from stored config.
     */
    protected function getPropositionAttribute(?string $value): ?Proposition
    {
        if (!$value) {
            return null;
        }

        $config = json_decode($value, true);

        throw_if(!is_array($config) || !isset($config['method']) || !is_string($config['method']), InvalidPropositionGetterFormatException::missingMethodKey());

        $builder = new PropositionBuilder();

        /** @var array<int, mixed> $params */
        $params = $config['params'] ?? [];
        $method = $config['method'];

        /** @var Proposition */
        return $builder->{$method}(...$params);
    }

    /**
     * Set the proposition attribute - store as JSON config.
     */
    protected function setPropositionAttribute(mixed $value): void
    {
        if ($value === null) {
            $this->attributes['proposition'] = null;

            return;
        }

        throw_if(!is_array($value), InvalidPropositionSetterFormatException::mustBeArrayConfig());

        $this->attributes['proposition'] = json_encode($value);
    }
}
