<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Defines the contract for multi-tenancy scoping in authorization.
 *
 * Enables authorization data (abilities, roles, permissions) to be scoped
 * to specific tenants or boundaries. All queries and relationships are
 * automatically filtered to the current scope, ensuring data isolation
 * between tenants in multi-tenant applications.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface ScopeInterface
{
    /**
     * Namespace cache keys with the current scope identifier.
     *
     * Ensures cache entries are isolated between scopes by appending the
     * tenant/scope identifier to cache keys. Prevents cache collisions
     * when the same ability IDs exist across multiple tenants.
     *
     * @param  string $key The base cache key
     * @return string The namespaced cache key with scope identifier
     */
    public function appendToCacheKey(string $key): string;

    /**
     * Apply scope constraints to a model instance.
     *
     * Sets the scope attribute on the model, ensuring it belongs to the
     * current tenant. Typically called when creating new authorization
     * records to automatically associate them with the active scope.
     *
     * @param  Model $model The model to scope
     * @return Model The scoped model instance
     */
    public function applyToModel(Model $model): Model;

    /**
     * Apply scope constraints to a query builder.
     *
     * Adds WHERE clauses to limit query results to the current scope.
     * Used by the authorization system to automatically filter abilities,
     * roles, and permissions to the active tenant.
     *
     * @param  Builder<Model>|\Illuminate\Database\Query\Builder $query The query to scope
     * @param  null|string                                       $table Optional table name for multi-table queries
     * @return Builder<Model>|\Illuminate\Database\Query\Builder The scoped query
     */
    public function applyToModelQuery(Builder|\Illuminate\Database\Query\Builder $query, ?string $table = null): Builder|\Illuminate\Database\Query\Builder;

    /**
     * Apply scope constraints to a relationship query.
     *
     * Filters relationship queries to the current scope. Used when loading
     * related authorization data to ensure only records from the current
     * tenant are included.
     *
     * @param  Builder<Model>|\Illuminate\Database\Query\Builder $query The relationship query to scope
     * @param  string                                            $table The table name for the relationship
     * @return Builder<Model>|\Illuminate\Database\Query\Builder The scoped query
     */
    public function applyToRelationQuery(Builder|\Illuminate\Database\Query\Builder $query, string $table): Builder|\Illuminate\Database\Query\Builder;

    /**
     * Apply scope constraints to a many-to-many relationship.
     *
     * Configures pivot table queries and inserts to include the scope
     * identifier. Ensures permission assignments and role memberships
     * are scoped to the current tenant.
     *
     * @param  BelongsToMany<Model, Model> $relation The relationship to scope
     * @return BelongsToMany<Model, Model> The scoped relationship instance
     */
    public function applyToRelation(BelongsToMany $relation): BelongsToMany;

    /**
     * Retrieve the current scope identifier value.
     *
     * Returns the active tenant/scope ID that's being used to filter
     * all authorization queries. May be an integer, string, or other
     * identifier depending on the scope implementation.
     *
     * @return mixed The current scope identifier (e.g., tenant ID)
     */
    public function get(): mixed;

    /**
     * Get additional pivot attributes for relationship attachments.
     *
     * Provides the scope identifier and any other attributes that should
     * be automatically included when attaching authorization records to
     * relationships (e.g., when granting abilities or assigning roles).
     *
     * @param  null|Model|string    $authority Optional authority identifier for additional context
     * @return array<string, mixed> Associative array of pivot attributes
     */
    public function getAttachAttributes(Model|string|null $authority = null): array;

    /**
     * Execute a callback with a temporary scope override.
     *
     * Temporarily changes the active scope for the duration of the callback,
     * then restores the original scope. Useful for administrative operations
     * that need to access or modify data across multiple tenants.
     *
     * @param  mixed    $scope    The temporary scope identifier to use
     * @param  callable $callback The callback to execute with the temporary scope
     * @return mixed    The callback's return value
     *
     * @codeCoverageIgnore Coverage tool cannot track interface method signatures
     */
    public function onceTo($scope, callable $callback): mixed;

    /**
     * Disable scoping entirely.
     *
     * Removes the active scope, causing subsequent queries to return
     * unfiltered results. Use with caution in multi-tenant environments
     * as this allows cross-tenant data access.
     *
     * @return $this Returns $this for method chaining
     */
    public function remove(): static;

    /**
     * Execute a callback without scope constraints.
     *
     * Temporarily disables scoping for the duration of the callback, then
     * restores it. Enables queries across all scopes/tenants when needed
     * for system-level operations or reporting.
     *
     * @param  callable $callback The callback to execute without scoping
     * @return mixed    The callback's return value
     */
    public function removeOnce(callable $callback): mixed;
}
