<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures;

use Cline\Warden\Contracts\ScopeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class MultiTenancyNullScopeStub implements ScopeInterface
{
    public function to(): void
    {
        //
    }

    public function appendToCacheKey(string $key): string
    {
        return $key;
    }

    public function applyToModel(Model $model): Model
    {
        return $model;
    }

    public function applyToModelQuery(Builder|\Illuminate\Database\Query\Builder $query, ?string $table = null): Builder|\Illuminate\Database\Query\Builder
    {
        return $query;
    }

    public function applyToRelationQuery(Builder|\Illuminate\Database\Query\Builder $query, string $table): Builder|\Illuminate\Database\Query\Builder
    {
        return $query;
    }

    public function applyToRelation(BelongsToMany $relation): BelongsToMany
    {
        return $relation;
    }

    public function get(): null
    {
        return null;
    }

    public function getAttachAttributes(Model|string|null $authority = null): array
    {
        return [];
    }

    public function onceTo($scope, callable $callback): mixed
    {
        return null;
    }

    public function remove(): static
    {
        return $this;
    }

    public function removeOnce(callable $callback): mixed
    {
        return null;
    }
}
