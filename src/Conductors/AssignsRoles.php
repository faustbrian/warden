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
use Cline\Warden\Support\CharDetector;
use Cline\Warden\Support\Helpers;
use Cline\Warden\Support\PrimaryKeyGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

use function array_map;
use function assert;
use function config;
use function is_array;
use function is_int;
use function is_string;
use function method_exists;

/**
 * Conductor for assigning roles to authorities in bulk.
 *
 * Handles the complex process of attaching roles to multiple authorities efficiently.
 * Creates role records if they don't exist, manages polymorphic relationships, and
 * avoids duplicate pivot table entries through intelligent diffing against existing
 * assignments.
 *
 * @see     ChecksRoles For role verification
 *
 * @author  Brian Faust <brian@cline.sh>
 */
final class AssignsRoles
{
    /**
     * The boundary modelwithin which roles are assigned.
     */
    private ?Model $boundary = null;

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
     * Set the boundary for boundary-scoped role assignments.
     *
     * @param  Model $boundary The boundary model instance
     * @return self  Fluent interface for method chaining
     */
    public function within(Model $boundary): self
    {
        $this->boundary = $boundary;

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
     * Uses Eloquent's sync/attach methods to create relationships, letting
     * Eloquent handle pivot record creation, events, and primary key generation.
     *
     * @param Collection<int, Role>  $roles          Collection of role models to assign
     * @param class-string           $authorityClass The authority model class name
     * @param Collection<int, mixed> $authorityIds   Collection of authority IDs to assign roles to
     */
    private function assignRoles(Collection $roles, string $authorityClass, Collection $authorityIds): void
    {
        /** @var Collection<int, int> */
        $roleIds = $roles->pluck('id');

        foreach ($authorityIds as $authorityId) {
            /** @var Builder<Model> $query */
            $query = $authorityClass::query();

            // Include soft-deleted models if the model uses SoftDeletes
            if (method_exists($authorityClass, 'bootSoftDeletes')) {
                /** @phpstan-ignore-next-line withTrashed() is dynamically added by SoftDeletes trait */
                $query->withTrashed();
            }

            /** @var null|Model $authority */
            $authority = $query->where(Models::getModelKeyFromClass($authorityClass), $authorityId)->first();

            if ($authority === null) {
                continue;
            }

            $pivotData = Models::scope()->getAttachAttributes();

            if ($this->boundary instanceof Model) {
                $pivotData['boundary_id'] = $this->boundary->getAttribute(Models::getModelKey($this->boundary));
                $pivotData['boundary_type'] = $this->boundary->getMorphClass();
            }

            // Get existing role IDs to avoid duplicates (accounting for scope)
            /** @phpstan-ignore-next-line Dynamic relationship */
            $existing = $authority->roles()->pluck(Models::table('roles').'.id')->all();

            // Only attach roles that don't already exist in this scope
            /** @var array<int> $existing */
            $toAttach = $roleIds->diff($existing);

            Log::channel('migration')->debug('AssignsRoles debug', [
                'authority' => $authority->getKey(),
                'roleIds' => $roleIds->all(),
                'existing' => $existing,
                'toAttach' => $toAttach->all(),
            ]);

            if ($toAttach->isNotEmpty()) {
                /** @phpstan-ignore-next-line Dynamic relationship */
                $authority->roles()->attach(
                    PrimaryKeyGenerator::enrichPivotDataForIds($toAttach->all(), $pivotData),
                );
            }
        }
    }

    /**
     * Find or create roles with guard filtering.
     *
     * Resolves role identifiers into role models. For Role instances, uses them directly.
     * For integer IDs or UUID/ULID strings, looks up existing roles. For plain strings,
     * creates roles with the configured guard name if they don't exist.
     *
     * @param  array<int|Role|string> $roles Array of role IDs, names, or Role instances
     * @return Collection<int, Role>  Collection of resolved role models
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
