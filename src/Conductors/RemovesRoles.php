<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Conductors;

use BackedEnum;
use Cline\Warden\Database\Models;
use Cline\Warden\Database\Role;
use Cline\Warden\Support\Helpers;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

use function array_map;
use function assert;
use function config;
use function is_array;
use function is_int;
use function is_string;

/**
 * Removes role assignments from authorities.
 *
 * This conductor handles the detachment of roles from one or more authority
 * entities. It resolves role identifiers to database IDs and efficiently
 * removes the assignments from the pivot table, supporting both single and
 * batch authority operations.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class RemovesRoles
{
    /**
     * The roles to be removed.
     *
     * Normalized array of role identifiers which may be Role model instances
     * or string names that will be resolved to IDs before removal.
     *
     * @var array<Role|string>
     */
    private array $roles;

    /**
     * The guard name for role filtering.
     *
     * Specifies which authentication guard these roles apply to, enabling
     * support for multiple authentication systems within a single application.
     * Only roles matching this guard name will be considered for removal.
     */
    private string $guardName;

    /**
     * Create a new role remover instance.
     *
     * @param array<BackedEnum|Role|string>|BackedEnum|Collection<int, BackedEnum|Role|string>|Role|string $roles     Single role identifier, array of identifiers,
     *                                                                                                                or collection of roles to be removed from authorities.
     *                                                                                                                Accepts Role model instances, string role names, or
     *                                                                                                                BackedEnum instances which will be normalized and
     *                                                                                                                resolved to database IDs during removal.
     * @param null|string                                                                                  $guardName The guard name to filter roles by. Defaults to the
     *                                                                                                                configured guard name if not provided.
     */
    public function __construct(array|BackedEnum|Collection|Role|string $roles, ?string $guardName = null)
    {
        /** @var array<BackedEnum|Role|string> $rolesArray */
        $rolesArray = Helpers::toArray($roles);

        /** @var array<Role|string> $normalized */
        $normalized = array_map(Helpers::normalizeEnumValue(...), $rolesArray);
        $this->roles = $normalized;

        $guard = $guardName ?? config('warden.guard', 'web');
        assert(is_string($guard));
        $this->guardName = $guard;
    }

    /**
     * Remove the roles from the given authority or authorities.
     *
     * Detaches all specified roles from one or more authorities. Supports
     * batch operations by accepting an array of authorities, automatically
     * grouping them by class for efficient bulk removal queries.
     *
     * @param array<Model>|int|Model $authority Single authority Model instance, authority ID,
     *                                          or array of authority models from which to remove
     *                                          the roles. When processing multiple authorities,
     *                                          they are grouped by class for optimal performance.
     */
    public function from(array|int|Model $authority): void
    {
        if (!($roleIds = $this->getRoleIds())) {
            return;
        }

        $authorities = is_array($authority) ? $authority : [$authority];

        foreach (Helpers::mapAuthorityByClass($authorities) as $class => $keys) {
            $this->retractRoles($roleIds, $class, $keys);
        }
    }

    /**
     * Get the IDs of any existing roles provided.
     *
     * Resolves the mixed role input (models and names) to database IDs.
     * Role models provide their IDs directly, while string names require
     * a database lookup to find matching role records.
     *
     * @return array<int|string> Array of role primary key values
     */
    private function getRoleIds(): array
    {
        $partitions = Helpers::partition($this->roles, fn (mixed $role): bool => $role instanceof Model);

        /** @var Collection<int, Model> $models */
        $models = $partitions->get(0, new Collection());

        /** @var Collection<int, string> $names */
        $names = $partitions->get(1, new Collection());

        /** @var Collection<int, int|string> $ids */
        $ids = $models->map(function (Model $model): int|string {
            $key = $model->getKey();
            assert(is_int($key) || is_string($key));

            return $key;
        });

        if ($names->count()) {
            $ids = $ids->merge($this->getRoleIdsFromNames($names->all()));
        }

        return $ids->all();
    }

    /**
     * Get the IDs of the roles with the given names.
     *
     * Queries the database to find role records matching the provided names
     * and extracts their primary key values for use in detachment queries.
     * Only returns IDs for roles that exist in the database.
     *
     * @param  array<string>               $names Array of role names to look up in the database
     * @return Collection<int, int|string> Collection of role IDs corresponding to the given names
     */
    private function getRoleIdsFromNames(array $names): Collection
    {
        $key = Models::role()->getKeyName();

        /** @var Collection<int, int|string> */
        return Models::role()::query()
            ->whereIn('name', $names)
            ->where('guard_name', $this->guardName)
            ->get([$key])
            ->pluck($key);
    }

    /**
     * Retract the given roles from the given authorities.
     *
     * Builds a deletion query that removes all combinations of the specified
     * role IDs and authority IDs from the assigned_roles pivot table. Uses
     * orWhere clauses to handle multiple authority-role pairs efficiently
     * in a single database query.
     *
     * @param array<int|string> $roleIds        Array of role primary keys to detach from authorities
     * @param class-string      $authorityClass The fully-qualified class name of the authority model
     * @param array<int|string> $authorityIds   Array of authority primary keys from which to remove roles
     */
    private function retractRoles(array $roleIds, string $authorityClass, array $authorityIds): void
    {
        $query = $this->newPivotTableQuery();

        /** @var Model $instance */
        $instance = new $authorityClass();
        $morphType = $instance->getMorphClass();

        foreach ($roleIds as $roleId) {
            foreach ($authorityIds as $authorityId) {
                $query->orWhere($this->getDetachQueryConstraint(
                    $roleId,
                    $authorityId,
                    $morphType,
                ));
            }
        }

        $query->delete();
    }

    /**
     * Get a constraint for the detach query for the given parameters.
     *
     * Creates a closure that applies where conditions matching a specific
     * role-authority pair in the pivot table, including any scope-based
     * attributes (e.g., team_id for multi-tenancy).
     *
     * @param  int|string             $roleId      The role ID to match in the pivot table
     * @param  int|string             $authorityId The authority ID to match in the pivot table
     * @param  string                 $morphType   The morph type of the authority entity
     * @return Closure(Builder): void Closure that applies where conditions to a query builder
     */
    private function getDetachQueryConstraint(int|string $roleId, int|string $authorityId, string $morphType): Closure
    {
        return function (Builder $query) use ($roleId, $authorityId, $morphType): void {
            $query->where(Models::scope()->getAttachAttributes() + [
                'role_id' => $roleId,
                'actor_id' => $authorityId,
                'actor_type' => $morphType,
            ]);
        };
    }

    /**
     * Get a query builder instance for the assigned roles pivot table.
     *
     * @return Builder Query builder for the assigned_roles pivot table
     */
    private function newPivotTableQuery(): Builder
    {
        return Models::query('assigned_roles');
    }
}
