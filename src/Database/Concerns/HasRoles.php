<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Database\Concerns;

use Cline\Warden\Conductors\AssignsRoles;
use Cline\Warden\Conductors\RemovesRoles;
use Cline\Warden\Contracts\ClipboardInterface;
use Cline\Warden\Database\AssignedRole;
use Cline\Warden\Database\Models;
use Cline\Warden\Database\Queries\Roles as RolesQuery;
use Cline\Warden\Database\Role;
use Cline\Warden\Support\Helpers;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;

use function func_get_args;

/**
 * Provides role assignment and checking capabilities to Eloquent models.
 *
 * This trait enables models (typically User) to be assigned roles, check role
 * membership, and query by roles. Roles are managed through polymorphic relationships
 * and automatically cleaned up when models are hard-deleted.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @phpstan-ignore trait.unused
 */
trait HasRoles
{
    /**
     * Boot the HasRoles trait for model lifecycle events.
     *
     * Registers a model deletion listener that automatically detaches all assigned
     * roles when the model is hard-deleted (not soft-deleted). This ensures referential
     * integrity in the assigned_roles pivot table.
     */
    public static function bootHasRoles(): void
    {
        static::deleted(function ($model): void {
            if (!Helpers::isSoftDeleting($model)) {
                $model->roles()->detach();
            }
        });
    }

    /**
     * Define the polymorphic many-to-many relationship with roles.
     *
     * Returns a relationship query scoped to the current tenant (if applicable)
     * with the 'scope' column available in the pivot table for multi-tenancy support.
     *
     * @return MorphToMany<Role>
     */
    public function roles(): MorphToMany
    {
        $relation = $this->morphToMany(
            Models::classname(Role::class),
            'actor',
            Models::table('assigned_roles'),
            relatedPivotKey: null,
            parentKey: Models::getModelKey($this),
        )->using(AssignedRole::class)
            ->withPivot('scope', 'context_id', 'context_type');

        return Models::scope()->applyToRelation($relation);
    }

    /**
     * Retrieve all roles currently assigned to this model.
     *
     * Uses the Clipboard service to efficiently fetch cached role assignments
     * respecting the current tenant scope. Unlike the roles() relationship,
     * this method returns the resolved collection directly.
     *
     * @return Collection<int, Role>
     */
    public function getRoles(): Collection
    {
        return Container::getInstance()
            ->make(ClipboardInterface::class)
            ->getRoles($this);
    }

    /**
     * Assign one or more roles to this model.
     *
     * Accepts role identifiers in multiple formats: role names (strings),
     * role IDs (integers), or Role model instances. Multiple roles can be
     * passed as an array. Assignments respect the current tenant scope.
     *
     * @param  array<int|Role|string>|Role|string $roles Role name(s), ID(s), or model instance(s) to assign
     * @return $this
     */
    public function assign(array|Role|string $roles): static
    {
        new AssignsRoles($roles)->to($this);

        return $this;
    }

    /**
     * Remove one or more roles from this model.
     *
     * Accepts the same role identifier formats as assign(). Only roles currently
     * assigned to the model within the active tenant scope will be removed.
     *
     * @param  array<int|Role|string>|Role|string $roles Role name(s), ID(s), or model instance(s) to remove
     * @return $this
     */
    public function retract(array|Role|string $roles): static
    {
        new RemovesRoles($roles)->from($this);

        return $this;
    }

    /**
     * Check if the model has any of the given roles (OR logic).
     *
     * Returns true if the model is assigned at least one of the specified roles
     * within the current tenant scope. Grammatically correct for vowel-starting
     * role names (e.g., "an admin", "an editor").
     *
     * @param  string ...$roles One or more role names to check
     * @return bool   True if the model has any of the specified roles
     */
    public function isAn(string ...$roles): bool
    {
        return Container::getInstance()
            ->make(ClipboardInterface::class)
            ->checkRole($this, $roles, 'or');
    }

    /**
     * Check if the model has any of the given roles (OR logic).
     *
     * Grammatically correct alias for isAn() for consonant-starting role names
     * (e.g., "a moderator", "a manager"). Functionality is identical to isAn().
     *
     * @param  string ...$roles One or more role names to check
     * @return bool   True if the model has any of the specified roles
     */
    public function isA(string ...$roles): bool
    {
        return $this->isAn(...$roles);
    }

    /**
     * Check if the model lacks all of the given roles (NOT logic).
     *
     * Returns true only if the model has none of the specified roles within
     * the current tenant scope. Inverse of isAn().
     *
     * @param  string ...$roles One or more role names to check
     * @return bool   True if the model has none of the specified roles
     */
    public function isNotAn(string ...$roles): bool
    {
        return Container::getInstance()
            ->make(ClipboardInterface::class)
            ->checkRole($this, $roles, 'not');
    }

    /**
     * Check if the model lacks all of the given roles (NOT logic).
     *
     * Grammatically correct alias for isNotAn() for consonant-starting role names.
     * Functionality is identical to isNotAn().
     *
     * @param  string ...$roles One or more role names to check
     * @return bool   True if the model has none of the specified roles
     */
    public function isNotA(string ...$roles): bool
    {
        return $this->isNotAn(...$roles);
    }

    /**
     * Check if the model has all of the given roles (AND logic).
     *
     * Returns true only if the model is assigned every specified role within
     * the current tenant scope. Requires all roles to be present.
     *
     * @param  string ...$roles One or more role names to check
     * @return bool   True if the model has all of the specified roles
     */
    public function isAll(string ...$roles): bool
    {
        return Container::getInstance()
            ->make(ClipboardInterface::class)
            ->checkRole($this, $roles, 'and');
    }

    /**
     * Query scope to filter models that have any of the specified roles.
     *
     * Constrains the query to only include models assigned to at least one
     * of the given roles. Uses eager loading to check role relationships efficiently.
     *
     * @param Builder<static> $query   The query builder instance
     * @param string          ...$role One or more role names to filter by
     */
    protected function scopeWhereIs(Builder $query, string ...$role): void
    {
        new RolesQuery()->constrainWhereIs(...func_get_args());
    }

    /**
     * Query scope to filter models that have all of the specified roles.
     *
     * Constrains the query to only include models assigned to every one of the
     * given roles (AND logic). More restrictive than scopeWhereIs().
     *
     * @param Builder<static> $query   The query builder instance
     * @param string          ...$role One or more role names that must all be present
     */
    protected function scopeWhereIsAll(Builder $query, string ...$role): void
    {
        new RolesQuery()->constrainWhereIsAll(...func_get_args());
    }

    /**
     * Query scope to filter models that lack the specified roles.
     *
     * Constrains the query to only include models that do not have any of the
     * given roles assigned. Inverse of scopeWhereIs().
     *
     * @param Builder<static> $query   The query builder instance
     * @param string          ...$role One or more role names to exclude
     */
    protected function scopeWhereIsNot(Builder $query, string ...$role): void
    {
        new RolesQuery()->constrainWhereIsNot(...func_get_args());
    }
}
