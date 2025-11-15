<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Database\Queries;

use Cline\Warden\Database\Ability;
use Cline\Warden\Database\Models;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

use function sprintf;

/**
 * Query builder for retrieving abilities assigned to an authority.
 *
 * This class constructs complex database queries to find all abilities granted
 * to an authority (user), including abilities assigned directly, through roles,
 * or to everyone globally. Supports both allowed and forbidden ability queries
 * with proper tenant scoping.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Abilities
{
    /**
     * Build a query for all abilities available to an authority.
     *
     * Constructs a query that unions three sources of abilities: those granted
     * through roles, those granted directly, and those granted to everyone.
     * Respects tenant scoping and can filter for allowed or forbidden abilities.
     *
     * @param  Model            $authority The authority (typically User) to query abilities for
     * @param  bool             $allowed   True for granted abilities, false for forbidden abilities
     * @return Builder<Ability> Query builder for the authority's abilities
     */
    public static function forAuthority(Model $authority, bool $allowed = true): Builder
    {
        $ability = Models::ability();

        /** @var Builder<Ability> $query */
        $query = $ability->newQuery();

        return $query->where(function (Builder $query) use ($authority, $allowed): void {
            $query->whereExists(self::getRoleConstraint($authority, $allowed));
            $query->orWhereExists(self::getAuthorityConstraint($authority, $allowed));
            $query->orWhereExists(self::getEveryoneConstraint($allowed));
        });
    }

    /**
     * Build a query for forbidden abilities for an authority.
     *
     * Convenience method that calls forAuthority() with $allowed set to false,
     * returning only abilities explicitly marked as forbidden for the authority.
     *
     * @param  Model            $authority The authority to query forbidden abilities for
     * @return Builder<Ability> Query builder for the authority's forbidden abilities
     */
    public static function forbiddenForAuthority(Model $authority): Builder
    {
        return self::forAuthority($authority, false);
    }

    /**
     * Build a subquery constraint for abilities granted through roles.
     *
     * Creates a closure that defines a whereExists subquery checking if an ability
     * is granted to the authority through any of their assigned roles. Applies
     * tenant scoping to both the role and permission relationships.
     *
     * @param  Model   $authority The authority to check role-based abilities for
     * @param  bool    $allowed   True to find granted abilities, false for forbidden
     * @return Closure Subquery constraint closure
     */
    private static function getRoleConstraint(Model $authority, bool $allowed): Closure
    {
        return function (\Illuminate\Database\Query\Builder $query) use ($authority, $allowed): void {
            $permissions = Models::table('permissions');
            $abilities = Models::table('abilities');
            $roles = Models::table('roles');

            $query->from($roles)
                ->join($permissions, $roles.'.id', '=', $permissions.'.actor_id')
                ->whereColumn($permissions.'.ability_id', $abilities.'.id')
                ->where($permissions.'.forbidden', !$allowed)
                ->where($permissions.'.actor_type', Models::role()->getMorphClass());

            Models::scope()->applyToModelQuery($query, $roles);
            Models::scope()->applyToRelationQuery($query, $permissions);

            $query->where(function (\Illuminate\Database\Query\Builder $query) use ($authority): void {
                $query->whereExists(self::getAuthorityRoleConstraint($authority));
            });
        };
    }

    /**
     * Build a subquery constraint for roles assigned to an authority.
     *
     * Creates a nested subquery that checks if a role from the outer query is
     * actually assigned to the given authority. Used within getRoleConstraint()
     * to ensure only abilities from the authority's roles are included.
     *
     * @param  Model   $authority The authority to check role assignments for
     * @return Closure Subquery constraint closure
     */
    private static function getAuthorityRoleConstraint(Model $authority): Closure
    {
        return function (\Illuminate\Database\Query\Builder $query) use ($authority): void {
            $pivot = Models::table('assigned_roles');
            $roles = Models::table('roles');
            $table = $authority->getTable();

            $query->from($table)
                ->join($pivot, sprintf('%s.%s', $table, $authority->getKeyName()), '=', $pivot.'.actor_id')
                ->whereColumn($pivot.'.role_id', $roles.'.id')
                ->where($pivot.'.actor_type', $authority->getMorphClass())
                ->where(sprintf('%s.%s', $table, $authority->getKeyName()), $authority->getKey());

            Models::scope()->applyToModelQuery($query, $roles);
            Models::scope()->applyToRelationQuery($query, $pivot);
        };
    }

    /**
     * Build a subquery constraint for abilities granted directly to an authority.
     *
     * Creates a closure that defines a whereExists subquery checking if an ability
     * is directly assigned to the authority (bypassing roles). Applies tenant
     * scoping to the permission relationship.
     *
     * @param  Model   $authority The authority to check direct ability assignments for
     * @param  bool    $allowed   True to find granted abilities, false for forbidden
     * @return Closure Subquery constraint closure
     */
    private static function getAuthorityConstraint(Model $authority, bool $allowed): Closure
    {
        return function (\Illuminate\Database\Query\Builder $query) use ($authority, $allowed): void {
            $permissions = Models::table('permissions');
            $abilities = Models::table('abilities');
            $table = $authority->getTable();

            $query->from($table)
                ->join($permissions, sprintf('%s.%s', $table, $authority->getKeyName()), '=', $permissions.'.actor_id')
                ->whereColumn($permissions.'.ability_id', $abilities.'.id')
                ->where($permissions.'.forbidden', !$allowed)
                ->where($permissions.'.actor_type', $authority->getMorphClass())
                ->where(sprintf('%s.%s', $table, $authority->getKeyName()), $authority->getKey());

            Models::scope()->applyToModelQuery($query, $abilities);
            Models::scope()->applyToRelationQuery($query, $permissions);
        };
    }

    /**
     * Build a subquery constraint for abilities granted to everyone.
     *
     * Creates a closure that defines a whereExists subquery checking for global
     * ability assignments (permissions with null actor_id) that apply to all users.
     * Applies tenant scoping to the permission relationship.
     *
     * @param  bool    $allowed True to find granted abilities, false for forbidden
     * @return Closure Subquery constraint closure
     */
    private static function getEveryoneConstraint(bool $allowed): Closure
    {
        return function (\Illuminate\Database\Query\Builder $query) use ($allowed): void {
            $permissions = Models::table('permissions');
            $abilities = Models::table('abilities');

            $query->from($permissions)
                ->whereColumn($permissions.'.ability_id', $abilities.'.id')
                ->where($permissions.'.forbidden', !$allowed)
                ->whereNull('actor_id');

            Models::scope()->applyToRelationQuery($query, $permissions);
        };
    }
}
