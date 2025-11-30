<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Database\Scope;

use Cline\Warden\Contracts\ScopeInterface;
use Cline\Warden\Database\Models;
use Cline\Warden\Database\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

use function is_scalar;
use function serialize;

/**
 * Default tenant scoping implementation for multi-tenancy support.
 *
 * This class provides data isolation in multi-tenant applications by automatically
 * filtering roles, abilities, and assignments to a specific tenant scope. Supports
 * flexible configuration including relationship-only scoping and selective role
 * ability scoping.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Scope implements ScopeInterface
{
    /**
     * The current tenant identifier for query scoping.
     *
     * When set, all queries for roles, abilities, and their relationships are
     * automatically filtered to this tenant. Set to null for global (unscoped) access.
     */
    private mixed $scope = null;

    /**
     * Whether to apply scoping only to relationships, not models themselves.
     *
     * When true, role and ability models can be queried globally, but their
     * assignments (permissions, assigned_roles) are still scoped. Useful for
     * shared role/ability definitions across tenants.
     */
    private bool $onlyScopeRelations = false;

    /**
     * Whether to scope the role-to-ability relationship.
     *
     * When false, abilities assigned to roles are global and shared across all
     * tenants. When true, even role-ability associations respect tenant scoping.
     */
    private bool $scopeRoleAbilities = true;

    /**
     * Set the active tenant scope identifier.
     *
     * Configures this scope instance to filter all queries to the specified tenant.
     * Pass null to clear the scope and allow global queries.
     *
     * @param  mixed $id Tenant identifier (typically an integer or UUID)
     * @return $this
     */
    public function to(mixed $id): self
    {
        $this->scope = $id;

        return $this;
    }

    /**
     * Configure relationship-only scoping mode.
     *
     * When enabled, role and ability models themselves are not scoped (can be
     * queried globally), but their assignments to users remain scoped. This allows
     * sharing role/ability definitions while isolating their assignments by tenant.
     *
     * @param  bool  $boolean True to scope only relationships, false to scope everything
     * @return $this
     */
    public function onlyRelations(bool $boolean = true): self
    {
        $this->onlyScopeRelations = $boolean;

        return $this;
    }

    /**
     * Disable scoping for role-ability associations.
     *
     * When called, abilities granted to roles become global and shared across
     * all tenants. Only the user-role and user-ability assignments remain scoped.
     * Useful when role permissions should be consistent across tenants.
     *
     * @return $this
     */
    public function dontScopeRoleAbilities(): self
    {
        $this->scopeRoleAbilities = false;

        return $this;
    }

    /**
     * Generate a tenant-specific cache key.
     *
     * Appends the current tenant scope to the provided cache key to ensure
     * cached authorization data is properly isolated by tenant. Returns the
     * original key unchanged if no scope is active.
     *
     * @param  string $key Base cache key
     * @return string Tenant-specific cache key
     */
    public function appendToCacheKey(string $key): string
    {
        if (null === $this->scope) {
            return $key;
        }

        return $key.'-'.(is_scalar($this->scope) ? (string) $this->scope : serialize($this->scope));
    }

    /**
     * Apply the current tenant scope to a model being created.
     *
     * Sets the scope attribute on the model if relationship-only mode is disabled
     * and a scope is active. Called automatically during model creation lifecycle.
     *
     * @param  Model $model The model to apply scoping to
     * @return Model The model with scope applied
     */
    public function applyToModel(Model $model): Model
    {
        if (!$this->onlyScopeRelations && null !== $this->scope) {
            /** @phpstan-ignore-next-line Property dynamically set for tenant scoping */
            $model->scope = $this->scope;
        }

        return $model;
    }

    /**
     * Apply tenant scoping to an Eloquent model query.
     *
     * Applies scoping constraints unless relationship-only mode is enabled.
     * Automatically resolves the table name from the query's model if not provided.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>|\Illuminate\Database\Query\Builder $query The query builder to scope
     * @param  null|string                                        $table Optional table name (auto-detected if not provided)
     * @return Builder<TModel>|\Illuminate\Database\Query\Builder The scoped query
     */
    public function applyToModelQuery(Builder|\Illuminate\Database\Query\Builder $query, ?string $table = null): Builder|\Illuminate\Database\Query\Builder
    {
        if ($this->onlyScopeRelations) {
            return $query;
        }

        if (null === $table && $query instanceof Builder) {
            $table = $query->getModel()->getTable();
        }

        return $this->applyToQuery($query, $table ?? '');
    }

    /**
     * Apply tenant scoping to a relationship pivot query.
     *
     * Always applies scoping to relationship queries regardless of the
     * relationship-only mode setting, ensuring assignments are tenant-isolated.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>|\Illuminate\Database\Query\Builder $query The pivot query builder
     * @param  string                                             $table The pivot table name
     * @return Builder<TModel>|\Illuminate\Database\Query\Builder The scoped query
     */
    public function applyToRelationQuery(Builder|\Illuminate\Database\Query\Builder $query, string $table): Builder|\Illuminate\Database\Query\Builder
    {
        return $this->applyToQuery($query, $table);
    }

    /**
     * Apply tenant scoping to a BelongsToMany relationship.
     *
     * Convenience method that applies scoping to the relationship's query builder
     * and pivot table. Called automatically when relationships are loaded.
     *
     * @template TRelatedModel of Model
     * @template TDeclaringModel of Model
     *
     * @param  BelongsToMany<TRelatedModel, TDeclaringModel> $relation The relationship to scope
     * @return BelongsToMany<TRelatedModel, TDeclaringModel> The scoped relationship
     */
    public function applyToRelation(BelongsToMany $relation): BelongsToMany
    {
        $this->applyToRelationQuery(
            $relation->getQuery(),
            $relation->getTable(),
        );

        return $relation;
    }

    /**
     * Retrieve the current tenant scope identifier.
     *
     * Returns the active tenant ID being used for query filtering, or null
     * if no scope is currently active (global mode).
     *
     * @return mixed The current tenant identifier or null
     */
    public function get(): mixed
    {
        return $this->scope;
    }

    /**
     * Get pivot table attributes for the current tenant scope.
     *
     * Returns scope attributes to be added to pivot records when attaching
     * relationships. Handles special cases like role-ability associations based
     * on configuration. Returns empty array if no scope is active.
     *
     * @param  null|Model|string    $authority Optional authority class name or instance for special handling
     * @return array<string, mixed> Associative array of pivot attributes
     */
    public function getAttachAttributes(Model|string|null $authority = null): array
    {
        if (null === $this->scope) {
            return [];
        }

        $authorityClass = $authority instanceof Model ? $authority::class : $authority;

        if (!$this->scopeRoleAbilities && $this->isRoleClass($authorityClass)) {
            return [];
        }

        return ['scope' => $this->scope];
    }

    /**
     * Execute a callback with a temporary scope override.
     *
     * Temporarily changes the active scope to the provided value, executes the
     * callback, then restores the original scope. Useful for performing operations
     * in a different tenant context without affecting the global scope state.
     *
     * @param  mixed    $scope    Temporary tenant identifier to use during callback execution
     * @param  callable $callback The callback to execute with the temporary scope
     * @return mixed    The callback's return value
     */
    public function onceTo(mixed $scope, callable $callback): mixed
    {
        $mainScope = $this->scope;

        $this->scope = $scope;

        $result = $callback();

        $this->scope = $mainScope;

        return $result;
    }

    /**
     * Clear the active tenant scope.
     *
     * Disables tenant scoping, allowing queries to access data across all tenants.
     * Use with caution as this exposes global data access.
     *
     * @return $this
     */
    public function remove(): static
    {
        $this->scope = null;

        return $this;
    }

    /**
     * Execute a callback without any tenant scoping.
     *
     * Temporarily disables the scope, executes the callback with global access,
     * then restores the original scope. Shorthand for onceTo(null, $callback).
     *
     * @param  callable $callback The callback to execute without scoping
     * @return mixed    The callback's return value
     */
    public function removeOnce(callable $callback): mixed
    {
        return $this->onceTo(null, $callback);
    }

    /**
     * Apply the WHERE clause for tenant scope filtering.
     *
     * Internal method that adds the actual SQL constraints for tenant filtering.
     * Includes rows with null scope (global) or matching the active tenant scope.
     * Callers are responsible for determining if scoping should be applied.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>|\Illuminate\Database\Query\Builder $query The query to filter
     * @param  string                                             $table The table name to apply constraints to
     * @return Builder<TModel>|\Illuminate\Database\Query\Builder The filtered query
     */
    private function applyToQuery(Builder|\Illuminate\Database\Query\Builder $query, string $table): Builder|\Illuminate\Database\Query\Builder
    {
        return $query->where(function (Builder|\Illuminate\Database\Query\Builder $query) use ($table): void {
            $query->whereNull($table.'.scope');

            if (null !== $this->scope) {
                $query->orWhere($table.'.scope', $this->scope);
            }
        });
    }

    /**
     * Check if a class name matches the configured Role model.
     *
     * Internal helper to determine if special role-ability scoping rules should
     * be applied. Compares against the registered Role class from the Models registry.
     *
     * @param  null|string $className Fully-qualified class name to check
     * @return bool        True if the class is the registered Role model
     */
    private function isRoleClass(?string $className): bool
    {
        return Models::classname(Role::class) === $className;
    }
}
