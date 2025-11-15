<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Database\Concerns;

use Cline\Warden\Database\Models;
use Cline\Warden\Database\Queries\Roles as RolesQuery;
use Cline\Warden\Database\Scope\TenantScope;
use Cline\Warden\Database\Titles\RoleTitle;
use Cline\Warden\Support\Helpers;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

use function array_map;
use function assert;
use function config;

/**
 * Provides role-specific behavior and relationships to role models.
 *
 * This trait transforms an Eloquent model into a Warden role, providing user
 * and ability relationships, role assignment/retraction methods, automatic title
 * generation, and tenant scoping. Combines authorization checking and ability
 * management through composed traits.
 *
 * @author Brian Faust <brian@cline.sh>
 */
trait IsRole
{
    use Authorizable;
    use HasAbilities;

    /**
     * Boot the IsRole trait for model lifecycle events.
     *
     * Registers tenant scoping, creation hooks for auto-title generation,
     * and deletion hooks to clean up ability associations when a role is deleted.
     */
    public static function bootIsRole(): void
    {
        TenantScope::register(static::class);

        static::creating(function ($role): void {
            assert($role instanceof Model);
            Models::scope()->applyToModel($role);

            // @phpstan-ignore property.notFound
            if (null === $role->title) {
                // @phpstan-ignore property.notFound
                $role->title = RoleTitle::from($role)->toString();
            }
        });

        static::deleted(function ($role): void {
            assert($role instanceof Model);
            // @phpstan-ignore method.notFound, method.nonObject
            $role->abilities()->detach();
        });
    }

    /**
     * Define the polymorphic many-to-many relationship with users.
     *
     * Returns all users that have been assigned this role. The relationship
     * is scoped to the current tenant and includes the scope column in the
     * pivot table for multi-tenancy support.
     *
     * @return MorphToMany<Model, static>
     */
    public function users(): MorphToMany
    {
        /** @var class-string<Model> $userModel */
        $userModel = config('warden.user_model', 'App\Models\User');

        /** @var class-string<Model> $userClass */
        $userClass = Models::classname($userModel);

        // @phpstan-ignore argument.type, return.type
        $relation = $this->morphedByMany(
            $userClass,
            'actor',
            Models::table('assigned_roles'),
        )->withPivot('scope');

        // @phpstan-ignore argument.type, return.type
        return Models::scope()->applyToRelation($relation);
    }

    /**
     * Assign this role to one or more models.
     *
     * Bulk-assigns this role to the specified model(s), creating pivot records
     * in the assigned_roles table. Accepts a single model instance, a collection
     * of models, or a model class with an array of IDs. Respects tenant scope.
     *
     * @param \Illuminate\Database\Eloquent\Collection<int, Model>|Model|string $model Model instance(s) or class name
     * @param null|array<int>                                                   $keys  Optional array of model IDs when passing a class name
     */
    public function assignTo($model, ?array $keys = null): static
    {
        // @phpstan-ignore offsetAccess.nonArray, argument.type
        [$model, $keys] = Helpers::extractModelAndKeys($model, $keys);

        $query = $this->newBaseQueryBuilder()->from(Models::table('assigned_roles'));

        // @phpstan-ignore argument.type
        $query->insert($this->createAssignRecords($model, $keys));

        return $this;
    }

    /**
     * Find roles by various identifiers, creating missing roles by name.
     *
     * Accepts mixed input (IDs, names, or model instances) and returns a unified
     * collection. Role names that don't exist in the database are automatically
     * created. Useful for ensuring role existence before assignment.
     *
     * @param  iterable<int|static|string>                           $roles Mix of role IDs, names, or instances
     * @return \Illuminate\Database\Eloquent\Collection<int, static>
     */
    public function findOrCreateRoles($roles): \Illuminate\Database\Eloquent\Collection
    {
        // @phpstan-ignore argument.type
        $roles = Helpers::groupModelsAndIdentifiersByType($roles);

        $roles['integers'] = $this->find($roles['integers']);

        $roles['strings'] = $this->findOrCreateRolesByName($roles['strings']);

        // @phpstan-ignore argument.type
        return $this->newCollection(Arr::collapse($roles));
    }

    /**
     * Extract primary keys from mixed role identifiers.
     *
     * Normalizes various role identifier formats (IDs, names, model instances)
     * into a flat array of primary keys. Queries the database to resolve names
     * to IDs when necessary.
     *
     * @param  iterable<int|static|string> $roles Mix of role IDs, names, or instances
     * @return array<int>                  Array of role primary keys
     */
    public function getRoleKeys($roles): array
    {
        // @phpstan-ignore argument.type
        $roles = Helpers::groupModelsAndIdentifiersByType($roles);

        $roles['strings'] = $this->getKeysByName($roles['strings']);

        $roles['models'] = Arr::pluck($roles['models'], $this->getKeyName());

        /** @var array<int> */
        return Arr::collapse($roles);
    }

    /**
     * Extract role names from mixed role identifiers.
     *
     * Normalizes various role identifier formats (IDs, names, model instances)
     * into a flat array of role names. Queries the database to resolve IDs
     * to names when necessary.
     *
     * @param  iterable<int|static|string> $roles Mix of role IDs, names, or instances
     * @return array<string>               Array of role names
     */
    public function getRoleNames($roles): array
    {
        // @phpstan-ignore argument.type
        $roles = Helpers::groupModelsAndIdentifiersByType($roles);

        $roles['integers'] = $this->getNamesByKey($roles['integers']);

        $roles['models'] = Arr::pluck($roles['models'], 'name');

        /** @var array<string> */
        return Arr::collapse($roles);
    }

    /**
     * Query role IDs by their names.
     *
     * Performs a database lookup to find the primary keys of roles matching
     * the given names. Returns an empty array if no names are provided.
     *
     * @param  iterable<string> $names Role names to look up
     * @return array<int>       Array of matching role IDs
     */
    public function getKeysByName($names): array
    {
        if (empty($names)) {
            return [];
        }

        /** @var array<int> */
        return $this->whereIn('name', $names)
            ->select($this->getKeyName())->get()
            ->pluck($this->getKeyName())->all();
    }

    /**
     * Query role names by their IDs.
     *
     * Performs a database lookup to find the names of roles matching the
     * given primary keys. Returns an empty array if no keys are provided.
     *
     * @param  iterable<int> $keys Role IDs to look up
     * @return array<string> Array of matching role names
     */
    public function getNamesByKey($keys): array
    {
        if (empty($keys)) {
            return [];
        }

        /** @var array<string> */
        return $this->whereIn($this->getKeyName(), $keys)
            ->select('name')->get()
            ->pluck('name')->all();
    }

    /**
     * Remove this role from one or more models.
     *
     * Bulk-removes this role assignment from the specified model(s) by deleting
     * pivot records from the assigned_roles table. Accepts the same input formats
     * as assignTo(). Only affects assignments within the current tenant scope.
     *
     * @param \Illuminate\Database\Eloquent\Collection<int, Model>|Model|string $model Model instance(s) or class name
     * @param null|array<int>                                                   $keys  Optional array of model IDs when passing a class name
     */
    public function retractFrom($model, ?array $keys = null): static
    {
        // @phpstan-ignore offsetAccess.nonArray, argument.type
        [$model, $keys] = Helpers::extractModelAndKeys($model, $keys);

        $query = $this->newBaseQueryBuilder()
            ->from(Models::table('assigned_roles'))
            ->where('role_id', $this->getKey())
            ->where('actor_type', $model->getMorphClass())
            ->whereIn('actor_id', $keys);

        // @phpstan-ignore argument.type
        Models::scope()->applyToRelationQuery($query, $query->from);

        $query->delete();

        return $this;
    }

    /**
     * Query scope to filter roles assigned to specific models.
     *
     * Constrains the roles query to only include roles that have been assigned
     * to at least one of the specified models. Useful for finding all roles
     * shared by a set of users or other entities.
     *
     * @param Builder<static>                                                   $query The query builder instance
     * @param \Illuminate\Database\Eloquent\Collection<int, Model>|Model|string $model Model instance(s) or class name
     * @param null|array<int>                                                   $keys  Optional array of model IDs when passing a class name
     */
    protected function scopeWhereAssignedTo(Builder $query, \Illuminate\Database\Eloquent\Collection|Model|string $model, ?array $keys = null): void
    {
        // @phpstan-ignore argument.type
        new RolesQuery()->constrainWhereAssignedTo($query, $model, $keys);
    }

    /**
     * Find roles by name, creating missing roles on-the-fly.
     *
     * Queries for existing roles matching the given names, then creates new
     * role records for any names not found. Returns a merged collection of
     * existing and newly-created roles.
     *
     * @param  iterable<string>                     $names Role names to find or create
     * @return array<never>|Collection<int, static>
     */
    protected function findOrCreateRolesByName($names): Collection|array
    {
        if (empty($names)) {
            return [];
        }

        /** @phpstan-ignore-next-line staticMethod.notFound, method.nonObject - Eloquent query builder methods */
        $existing = static::whereIn('name', $names)->get()->keyBy('name');

        /** @var Collection<int, string> */
        // @phpstan-ignore argument.type
        $names = new Collection($names);

        /** @phpstan-ignore-next-line method.nonObject, argument.type - Eloquent collection methods */
        $missingNames = $names->diff($existing->pluck('name'));

        /** @phpstan-ignore-next-line staticMethod.notFound, argument.templateType - Eloquent model create */
        $newRoles = $missingNames->map(fn ($name) => static::create(['name' => $name]));

        /** @phpstan-ignore-next-line argument.type - Merging collections */
        return $newRoles->merge($existing);
    }

    /**
     * Build pivot table records for bulk role assignment.
     *
     * Generates an array of insert-ready records for the assigned_roles pivot table,
     * including the role ID, entity type, entity IDs, and current tenant scope
     * attributes. Used by assignTo() for efficient bulk inserts.
     *
     * @param  Model                            $model The model type being assigned this role
     * @param  array<int>                       $keys  Array of model IDs to create records for
     * @return array<int, array<string, mixed>> Array of pivot table records
     */
    protected function createAssignRecords(Model $model, array $keys): array
    {
        $type = $model->getMorphClass();

        /** @var array<int, array<string, mixed>> */
        return array_map(fn (int $key): array => Models::scope()->getAttachAttributes() + [
            'role_id' => $this->getKey(),
            'actor_type' => $type,
            'actor_id' => $key,
        ], $keys);
    }
}
