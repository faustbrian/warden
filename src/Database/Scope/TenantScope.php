<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Database\Scope;

use Cline\Warden\Database\Models;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope as EloquentScope;

/**
 * Applies tenant-based query scoping to Eloquent models.
 *
 * This scope integrates with Warden's scoping system to automatically filter
 * queries based on the current tenant context. When registered on a model, it
 * ensures all queries are automatically constrained to the appropriate tenant,
 * preventing cross-tenant data access in multi-tenant applications.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class TenantScope implements EloquentScope
{
    /**
     * Register this scope as a global scope on the given model.
     *
     * Adds the TenantScope to the model's global scopes, ensuring all queries
     * on that model are automatically filtered by the current tenant context.
     *
     * @param string $model The fully-qualified class name of the model to scope
     */
    public static function register(string $model): void
    {
        $model::addGlobalScope(
            new self(),
        );
    }

    /**
     * Apply tenant-based constraints to the Eloquent query builder.
     *
     * Delegates to the configured scope instance to add appropriate WHERE clauses
     * that filter results based on the current tenant. The specific filtering logic
     * is determined by the active scope implementation.
     *
     * @param Builder<Model> $query The Eloquent query builder to modify
     * @param Model          $model The model instance being queried
     */
    public function apply(Builder $query, Model $model): void
    {
        Models::scope()->applyToModelQuery($query, $model->getTable());
    }
}
