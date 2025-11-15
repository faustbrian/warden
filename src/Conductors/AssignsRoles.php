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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use stdClass;

use function array_map;
use function assert;
use function config;
use function is_array;
use function is_int;
use function is_string;

/**
 * Conductor for assigning roles to authorities in bulk.
 *
 * Handles the complex process of attaching roles to multiple authorities efficiently.
 * Creates role records if they don't exist, manages polymorphic relationships, and
 * avoids duplicate pivot table entries through intelligent diffing against existing
 * assignments.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class AssignsRoles
{
    /**
     * The context model within which roles are assigned.
     */
    private ?Model $context = null;

    /**
     * The roles to be assigned to authorities.
     *
     * @var array<Role|string>
     */
    private readonly array $roles;

    /**
     * The guard name for role filtering.
     */
    private readonly string $guardName;

    /**
     * Create a new role assignment conductor.
     *
     * Accepts roles in various formats (names, IDs, models, collections, BackedEnums) and
     * normalizes them into an array for processing during assignment.
     *
     * @param BackedEnum|iterable<BackedEnum|Role|string>|Role|string $roles     Role identifier(s) in any supported format
     * @param null|string                                             $guardName The guard name to filter roles by
     */
    public function __construct(iterable|BackedEnum|Role|string $roles, ?string $guardName = null)
    {
        /** @var array<BackedEnum|Role|string> $normalizedRoles */
        $normalizedRoles = Helpers::toArray($roles);

        /** @var array<Role|string> $normalized */
        $normalized = array_map(Helpers::normalizeEnumValue(...), $normalizedRoles);
        $this->roles = $normalized;

        $guard = $guardName ?? config('warden.guard', 'web');
        assert(is_string($guard));
        $this->guardName = $guard;
    }

    /**
     * Set the context for context-aware role assignments.
     *
     * @param  Model $context The context model instance
     * @return self  Fluent interface for method chaining
     */
    public function within(Model $context): self
    {
        $this->context = $context;

        return $this;
    }

    /**
     * Assign the roles to the given authority or authorities.
     *
     * Finds or creates the specified roles, then efficiently assigns them to all
     * provided authorities. Handles both single authorities and arrays of authorities,
     * grouping by authority type for optimal database operations.
     *
     * @param  array<int|Model|string>|int|Model $authority Authority model(s), ID(s), or mixed array to assign roles to
     * @return bool                              Always returns true upon successful assignment
     */
    public function to(array|int|Model|string $authority): bool
    {
        /** @var array<int|Model|string> */
        $authorities = is_array($authority) ? $authority : [$authority];

        $roles = $this->findOrCreateRoles($this->roles);

        foreach (Helpers::mapAuthorityByClass($authorities) as $class => $ids) {
            /** @var Collection<int, mixed> $idCollection */
            $idCollection = new Collection($ids);
            $this->assignRoles($roles, $class, $idCollection);
        }

        return true;
    }

    /**
     * Assign the given roles to the given authorities of a specific type.
     *
     * Creates pivot table entries linking roles to authorities. Uses cross-join
     * to generate all role-authority combinations, then filters out existing
     * assignments to avoid duplicate entries.
     *
     * @param Collection<int, Role>  $roles          Collection of role models to assign
     * @param int|string             $authorityClass The authority model class name
     * @param Collection<int, mixed> $authorityIds   Collection of authority IDs to assign roles to
     */
    private function assignRoles(Collection $roles, int|string $authorityClass, Collection $authorityIds): void
    {
        /** @var Collection<int, int> */
        $roleIds = $roles->map(fn (Role $model): mixed => $model->getKey());

        /** @var Model $authority */
        $authority = new $authorityClass();
        $morphType = $authority->getMorphClass();

        $records = $this->buildAttachRecords($roleIds, $morphType, $authorityIds);

        $existing = $this->getExistingAttachRecords($roleIds, $morphType, $authorityIds);

        $this->createMissingAssignRecords($records, $existing);
    }

    /**
     * Get the pivot table records for the roles already assigned.
     *
     * Queries existing role assignments to prevent duplicate pivot entries.
     * Applies scope constraints to ensure multi-tenancy isolation when configured.
     *
     * @param  Collection<int, int>      $roleIds      Collection of role IDs to check
     * @param  string                    $morphType    The morph type of the authority class
     * @param  Collection<int, mixed>    $authorityIds Collection of authority IDs to check
     * @return Collection<int, stdClass> Existing pivot table records matching the criteria
     */
    private function getExistingAttachRecords(Collection $roleIds, string $morphType, Collection $authorityIds): Collection
    {
        $query = $this->newPivotTableQuery()
            ->whereIn('role_id', $roleIds->all())
            ->whereIn('actor_id', $authorityIds->all())
            ->where('actor_type', $morphType);

        /** @phpstan-ignore-next-line Expression type may be string */
        Models::scope()->applyToRelationQuery($query, $query->from);

        return new Collection($query->get());
    }

    /**
     * Build the raw attach records for the assigned roles pivot table.
     *
     * Creates a Cartesian product of role IDs and authority IDs to generate all
     * possible assignment combinations. Includes scope attributes for multi-tenancy
     * support when applicable.
     *
     * @param  Collection<int, int>                  $roleIds      Collection of role IDs to assign
     * @param  string                                $morphType    The morph type of the authority class
     * @param  Collection<int, mixed>                $authorityIds Collection of authority IDs to receive roles
     * @return Collection<int, array<string, mixed>> Collection of pivot record arrays ready for insertion
     */
    private function buildAttachRecords(Collection $roleIds, string $morphType, Collection $authorityIds): Collection
    {
        /** @var Collection<int, array<string, mixed>> */
        return $roleIds
            ->crossJoin($authorityIds)
            /** @phpstan-ignore-next-line */
            ->mapSpread(function (int $roleId, mixed $authorityId) use ($morphType): array {
                $attributes = Models::scope()->getAttachAttributes() + [
                    'role_id' => $roleId,
                    'actor_id' => $authorityId,
                    'actor_type' => $morphType,
                ];

                if ($this->context instanceof Model) {
                    $attributes['context_id'] = $this->context->getAttribute(Models::getModelKey($this->context));
                    $attributes['context_type'] = $this->context->getMorphClass();
                }

                return $attributes;
            });
    }

    /**
     * Save the non-existing attach records to the database.
     *
     * Filters out records that already exist by comparing hashed keys, then
     * bulk inserts only the new assignments. This prevents duplicate key errors
     * and ensures idempotent role assignment operations.
     *
     * @param Collection<int, array<string, mixed>> $records  All potential assignment records to insert
     * @param Collection<int, stdClass>             $existing Existing assignment records from the database
     */
    private function createMissingAssignRecords(Collection $records, Collection $existing): void
    {
        /** @var Collection<string, mixed> $existing */
        $existing = $existing->keyBy(function (stdClass $record): string {
            /** @var array<string, mixed> $recordArray */
            $recordArray = (array) $record;

            return $this->getAttachRecordHash($recordArray);
        });

        $records = $records->reject(fn (array $record) => $existing->has($this->getAttachRecordHash($record)));

        $this->newPivotTableQuery()->insert($records->all());
    }

    /**
     * Get a unique string identifying the given attach record.
     *
     * Concatenates role ID, entity ID, and entity type to create a composite key
     * for deduplicating assignments. This hash enables efficient lookup of existing
     * assignments without additional database queries.
     *
     * @param  array<string, mixed> $record The pivot record containing role_id, actor_id, and actor_type
     * @return string               Composite hash uniquely identifying the assignment
     */
    private function getAttachRecordHash(array $record): string
    {
        /** @phpstan-ignore-next-line Array keys guaranteed by buildAttachRecords */
        return $record['role_id'].$record['actor_id'].$record['actor_type'];
    }

    /**
     * Find or create roles with guard filtering.
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
