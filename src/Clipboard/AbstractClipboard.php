<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Clipboard;

use BackedEnum;
use BadMethodCallException;
use Cline\Warden\Contracts\ClipboardInterface;
use Cline\Warden\Database\Ability;
use Cline\Warden\Database\Models;
use Cline\Warden\Database\Queries\Abilities;
use Cline\Warden\Database\Role;
use Cline\Warden\Exceptions\InvalidModelIdentifierException;
use Cline\Warden\Support\CharDetector;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;

use function array_filter;
use function assert;
use function count;
use function is_int;
use function is_numeric;
use function is_string;
use function method_exists;
use function throw_unless;

/**
 * Base clipboard implementation providing core authorization checking functionality.
 *
 * Handles ability and role verification for authority models (users, roles, etc.)
 * by querying the authorization system. Provides methods for checking permissions,
 * retrieving role information, and validating model ownership.
 *
 * @see     CachedClipboard For cached implementation
 * @see     Clipboard For real-time implementation
 *
 * @author  Brian Faust <brian@cline.sh>
 */
abstract class AbstractClipboard implements ClipboardInterface
{
    /**
     * Determine if the given authority has the given ability.
     *
     * Checks whether an authority (user or role) has permission to perform a specific
     * ability, optionally scoped to a model instance or model type. Returns a boolean
     * indicating permission status based on the underlying authorization rules.
     *
     * @param  Model             $authority The authority model (user or role) to check permissions for
     * @param  BackedEnum|string $ability   The ability name to verify (e.g., 'create', 'update', 'delete')
     * @param  null|Model|string $model     Optional model instance or class name to scope the ability check
     * @return bool              True if the authority has the ability, false otherwise
     */
    public function check(Model $authority, BackedEnum|string $ability, Model|string|null $model = null): bool
    {
        return (bool) $this->checkGetId($authority, $ability, $model);
    }

    /**
     * Check if an authority has the given roles.
     *
     * Verifies role membership using configurable logic operators. Supports 'or' for
     * checking if the authority has any of the roles, 'and' for requiring all roles,
     * and 'not' for ensuring the authority has none of the roles.
     *
     * @param  Model                                       $authority The authority model to check role membership for
     * @param  array<int, int|Role|string>|int|Role|string $roles     Role name(s), IDs, or instances to check against
     * @param  string                                      $boolean   Logical operator: 'or' (any), 'and' (all), or 'not' (none)
     * @return bool                                        True if the role check passes according to the specified operator
     */
    public function checkRole(Model $authority, array|Role|int|string $roles, string $boolean = 'or'): bool
    {
        $count = $this->countMatchingRoles($authority, $roles);

        if ($boolean === 'or') {
            return $count > 0;
        }

        if ($boolean === 'not') {
            return $count === 0;
        }

        return $count === count((array) $roles);
    }

    /**
     * Get the given authority's roles' IDs and names.
     *
     * Returns a bidirectional lookup array containing role IDs mapped to names
     * and names mapped to IDs, enabling efficient role resolution in either direction.
     *
     * @param  Model                                                               $authority The authority model to retrieve roles for
     * @return array{ids: Collection<int, string>, names: Collection<string, int>} Associative array with 'ids' and 'names' collections
     */
    public function getRolesLookup(Model $authority): array
    {
        throw_unless(method_exists($authority, 'roles'), BadMethodCallException::class, 'Model must have roles() method');

        $relation = $authority->roles();
        assert($relation instanceof MorphToMany);

        $roles = $relation->get([
            'name', Models::role()->getQualifiedKeyName(),
        ])->pluck('name', Models::role()->getKeyName());

        /** @var Collection<int, string> $roles */
        /** @var Collection<string, int> $flipped */
        $flipped = $roles->flip();

        return ['ids' => $roles, 'names' => $flipped];
    }

    /**
     * Get the given authority's roles' names.
     *
     * @param  Model                   $authority The authority model to retrieve role names for
     * @return Collection<int, string> Collection of role names
     */
    public function getRoles(Model $authority): Collection
    {
        return $this->getRolesLookup($authority)['names']->keys();
    }

    /**
     * Get a list of the authority's abilities.
     *
     * Retrieves either allowed or forbidden abilities for an authority based on
     * the $allowed parameter. Returns the full ability model instances for further
     * inspection or transformation.
     *
     * @param  Model                                                  $authority The authority model to retrieve abilities for
     * @param  bool                                                   $allowed   True for allowed abilities, false for forbidden abilities
     * @return \Illuminate\Database\Eloquent\Collection<int, Ability> Collection of ability models
     */
    public function getAbilities(Model $authority, bool $allowed = true): \Illuminate\Database\Eloquent\Collection
    {
        return Abilities::forAuthority($authority, $allowed)->get();
    }

    /**
     * Get a list of the authority's forbidden abilities.
     *
     * Convenience method that retrieves abilities explicitly forbidden to the authority.
     * Forbidden abilities take precedence over allowed abilities in permission checks.
     *
     * @param  Model                                                  $authority The authority model to retrieve forbidden abilities for
     * @return \Illuminate\Database\Eloquent\Collection<int, Ability> Collection of forbidden ability models
     */
    public function getForbiddenAbilities(Model $authority): \Illuminate\Database\Eloquent\Collection
    {
        return $this->getAbilities($authority, false);
    }

    /**
     * Determine whether the authority owns the given model.
     *
     * Checks ownership by comparing the authority against the model's configured
     * ownership relationships. Used to enforce "owned" scoped abilities where
     * permissions apply only to resources owned by the authority.
     *
     * @param  Model $authority The authority model to check ownership for
     * @param  mixed $model     The model instance to check ownership of
     * @return bool  True if the authority owns the model, false otherwise
     */
    public function isOwnedBy(Model $authority, mixed $model): bool
    {
        return $model instanceof Model && Models::isOwnedBy($authority, $model);
    }

    /**
     * Count the authority's roles matching the given roles.
     *
     * Supports matching by role name (string), ID (numeric), or model instance.
     * Used internally by checkRole() to determine how many of the requested
     * roles the authority possesses. Intelligently detects UUIDs and ULIDs
     * in string format to treat them as IDs rather than role names.
     *
     * @param Model                                       $authority The authority model to count matching roles for
     * @param array<int, int|Role|string>|int|Role|string $roles     Role identifiers to match against (names, IDs, or models)
     *
     * @throws InvalidModelIdentifierException If a role identifier type is not supported
     *
     * @return int Number of matching roles found
     */
    protected function countMatchingRoles(Model $authority, array|Role|int|string $roles): int
    {
        $lookups = $this->getRolesLookup($authority);
        $rolesArray = (array) $roles;

        return count(array_filter($rolesArray, function (mixed $role) use ($lookups): bool {
            if (is_string($role)) {
                // Check if string is a ULID or UUID - treat as ID
                if (CharDetector::isUuidOrUlid($role)) {
                    return $lookups['ids']->has($role);
                }

                // Otherwise it's a role name
                return $lookups['names']->has($role);
            }

            if (is_numeric($role)) {
                return $lookups['ids']->has((int) $role);
            }

            if ($role instanceof Model) {
                $key = $role->getKey();

                if (is_int($key) || is_string($key)) {
                    return $lookups['ids']->has($key);
                }
            }

            throw InvalidModelIdentifierException::cannotResolve();
        }));
    }
}
