<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Facades;

use Cline\Warden\Conductors\AssignsRoles;
use Cline\Warden\Conductors\ChecksRoles;
use Cline\Warden\Conductors\ForbidsAbilities;
use Cline\Warden\Conductors\GivesAbilities;
use Cline\Warden\Conductors\RemovesAbilities;
use Cline\Warden\Conductors\RemovesRoles;
use Cline\Warden\Conductors\SyncsRolesAndAbilities;
use Cline\Warden\Conductors\UnforbidsAbilities;
use Cline\Warden\Contracts\ClipboardInterface;
use Cline\Warden\Contracts\ScopeInterface;
use Cline\Warden\Database\Role;
use Cline\Warden\Warden as WardenService;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * Facade for accessing Warden authorization functionality.
 *
 * Provides a static interface to Warden's role and permission management system,
 * allowing developers to grant abilities, assign roles, and check permissions
 * throughout their Laravel application using convenient static methods.
 */

/**
 * @method static mixed                  ability(array<string, mixed> $attributes = [])                                                             Create a new ability model instance
 * @method static GivesAbilities         allow(Model|string $authority)                                                                             Grant abilities to a specific authority
 * @method static GivesAbilities         allowEveryone()                                                                                            Grant abilities to all users
 * @method static AssignsRoles           assign(iterable<Role|\BackedEnum|string>|\BackedEnum|Role|string $roles)                                   Assign roles to a model
 * @method static Response               authorize(string $ability, array|mixed $arguments = [])                                                    Authorize ability or throw exception
 * @method static self                   cache(?Store $cache = null)                                                                                Enable clipboard caching with custom store
 * @method static bool                   can(string $ability, array|mixed $arguments = [])                                                          Check if ability is allowed
 * @method static bool                   canAny(array<int, string> $abilities, array|mixed $arguments = [])                                         Check if any abilities are allowed
 * @method static bool                   denies(string $ability, array|mixed $arguments = []) Check if ability is denied (deprecated, use cannot())
 * @method static self                   define(string $ability, callable|string $callback)                                                         Define ability callback at gate
 * @method static RemovesAbilities       disallow(Model|string $authority)                                                                          Remove abilities from authority
 * @method static RemovesAbilities       disallowEveryone()                                                                                         Remove abilities from all users
 * @method static self                   dontCache()                                                                                                Disable clipboard caching
 * @method static ForbidsAbilities       forbid(Model|string $authority)                                                                            Explicitly forbid abilities for authority
 * @method static ForbidsAbilities       forbidEveryone()                                                                                           Explicitly forbid abilities for all users
 * @method static ClipboardInterface     getClipboard()                                                                                             Get the clipboard instance
 * @method static Gate|null              getGate()                                                                                                  Get gate instance or null
 * @method static string                 getGuardName()                                                                                             Get current guard name
 * @method static WardenService          guard(\BackedEnum|string $guardName)                                                                       Create guard-scoped Warden instance
 * @method static Gate                   gate() Get gate instance (throws if not set)
 * @method static ChecksRoles            is(Model $authority)                                                                                       Check roles for authority
 * @method static self                   ownedVia(\Closure|string $model, \Closure|string|null $attribute = null)                                   Configure ownership checks
 * @method static self                   refresh(?Model $authority = null)                                                                          Clear cached permissions
 * @method static self                   refreshFor(Model $authority)                                                                               Clear cached permissions for authority
 * @method static self                   registerClipboardAtContainer()                                                                             Register clipboard in container
 * @method static RemovesRoles           retract(array<int, Role|\BackedEnum|string>|\BackedEnum|Collection<int, Role>|Role|string $roles)          Remove roles from model
 * @method static mixed                  role(array<string, mixed> $attributes = [])                                                                Create a new role model instance
 * @method static self                   runBeforePolicies(bool $boolean = true)                                                                    Set clipboard to run before policies
 * @method static mixed                  scope(?ScopeInterface $scope = null)                                                                       Get or set the scope instance
 * @method static self                   setClipboard(ClipboardInterface $clipboard)                                                                Set the clipboard instance
 * @method static self                   setGate(Gate $gate)                                                                                        Set the gate instance
 * @method static SyncsRolesAndAbilities sync(Model|string $authority)                                                                              Sync roles and abilities for authority
 * @method static self                   tables(array<string, string> $map)                                                                         Set custom table names
 * @method static UnforbidsAbilities     unforbid(Model|string $authority)                                                                          Remove forbidden abilities from authority
 * @method static UnforbidsAbilities     unforbidEveryone()                                                                                         Remove forbidden abilities from all users
 * @method static self                   useAbilityModel(string $model)                                                                             Set custom ability model
 * @method static self                   useRoleModel(string $model)                                                                                Set custom role model
 * @method static bool                   usesCachedClipboard()                                                                                      Check if using cached clipboard
 * @method static self                   useUserModel(string $model)                                                                                Set custom user model
 *
 * @see WardenService
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Warden extends Facade
{
    /**
     * Get the accessor key for the underlying Warden instance.
     *
     * Returns the class name used to resolve the Warden instance from
     * the Laravel service container.
     *
     * @return string The service container binding key
     */
    protected static function getFacadeAccessor(): string
    {
        return WardenService::class;
    }
}
