<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Conductors;

use Cline\Warden\Conductors\Concerns\FindsAndCreatesAbilities;
use Cline\Warden\Database\Models;
use Cline\Warden\Database\Role;
use Cline\Warden\Support\CharDetector;
use Cline\Warden\Support\PrimaryKeyGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

use function array_diff;
use function assert;
use function config;
use function explode;
use function is_array;
use function is_int;
use function is_string;
use function iterator_to_array;
use function last;

/**
 * Synchronizes roles and abilities for authorities.
 *
 * This conductor handles the synchronization of role and ability assignments,
 * ensuring that an authority has exactly the specified set of roles or abilities.
 * Unlike adding or removing individual items, sync operations provide an atomic
 * replacement that detaches removed items and attaches new ones in a single operation.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class SyncsRolesAndAbilities
{
    use FindsAndCreatesAbilities;

    /**
     * The guard name for role filtering.
     *
     * Specifies which authentication guard these roles and abilities apply to,
     * enabling support for multiple authentication systems within a single
     * application. Defaults to the configured Warden guard (typically 'web').
     */
    protected string $guardName;

    /**
     * Create a new sync conductor instance.
     *
     * @param Model|string $authority The entity whose roles/abilities to synchronize.
     *                                Can be a Model instance (typically a User or Role)
     *                                or string identifier representing a role name that
     *                                will be resolved to a role model if needed.
     * @param null|string  $guardName The guard name to filter roles by
     */
    public function __construct(
        private Model|string $authority,
        ?string $guardName = null,
    ) {
        $guard = $guardName ?? config('warden.guard', 'web');
        assert(is_string($guard));
        $this->guardName = $guard;
    }

    /**
     * Sync the provided roles to the authority.
     *
     * Replaces the authority's current role assignments with the provided set.
     * Removes any roles not in the provided list and adds any that are missing,
     * resulting in the authority having exactly the specified roles.
     *
     * @param iterable<int|Role|string> $roles Collection or array of role identifiers (IDs, names,
     *                                         or Role model instances) that should be assigned
     *                                         to the authority. After sync, the authority will
     *                                         have exactly these roles and no others.
     */
    public function roles(iterable $roles): void
    {
        $authority = $this->getAuthority();

        /**
         * Authority models use the HasRoles trait which provides the roles() relationship method.
         *
         * @phpstan-ignore-next-line method.notFound
         */
        $rolesRelation = $authority->roles();

        /** @var array<int|Role|string> $rolesArray */
        $rolesArray = is_array($roles) ? $roles : iterator_to_array($roles);

        $roleModels = $this->findOrCreateRoles($rolesArray);

        /** @var BelongsToMany<Model, Model> $rolesRelation */
        /** @var array<int> $roleIds */
        $roleIds = $roleModels->pluck($roleModels->first()?->getKeyName() ?? 'id')->all();
        $this->sync($roleIds, $rolesRelation);
    }

    /**
     * Sync the provided abilities to the authority.
     *
     * Replaces the authority's current granted abilities with the provided set.
     * Removes abilities not in the list and adds missing ones, ensuring the
     * authority has exactly the specified non-forbidden abilities.
     *
     * @param iterable<mixed> $abilities Collection or array of ability identifiers to assign.
     *                                   After sync, the authority will have exactly these granted
     *                                   abilities and no others (forbidden abilities are unaffected).
     */
    public function abilities(iterable $abilities): void
    {
        $this->syncAbilities($abilities);
    }

    /**
     * Sync the provided forbidden abilities to the authority.
     *
     * Replaces the authority's current forbidden abilities with the provided set.
     * This manages explicit denials separately from grants, allowing fine-grained
     * control over what an authority is explicitly prevented from doing.
     *
     * @param iterable<mixed> $abilities Collection or array of ability identifiers to forbid.
     *                                   After sync, the authority will have exactly these forbidden
     *                                   abilities and no others (granted abilities are unaffected).
     */
    public function forbiddenAbilities(iterable $abilities): void
    {
        $this->syncAbilities($abilities, ['forbidden' => true]);
    }

    /**
     * Detach the records with the given IDs from the relationship.
     *
     * Removes pivot table entries for the specified IDs from the given relationship.
     * This is a lower-level operation used by the sync method to clean up removed
     * associations before attaching the new set.
     *
     * @param array<int>                  $ids      Array of pivot table IDs (related model IDs) to remove
     *                                              from the relationship. If empty, no action is taken.
     * @param BelongsToMany<Model, Model> $relation The Eloquent relationship from which to detach records
     */
    public function detach(array $ids, BelongsToMany $relation): void
    {
        if ($ids === []) {
            return;
        }

        $this->newPivotQuery($relation)
            ->whereIn($relation->getRelatedPivotKeyName(), $ids)
            ->delete();
    }

    /**
     * Sync the given abilities for the authority.
     *
     * Removes abilities not in the provided set and adds missing ones. Uses
     * separate conductors (ForbidsAbilities or GivesAbilities) to handle the
     * attachment, ensuring proper constraint application and preventing
     * duplicate ability assignments.
     *
     * @param iterable<mixed>     $abilities Collection or array of ability identifiers to sync
     * @param array<string, bool> $options   Configuration options, primarily the 'forbidden'
     *                                       flag (default: false) to distinguish between granted
     *                                       and denied abilities
     */
    private function syncAbilities(iterable $abilities, array $options = ['forbidden' => false]): void
    {
        /** @var array<int|string, mixed> $abilitiesArray */
        $abilitiesArray = is_array($abilities) ? $abilities : iterator_to_array($abilities);

        /** @var array<int, int> $abilityKeys */
        $abilityKeys = $this->getAbilityIds($abilitiesArray);
        $authority = $this->getAuthority();

        /**
         * Authority models use the HasAbilities trait which provides the abilities() relationship method.
         *
         * @phpstan-ignore-next-line method.notFound
         */
        $relation = $authority->abilities();

        /** @var BelongsToMany<Model, Model> $relation */
        $this->newPivotQuery($relation)
            ->where('actor_type', $authority->getMorphClass())
            ->whereNotIn($relation->getRelatedPivotKeyName(), $abilityKeys)
            ->where('forbidden', $options['forbidden'])
            ->delete();

        if ($options['forbidden']) {
            new ForbidsAbilities($this->authority)->to($abilityKeys);
        } else {
            new GivesAbilities($this->authority)->to($abilityKeys);
        }
    }

    /**
     * Get (and cache) the authority for whom to sync roles/abilities.
     *
     * If the authority was provided as a string, this method resolves it to a
     * role model, creating the role if it doesn't exist. The resolved model is
     * cached in the property to avoid repeated database queries.
     *
     * @return Model The resolved authority model instance
     */
    private function getAuthority(): Model
    {
        if (is_string($this->authority)) {
            $this->authority = Models::role()::query()
                ->firstOrCreate(
                    ['name' => $this->authority, 'guard_name' => $this->guardName],
                    ['name' => $this->authority, 'guard_name' => $this->guardName],
                );
        }

        return $this->authority;
    }

    /**
     * Sync the given IDs on the pivot relation.
     *
     * This is a heavily-modified version of Eloquent's built-in BelongsToMany@sync
     * method. The custom implementation is necessary because the scope applies closure
     * constraints to the pivot table, which are incompatible with Eloquent's standard
     * sync logic. Detaches IDs not in the new set and attaches missing IDs.
     *
     * @param array<int>                  $ids      array of related model IDs that should be attached
     * @param BelongsToMany<Model, Model> $relation the relationship on which to perform the sync
     */
    private function sync(array $ids, BelongsToMany $relation): void
    {
        /** @var array<int> */
        $current = $this->pluck(
            $this->newPivotQuery($relation),
            $relation->getRelatedPivotKeyName(),
        );

        /** @var array<int> */
        $toDetach = array_diff($current, $ids);
        $this->detach($toDetach, $relation);

        $relation->attach(
            PrimaryKeyGenerator::enrichPivotDataForIds(
                array_diff($ids, $current),
                Models::scope()->getAttachAttributes($this->authority),
            ),
        );
    }

    /**
     * Get a scoped query for the pivot table.
     *
     * Creates a query builder instance for the relationship's pivot table with
     * scope-based constraints applied. This ensures multi-tenancy and other
     * global scopes are respected during sync operations.
     *
     * @param  BelongsToMany<Model, Model>                          $relation the relationship whose pivot table to query
     * @return Builder|\Illuminate\Database\Eloquent\Builder<Model> Query builder for the scoped pivot table
     */
    private function newPivotQuery(BelongsToMany $relation): \Illuminate\Database\Eloquent\Builder|Builder
    {
        $parent = $relation->getParent();

        $query = $relation
            ->newPivotStatement()
            ->where('actor_type', $this->getAuthority()->getMorphClass())
            ->where(
                $relation->getForeignPivotKeyName(),
                $parent->getAttribute(Models::getModelKey($parent)),
            );

        return Models::scope()->applyToRelationQuery(
            $query,
            $relation->getTable(),
        );
    }

    /**
     * Pluck the values of the given column using the provided query.
     *
     * Executes the query and extracts a single column's values from the results.
     * Handles dot-notation column names by extracting the final segment for array access.
     *
     * @param  Builder|\Illuminate\Database\Eloquent\Builder<Model> $query  query builder instance to execute
     * @param  string                                               $column column name to pluck, may use dot-notation for nested access
     * @return array<string>                                        Array of column values from the query results
     */
    private function pluck(\Illuminate\Database\Eloquent\Builder|Builder $query, string $column): array
    {
        /** @var string */
        $key = last(explode('.', $column));

        /** @var array<string> */
        return Arr::pluck($query->get([$column]), $key);
    }

    /**
     * Find or create roles with guard filtering.
     *
     * Resolves role identifiers into role model instances, creating missing roles
     * as needed with proper guard name assignment. Handles multiple input formats:
     * Role instances (used as-is), integer IDs (looked up), and string names
     * (found or created with guard).
     *
     * @param  array<int|Role|string> $roles Array of role IDs, names, or Role instances
     * @return Collection<int, Role>  Collection of role models
     */
    private function findOrCreateRoles(array $roles): Collection
    {
        $result = new Collection();

        foreach ($roles as $role) {
            if ($role instanceof Role) {
                $result->push($role);
            } elseif (is_int($role)) {
                // Look up role by ID
                $roleModel = Models::role()::query()->find($role);

                if ($roleModel) {
                    $result->push($roleModel);
                }
            } elseif (CharDetector::isUuidOrUlid($role)) {
                // Look up role by ULID/UUID string ID
                $roleModel = Models::role()::query()->find($role);

                if ($roleModel) {
                    $result->push($roleModel);
                }
            } else {
                // Create or find role by name with guard
                $result->push(
                    Models::role()::query()
                        ->firstOrCreate(
                            ['name' => $role, 'guard_name' => $this->guardName],
                            ['name' => $role, 'guard_name' => $this->guardName],
                        ),
                );
            }
        }

        return $result;
    }
}
