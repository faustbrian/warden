<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Clipboard;

use Cline\Warden\Database\Ability;
use Cline\Warden\Database\Queries\Abilities;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

use function assert;
use function is_int;

/**
 * Real-time clipboard implementation that queries the database directly.
 *
 * Performs fresh database queries for every authorization check without caching.
 * Use this implementation for applications where authorization changes must be
 * reflected immediately, or when caching introduces unacceptable complexity.
 * For high-traffic applications, consider using CachedClipboard instead.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Clipboard extends AbstractClipboard
{
    /**
     * Determine if the given authority has the given ability, and return the ability ID.
     *
     * Queries the database directly to check abilities. Forbidden abilities are checked
     * first and take precedence - if any forbidden match exists, returns false immediately.
     * Otherwise returns the allowing ability ID if found, or null if not authorized.
     *
     * @param  Model             $authority The authority model to check permissions for
     * @param  string            $ability   The ability name to verify
     * @param  null|Model|string $model     Optional model to scope the ability check
     * @return null|bool|int     False if forbidden, ability ID if allowed, null if no permission found
     */
    public function checkGetId(Model $authority, string $ability, Model|string|null $model = null): bool|int|null
    {
        if ($this->isForbidden($authority, $ability, $model)) {
            return false;
        }

        $abilityModel = $this->getAllowingAbility($authority, $ability, $model);

        if (!$abilityModel instanceof Ability) {
            return null;
        }

        $key = $abilityModel->getKey();

        // The Ability model casts 'id' to int, so getKey() will return int
        assert(is_int($key));

        return $key;
    }

    /**
     * Determine whether the given ability request is explicitly forbidden.
     *
     * Checks if a forbidden ability exists matching the request. Forbidden abilities
     * always take precedence over allowed abilities in authorization checks.
     *
     * @param  Model             $authority The authority model to check
     * @param  string            $ability   The ability name being requested
     * @param  null|Model|string $model     Optional model to scope the forbidden check
     * @return bool              True if the ability is explicitly forbidden, false otherwise
     */
    private function isForbidden(Model $authority, string $ability, Model|string|null $model = null): bool
    {
        return $this->getHasAbilityQuery(
            $authority,
            $ability,
            $model,
            $allowed = false,
        )->exists();
    }

    /**
     * Get the ability model that allows the given ability request.
     *
     * Queries for an allowing ability matching the request parameters. Returns
     * the ability model for further inspection, or null if no permission exists.
     *
     * @param  Model             $authority The authority model to check
     * @param  string            $ability   The ability name being requested
     * @param  null|Model|string $model     Optional model to scope the ability check
     * @return null|Ability      The allowing ability model, or null if not found
     */
    private function getAllowingAbility(Model $authority, string $ability, Model|string|null $model = null): ?Ability
    {
        /** @var null|Ability */
        return $this->getHasAbilityQuery(
            $authority,
            $ability,
            $model,
            $allowed = true,
        )->first();
    }

    /**
     * Get the query for where the given authority has the given ability.
     *
     * Constructs a query to find abilities matching the request. Automatically
     * filters by ownership scope when applicable, and handles both simple abilities
     * (without models) and model-scoped abilities differently.
     *
     * @param  Model             $authority The authority model to query abilities for
     * @param  string            $ability   The ability name to search for
     * @param  null|Model|string $model     Optional model to scope the query
     * @param  bool              $allowed   True for allowed abilities, false for forbidden
     * @return Builder<Ability>  Query builder for matching abilities
     */
    private function getHasAbilityQuery(Model $authority, string $ability, Model|string|null $model, bool $allowed): Builder
    {
        /** @var Builder<Ability> $query */
        $query = Abilities::forAuthority($authority, $allowed);

        if (!$this->isOwnedBy($authority, $model)) {
            $query->where('only_owned', false);
        }

        if (null === $model) {
            return $this->constrainToSimpleAbility($query, $ability);
        }

        // Apply byName scope - available via IsAbility trait
        $query->byName($ability);

        // Apply forModel scope - available via IsAbility trait
        $query->forModel($model);

        return $query;
    }

    /**
     * Constrain the query to the given non-model ability.
     *
     * Builds query constraints for simple abilities that aren't tied to specific
     * models. Matches exact ability name with no entity type, or wildcard abilities
     * that apply globally across all model types.
     *
     * @param  Builder<Ability> $query   The base ability query builder to constrain
     * @param  string           $ability The ability name to match
     * @return Builder<Ability> The constrained query builder
     */
    private function constrainToSimpleAbility(Builder $query, string $ability): Builder
    {
        return $query->where(function (Builder $query) use ($ability): void {
            $query->where('name', $ability)->whereNull('subject_type');

            $query->orWhere(function (Builder $query): void {
                $query->where('name', '*')->where(function (Builder $query): void {
                    $query->whereNull('subject_type')->orWhere('subject_type', '*');
                });
            });
        });
    }
}
