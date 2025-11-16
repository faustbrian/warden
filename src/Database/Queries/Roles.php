<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Database\Queries;

use Cline\Warden\Database\Models;
use Cline\Warden\Support\Helpers;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

use function assert;
use function count;
use function sprintf;

/**
 * Query builder for role-based model filtering.
 *
 * This class provides query constraint methods for filtering models (typically Users)
 * based on their role assignments. Supports OR logic (any role), AND logic (all roles),
 * and NOT logic (none of the roles), as well as filtering roles by assigned entities.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Roles
{
    /**
     * Constrain query to models having any of the specified roles.
     *
     * Applies a whereHas constraint that checks if the model has at least one
     * of the given role names assigned. Uses OR logic across the provided roles.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel> $query    The query builder instance
     * @param  string          ...$roles One or more role names to filter by
     * @return Builder<TModel> The constrained query
     */
    public function constrainWhereIs(Builder $query, string ...$roles): Builder
    {
        return $query->whereHas('roles', function (Builder $query) use ($roles): void {
            $query->whereIn('name', $roles);
        });
    }

    /**
     * Constrain query to models having all of the specified roles.
     *
     * Applies a whereHas constraint with an exact count match, ensuring the model
     * has every one of the given role names assigned. Uses AND logic requiring all roles.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel> $query    The query builder instance
     * @param  string          ...$roles One or more role names that must all be present
     * @return Builder<TModel> The constrained query
     */
    public function constrainWhereIsAll(Builder $query, string ...$roles): Builder
    {
        return $query->whereHas('roles', function (Builder $query) use ($roles): void {
            $query->whereIn('name', $roles);
        }, '=', count($roles));
    }

    /**
     * Constrain query to models lacking all of the specified roles.
     *
     * Applies a whereDoesntHave constraint that checks if the model has none
     * of the given role names assigned. Uses NOT logic to exclude roles.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel> $query    The query builder instance
     * @param  string          ...$roles One or more role names to exclude
     * @return Builder<TModel> The constrained query
     */
    public function constrainWhereIsNot(Builder $query, string ...$roles): Builder
    {
        return $query->whereDoesntHave('roles', function (Builder $query) use ($roles): void {
            $query->whereIn('name', $roles);
        });
    }

    /**
     * Constrain roles query to only roles assigned to specific entities.
     *
     * Applies a whereExists subquery that checks if the role is assigned to at
     * least one of the specified models. Useful for finding roles shared by a
     * set of users or other entities. Respects tenant scoping.
     *
     * @template TModel of Model
     * @template TModelArg of Model
     *
     * @param Builder<TModel>                                              $query The roles query builder instance
     * @param class-string<TModelArg>|Collection<int, TModelArg>|TModelArg $model Model instance(s) or class name
     * @param null|array<int>                                              $keys  Optional array of model IDs when passing a class name
     */
    public function constrainWhereAssignedTo(Builder $query, Collection|Model|string $model, ?array $keys = null): void
    {
        $extracted = Helpers::extractModelAndKeys($model, $keys);
        assert($extracted !== null);
        [$modelInstance, $keys] = $extracted;

        $query->whereExists(function (\Illuminate\Database\Query\Builder $query) use ($modelInstance, $keys): void {
            $table = $modelInstance->getTable();
            $keyColumn = Models::getModelKey($modelInstance);
            $key = sprintf('%s.%s', $table, $keyColumn);
            $pivot = Models::table('assigned_roles');
            $roles = Models::table('roles');

            $query->from($table)
                ->join($pivot, $key, '=', $pivot.'.actor_id')
                ->whereColumn($pivot.'.role_id', $roles.'.id')
                ->where($pivot.'.actor_type', $modelInstance->getMorphClass())
                ->whereIn($key, $keys);

            Models::scope()->applyToModelQuery($query, $roles);
            Models::scope()->applyToRelationQuery($query, $pivot);
        });
    }
}
