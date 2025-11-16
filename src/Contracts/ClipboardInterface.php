<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Contracts;

use Cline\Warden\Database\Ability;
use Cline\Warden\Database\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Defines the core authorization checking contract.
 *
 * The clipboard manages authorization checks by evaluating whether authorities
 * (users or entities) have specific abilities on models or in general. Supports
 * role-based checks and ability enumeration for UI rendering and permission auditing.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface ClipboardInterface
{
    /**
     * Check if the authority has the specified ability.
     *
     * Performs authorization check considering the authority's granted and
     * forbidden abilities, including any constraints attached to those abilities.
     * When a model is provided, checks model-specific permissions. When a string
     * is provided, checks permissions for that model class.
     *
     * @param  Model             $authority The authority (user or entity) to check permissions for
     * @param  string            $ability   The ability name to check (e.g., 'edit', 'delete', 'publish')
     * @param  null|Model|string $model     The specific model instance or class name to check against
     * @return bool              True if the authority has the ability, false otherwise
     */
    public function check(Model $authority, string $ability, Model|string|null $model = null): bool;

    /**
     * Check ability and return the ability ID if authorized.
     *
     * Performs the same authorization check as check() but returns additional
     * information. Useful when you need to track which specific ability record
     * granted access, for example in audit logs.
     *
     * @param  Model                $authority The authority to check permissions for
     * @param  string               $ability   The ability name to check
     * @param  null|Model|string    $model     The specific model instance or class name to check against
     * @return null|bool|int|string The ability ID if authorized, false if not authorized, null if ability doesn't exist
     */
    public function checkGetId(Model $authority, string $ability, Model|string|null $model = null): null|bool|int|string;

    /**
     * Check if the authority has any or all of the specified roles.
     *
     * Evaluates role membership using either OR logic (has any of the roles)
     * or AND logic (has all of the roles). Roles are typically broader
     * categorizations than abilities (e.g., 'admin', 'editor', 'subscriber').
     *
     * @param  Model                                       $authority The authority to check roles for
     * @param  array<int, int|Role|string>|int|Role|string $roles     Single role name or array of role names/IDs/instances to check
     * @param  string                                      $boolean   Logical operator: 'or' (any role) or 'and' (all roles)
     * @return bool                                        True if the role check passes
     */
    public function checkRole(Model $authority, array|Role|int|string $roles, string $boolean = 'or'): bool;

    /**
     * Retrieve all roles assigned to the authority.
     *
     * Returns the complete set of roles the authority belongs to. Useful for
     * displaying user roles in admin panels or determining broad access levels.
     *
     * @param  Model                   $authority The authority whose roles to retrieve
     * @return Collection<int, string> Collection of role names
     */
    public function getRoles(Model $authority): Collection;

    /**
     * Retrieve all abilities granted to or forbidden for the authority.
     *
     * Returns the collection of ability records associated with the authority,
     * either allowed or forbidden based on the $allowed parameter. Includes
     * abilities inherited from roles and directly assigned abilities.
     *
     * @param  Model                                                  $authority The authority whose abilities to retrieve
     * @param  bool                                                   $allowed   True for allowed abilities, false for forbidden abilities
     * @return \Illuminate\Database\Eloquent\Collection<int, Ability> Collection of ability models
     */
    public function getAbilities(Model $authority, bool $allowed = true): \Illuminate\Database\Eloquent\Collection;

    /**
     * Retrieve all abilities explicitly forbidden for the authority.
     *
     * Convenience method for getting forbidden abilities. Equivalent to
     * calling getAbilities() with $allowed=false. Forbidden abilities take
     * precedence over granted abilities in authorization decisions.
     *
     * @param  Model                                                  $authority The authority whose forbidden abilities to retrieve
     * @return \Illuminate\Database\Eloquent\Collection<int, Ability> Collection of forbidden ability models
     */
    public function getForbiddenAbilities(Model $authority): \Illuminate\Database\Eloquent\Collection;
}
