<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden;

use BackedEnum;
use Cline\Warden\Clipboard\CachedClipboard;
use Cline\Warden\Clipboard\Clipboard;
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
use Cline\Warden\Database\Models;
use Cline\Warden\Database\Role;
use Closure;
use Deprecated;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Access\Response;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use RuntimeException;

use function assert;
use function class_exists;
use function config;
use function is_string;
use function sprintf;
use function throw_if;
use function throw_unless;

/**
 * Core authorization and permission management system.
 *
 * Warden provides a comprehensive role and permission system for Laravel applications,
 * offering fluent APIs for granting abilities, assigning roles, checking permissions,
 * and managing authorization rules. It integrates deeply with Laravel's Gate to provide
 * both simple and complex authorization scenarios including ownership-based permissions,
 * model-specific abilities, and flexible caching strategies.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Warden
{
    /**
     * Laravel's authorization gate instance.
     *
     * @var null|Gate The gate instance used for authorization checks, or null if not yet configured
     */
    private ?Gate $gate = null;

    /**
     * Create a new Warden instance.
     *
     * @param Guard       $guard     The guard instance that integrates Warden with Laravel's Gate,
     *                               providing the bridge between clipboard permission checks and
     *                               gate authorization flow. Handles registration of before/after
     *                               callbacks with the gate and delegates authorization checks to
     *                               the clipboard for actual permission verification.
     * @param null|string $guardName Optional authentication guard name for permission scoping
     */
    public function __construct(
        private readonly Guard $guard,
        private readonly ?string $guardName = null,
    ) {}

    /**
     * Create a new Warden instance with default configuration.
     *
     * Convenience method that creates a factory, configures it, and returns
     * a fully initialized Warden instance ready for use.
     *
     * @param  null|Model $user Optional user model for authorization context
     * @return static     Fully configured Warden instance
     */
    public static function create(?Model $user = null): static
    {
        return self::make($user)->create();
    }

    /**
     * Create a factory for building custom Warden instances.
     *
     * Returns a factory instance that provides a fluent interface for configuring
     * Warden's components (clipboard, gate, cache) before creating the instance.
     *
     * @param  null|Model $user Optional user model for authorization context
     * @return Factory    Factory instance for configuring Warden
     */
    public static function make(?Model $user = null): Factory
    {
        return new Factory($user);
    }

    /**
     * Create a guard-scoped Warden instance.
     *
     * Returns a new Warden instance configured to use the specified authentication
     * guard for all permission operations. This ensures roles and abilities are
     * isolated per guard (web, api, rpc, etc.).
     *
     * @param  BackedEnum|string $guardName The authentication guard name (e.g., 'web', 'api', 'rpc')
     * @return static            Guard-scoped Warden instance
     */
    public static function guard(BackedEnum|string $guardName): static
    {
        $guardNameValue = $guardName instanceof BackedEnum ? $guardName->value : $guardName;

        return Container::getInstance()->make(self::class, ['guardName' => $guardNameValue]);
    }

    /**
     * Get the current guard name or default guard.
     *
     * Returns the guard name configured for this Warden instance, falling back
     * to the 'warden.guard' configuration value (default: 'web') if none was
     * specified. Guard names scope permissions to specific authentication contexts,
     * allowing separate authorization rules for web, API, and other guards.
     *
     * @return string The authentication guard name (e.g., 'web', 'api', 'rpc')
     */
    public function getGuardName(): string
    {
        $guard = $this->guardName ?? config('warden.guard', 'web');
        assert(is_string($guard));

        return $guard;
    }

    /**
     * Begin a fluent chain to grant abilities to an authority.
     *
     * Creates a fluent interface for granting abilities (permissions) to a user,
     * role, or model class. Chain with ->to() to specify which abilities to grant.
     *
     * ```php
     * Warden::allow($user)->to('edit-posts');
     * Warden::allow('admin')->to(['create-users', 'delete-users']);
     * ```
     *
     * @param  Model|string   $authority The user model, role name, or model class to grant abilities to
     * @return GivesAbilities Conductor for completing the ability grant via ->to()
     */
    public function allow(Model|string $authority): GivesAbilities
    {
        return new GivesAbilities($authority, $this->getGuardName());
    }

    /**
     * Begin a fluent chain to grant abilities to all users.
     *
     * Creates a global permission grant that applies to all authenticated users
     * regardless of role. Use sparingly for universal permissions like viewing
     * public content.
     *
     * ```php
     * Warden::allowEveryone()->to('view-dashboard');
     * ```
     *
     * @return GivesAbilities Conductor for completing the ability grant via ->to()
     */
    public function allowEveryone(): GivesAbilities
    {
        return new GivesAbilities(null, $this->getGuardName());
    }

    /**
     * Begin a fluent chain to remove abilities from an authority.
     *
     * @param  Model|string     $authority The user model or model class to remove abilities from
     * @return RemovesAbilities Conductor for completing the ability removal
     */
    public function disallow(Model|string $authority): RemovesAbilities
    {
        return new RemovesAbilities($authority, $this->getGuardName());
    }

    /**
     * Begin a fluent chain to remove abilities from all users.
     *
     * @return RemovesAbilities Conductor for completing the ability removal
     */
    public function disallowEveryone(): RemovesAbilities
    {
        return new RemovesAbilities(null, $this->getGuardName());
    }

    /**
     * Begin a fluent chain to explicitly forbid abilities for an authority.
     *
     * Creates an explicit denial that takes precedence over any grants from roles
     * or direct permissions. Unlike disallow() which removes permissions, forbid()
     * actively blocks them even if granted elsewhere. Useful for temporary restrictions
     * or overriding role-based permissions for specific users.
     *
     * ```php
     * Warden::forbid($user)->to('delete-posts');
     * Warden::forbid('editor')->to('access-admin-panel');
     * ```
     *
     * @param  Model|string     $authority The user model, role name, or model class to forbid abilities for
     * @return ForbidsAbilities Conductor for completing the ability forbidding via ->to()
     */
    public function forbid(Model|string $authority): ForbidsAbilities
    {
        return new ForbidsAbilities($authority, $this->getGuardName());
    }

    /**
     * Begin a fluent chain to explicitly forbid abilities for all users.
     *
     * @return ForbidsAbilities Conductor for completing the ability forbidding
     */
    public function forbidEveryone(): ForbidsAbilities
    {
        return new ForbidsAbilities(null, $this->getGuardName());
    }

    /**
     * Begin a fluent chain to remove forbidden abilities from an authority.
     *
     * @param  Model|string       $authority The user model or model class to unforbid abilities for
     * @return UnforbidsAbilities Conductor for completing the unforbidding
     */
    public function unforbid(Model|string $authority): UnforbidsAbilities
    {
        return new UnforbidsAbilities($authority, $this->getGuardName());
    }

    /**
     * Begin a fluent chain to remove forbidden abilities from all users.
     *
     * @return UnforbidsAbilities Conductor for completing the unforbidding
     */
    public function unforbidEveryone(): UnforbidsAbilities
    {
        return new UnforbidsAbilities(null, $this->getGuardName());
    }

    /**
     * Begin a fluent chain to assign roles to a model.
     *
     * Creates a fluent interface for assigning one or more roles to a user or model.
     * Roles can be specified as strings, BackedEnums, Role models, or arrays of any
     * combination. Chain with ->to() to specify the target authority.
     *
     * ```php
     * Warden::assign('admin')->to($user);
     * Warden::assign(['editor', 'moderator'])->to($user);
     * Warden::assign(UserRole::ADMIN)->to($user);
     * ```
     *
     * @param  BackedEnum|iterable<BackedEnum|Role|string>|Role|string $roles The role(s) to assign (strings, BackedEnums, or Role models)
     * @return AssignsRoles                                            Conductor for completing the role assignment via ->to()
     */
    public function assign(iterable|BackedEnum|Role|string $roles): AssignsRoles
    {
        return new AssignsRoles($roles, $this->getGuardName());
    }

    /**
     * Begin a fluent chain to remove roles from a model.
     *
     * @param  array<BackedEnum|Role|string>|BackedEnum|Collection<int, BackedEnum|Role|string>|Role|string $roles The role(s) to remove (strings, BackedEnums, or Role models)
     * @return RemovesRoles                                                                                 Conductor for completing the role removal
     */
    public function retract(array|BackedEnum|Collection|Role|string $roles): RemovesRoles
    {
        return new RemovesRoles($roles, $this->getGuardName());
    }

    /**
     * Begin a fluent chain to sync roles and abilities for an authority.
     *
     * Syncing will remove any existing roles/abilities not in the provided list
     * and add any new ones, ensuring the authority has exactly the specified set.
     *
     * @param  Model|string           $authority The user model or model class to sync for
     * @return SyncsRolesAndAbilities Conductor for completing the sync operation
     */
    public function sync(Model|string $authority): SyncsRolesAndAbilities
    {
        return new SyncsRolesAndAbilities($authority, $this->getGuardName());
    }

    /**
     * Begin a fluent chain to check if an authority has specific roles.
     *
     * @param  Model       $authority The user model to check roles for
     * @return ChecksRoles Conductor for completing the role check
     */
    public function is(Model $authority): ChecksRoles
    {
        return new ChecksRoles($authority, $this->getClipboard());
    }

    /**
     * Retrieve the clipboard instance.
     *
     * Returns the clipboard that manages permission storage, retrieval, and caching.
     * The clipboard abstracts the underlying permission storage mechanism and provides
     * an interface for querying abilities and roles.
     *
     * @return ClipboardInterface The clipboard managing permission storage and retrieval
     */
    public function getClipboard(): ClipboardInterface
    {
        return $this->guard->getClipboard();
    }

    /**
     * Replace the clipboard instance.
     *
     * Updates both the clipboard used by Warden and registers it in the
     * service container for dependency injection.
     *
     * @param  ClipboardInterface $clipboard The new clipboard instance to use
     * @return $this
     */
    public function setClipboard(ClipboardInterface $clipboard): self
    {
        $this->guard->setClipboard($clipboard);

        return $this->registerClipboardAtContainer();
    }

    /**
     * Register the clipboard in the service container.
     *
     * Binds the current clipboard instance to the ClipboardInterface contract,
     * making it available for dependency injection throughout the application.
     *
     * @return $this
     */
    public function registerClipboardAtContainer(): self
    {
        $clipboard = $this->guard->getClipboard();

        Container::getInstance()->instance(ClipboardInterface::class, $clipboard);

        return $this;
    }

    /**
     * Enable cached clipboard with custom or default cache store.
     *
     * Activates permission caching to reduce database queries for authorization checks.
     * If already using a cached clipboard, updates its cache store. Otherwise, replaces
     * the current clipboard with a new CachedClipboard instance. Caching significantly
     * improves performance for applications with frequent permission checks.
     *
     * ```php
     * Warden::create()->cache(); // Use default cache
     * Warden::create()->cache($redisStore); // Use specific cache store
     * ```
     *
     * @param  null|Store $cache Custom cache store, or null to use default from container
     * @return $this
     */
    public function cache(?Store $cache = null): self
    {
        $repository = $this->resolve(CacheRepository::class);
        assert($repository instanceof CacheRepository);
        $cache = $cache ?: $repository->getStore();

        if ($this->usesCachedClipboard()) {
            $clipboard = $this->guard->getClipboard();
            assert($clipboard instanceof CachedClipboard);
            $clipboard->setCache($cache);

            return $this;
        }

        return $this->setClipboard(
            new CachedClipboard($cache),
        );
    }

    /**
     * Disable clipboard caching.
     *
     * Replaces the current clipboard with a non-caching Clipboard instance,
     * forcing all permission checks to query the database directly. Useful
     * during testing or when permission changes must be immediately visible
     * without cache invalidation delays.
     *
     * @return $this
     */
    public function dontCache(): self
    {
        return $this->setClipboard(
            new Clipboard(),
        );
    }

    /**
     * Clear cached permissions for a specific authority or all authorities.
     *
     * Invalidates cached permissions, forcing the next authorization check to
     * reload from the database. Call after modifying permissions to ensure
     * changes are immediately reflected. Pass null to clear all cached permissions,
     * or a specific model to clear only that authority's cache.
     *
     * @param  null|Model $authority The authority to refresh, or null to clear all caches
     * @return $this
     */
    public function refresh(?Model $authority = null): self
    {
        if ($this->usesCachedClipboard()) {
            $clipboard = $this->getClipboard();
            assert($clipboard instanceof CachedClipboard);
            $clipboard->refresh($authority);
        }

        return $this;
    }

    /**
     * Clear cached permissions for a specific authority.
     *
     * @param  Model $authority The authority to clear permissions for
     * @return $this
     */
    public function refreshFor(Model $authority): self
    {
        if ($this->usesCachedClipboard()) {
            $clipboard = $this->getClipboard();
            assert($clipboard instanceof CachedClipboard);
            $clipboard->refreshFor($authority);
        }

        return $this;
    }

    /**
     * Set the Laravel gate instance.
     *
     * @param  Gate  $gate The gate instance to use for authorization
     * @return $this
     */
    public function setGate(Gate $gate): self
    {
        $this->gate = $gate;

        return $this;
    }

    /**
     * Retrieve the gate instance if set.
     *
     * @return null|Gate The gate instance or null if not configured
     */
    public function getGate(): ?Gate
    {
        return $this->gate;
    }

    /**
     * Retrieve the gate instance, throwing if not set.
     *
     * @throws RuntimeException If gate instance has not been set
     *
     * @return Gate The configured gate instance
     */
    public function gate(): Gate
    {
        throw_if(!$this->gate instanceof Gate, RuntimeException::class, 'The gate instance has not been set.');

        return $this->gate;
    }

    /**
     * Check if using a cached clipboard implementation.
     *
     * @return bool True if using CachedClipboard
     */
    public function usesCachedClipboard(): bool
    {
        return $this->guard->usesCachedClipboard();
    }

    /**
     * Define a custom ability callback at the gate.
     *
     * Registers a custom authorization callback for a specific ability name.
     * This allows you to define complex authorization logic that goes beyond
     * simple role and permission checks. The callback receives the user and
     * optional arguments for context-aware authorization decisions.
     *
     * ```php
     * Warden::define('edit-post', function ($user, $post) {
     *     return $user->id === $post->author_id;
     * });
     * ```
     *
     * @param string          $ability  The ability name to define
     * @param callable|string $callback The callback or policy method to execute
     *
     * @throws InvalidArgumentException If callback is invalid
     *
     * @return $this
     */
    public function define(string $ability, callable|string $callback): self
    {
        $this->gate()->define($ability, $callback);

        return $this;
    }

    /**
     * Authorize an ability or throw an authorization exception.
     *
     * @param string      $ability   The ability to authorize
     * @param array|mixed $arguments Additional arguments for the authorization check
     *
     * @throws AuthorizationException If authorization fails
     *
     * @return Response Authorization response
     */
    public function authorize(string $ability, mixed $arguments = []): Response
    {
        return $this->gate()->authorize($ability, $arguments);
    }

    /**
     * Check if an ability is allowed for the current user.
     *
     * @param  string      $ability   The ability to check
     * @param  array|mixed $arguments Additional arguments for the authorization check
     * @return bool        True if allowed, false otherwise
     */
    public function can(string $ability, mixed $arguments = []): bool
    {
        return $this->gate()->allows($ability, $arguments);
    }

    /**
     * Check if any of the given abilities are allowed for the current user.
     *
     * @param  array<int, string> $abilities Array of ability names to check
     * @param  array|mixed        $arguments Additional arguments for the authorization checks
     * @return bool               True if any ability is allowed, false otherwise
     */
    public function canAny(array $abilities, mixed $arguments = []): bool
    {
        return $this->gate()->any($abilities, $arguments);
    }

    /**
     * Check if an ability is denied for the current user.
     *
     * @param  string      $ability   The ability to check
     * @param  array|mixed $arguments Additional arguments for the authorization check
     * @return bool        True if denied, false otherwise
     */
    public function cannot(string $ability, mixed $arguments = []): bool
    {
        return $this->gate()->denies($ability, $arguments);
    }

    /**
     * Check if an ability is allowed (deprecated).
     *
     * @param  string      $ability   The ability to check
     * @param  array|mixed $arguments Additional arguments for the authorization check
     * @return bool        True if allowed, false otherwise
     */
    #[Deprecated('Use can() instead')]
    public function allows(string $ability, mixed $arguments = []): bool
    {
        return $this->can($ability, $arguments);
    }

    /**
     * Check if an ability is denied (deprecated).
     *
     * @param  string      $ability   The ability to check
     * @param  array|mixed $arguments Additional arguments for the authorization check
     * @return bool        True if denied, false otherwise
     */
    #[Deprecated('Use cannot() instead')]
    public function denies(string $ability, mixed $arguments = []): bool
    {
        return $this->cannot($ability, $arguments);
    }

    /**
     * Create a new role model instance.
     *
     * @param  array<string, mixed> $attributes Attributes for the role model
     * @return Role                 New role instance
     */
    public function role(array $attributes = []): mixed
    {
        return Models::role($attributes);
    }

    /**
     * Create a new ability model instance.
     *
     * @param  array<string, mixed> $attributes Attributes for the ability model
     * @return Database\Ability     New ability instance
     */
    public function ability(array $attributes = []): mixed
    {
        return Models::ability($attributes);
    }

    /**
     * Configure Warden to run before policy checks.
     *
     * Controls whether Warden's permission checks execute before or after Laravel's
     * policy authorization. When enabled, Warden rules can override policy logic,
     * useful for implementing role-based overrides of model policies. When disabled
     * (default), policies take precedence, allowing model-specific authorization
     * to override general permission rules.
     *
     * @param  bool  $boolean Whether to run before policies (true) or after (false, default)
     * @return $this
     */
    public function runBeforePolicies(bool $boolean = true): self
    {
        $this->guard->slot($boolean ? 'before' : 'after');

        return $this;
    }

    /**
     * Configure how ownership is determined for models.
     *
     * Defines the ownership relationship for model-specific permissions, enabling
     * "owned" scoping in authorization checks. Specify the attribute name (e.g., 'user_id')
     * or provide a custom closure for complex ownership logic. This allows permissions
     * like "edit-own-posts" where users can only edit their own content.
     *
     * ```php
     * Warden::ownedVia(Post::class, 'author_id');
     * Warden::ownedVia(Post::class, fn($user, $post) => $post->team_id === $user->team_id);
     * ```
     *
     * @param  Closure|string      $model     The model class to configure ownership for
     * @param  null|Closure|string $attribute The ownership attribute name or custom closure
     * @return $this
     */
    public function ownedVia(Closure|string $model, Closure|string|null $attribute = null): self
    {
        Models::ownedVia($model, $attribute);

        return $this;
    }

    /**
     * Set a custom model class for abilities.
     *
     * Replaces Warden's default Ability model with a custom implementation, allowing
     * you to extend the ability model with custom attributes, relationships, or behavior.
     * The custom model must extend Warden's base Ability class or implement compatible
     * interfaces.
     *
     * @param string $model The fully-qualified class name of the ability model
     *
     * @throws InvalidArgumentException If the class does not exist
     *
     * @return $this
     */
    public function useAbilityModel(string $model): self
    {
        throw_unless(class_exists($model), InvalidArgumentException::class, sprintf('Class %s does not exist', $model));

        /** @var class-string $model */
        Models::setAbilitiesModel($model);

        return $this;
    }

    /**
     * Set a custom model class for roles.
     *
     * Replaces Warden's default Role model with a custom implementation, enabling
     * you to add custom attributes (e.g., color, icon), relationships, or methods
     * to the role model. The custom model must extend Warden's base Role class or
     * implement compatible interfaces.
     *
     * @param string $model The fully-qualified class name of the role model
     *
     * @throws InvalidArgumentException If the class does not exist
     *
     * @return $this
     */
    public function useRoleModel(string $model): self
    {
        throw_unless(class_exists($model), InvalidArgumentException::class, sprintf('Class %s does not exist', $model));

        /** @var class-string $model */
        Models::setRolesModel($model);

        return $this;
    }

    /**
     * Set a custom model class for users.
     *
     * Configures Warden to use a specific user model class for authorization
     * relationships. This is typically auto-detected from Laravel's auth configuration,
     * but can be overridden when using multiple user models or non-standard configurations.
     *
     * @param string $model The fully-qualified class name of the user model
     *
     * @throws InvalidArgumentException If the class does not exist
     *
     * @return $this
     */
    public function useUserModel(string $model): self
    {
        throw_unless(class_exists($model), InvalidArgumentException::class, sprintf('Class %s does not exist', $model));

        /** @var class-string $model */
        Models::setUsersModel($model);

        return $this;
    }

    /**
     * Configure custom table names for Warden's database tables.
     *
     * Overrides the default table names used by Warden's models, allowing integration
     * with existing database schemas or adherence to custom naming conventions. Provide
     * an array mapping table keys (e.g., 'abilities', 'roles', 'assigned_roles') to
     * actual table names.
     *
     * ```php
     * Warden::tables([
     *     'abilities' => 'permissions',
     *     'roles' => 'user_roles',
     * ]);
     * ```
     *
     * @param  array<string, string> $map Associative array mapping table keys to custom names
     * @return $this
     */
    public function tables(array $map): self
    {
        Models::setTables($map);

        return $this;
    }

    /**
     * Get or set the scope instance for multi-tenancy support.
     *
     * Configures or retrieves the global query scope that applies to all Warden
     * queries, enabling multi-tenancy by automatically filtering permissions to
     * the current tenant. Pass a ScopeInterface implementation to enable scoping,
     * or call without arguments to retrieve the current scope.
     *
     * ```php
     * Warden::scope(new TenantScope($currentTenant));
     * $currentScope = Warden::scope();
     * ```
     *
     * @param  null|ScopeInterface $scope The scope instance to set, or null to get current
     * @return mixed               Current scope when getting, or result of Models::scope() when setting
     */
    public function scope(?ScopeInterface $scope = null): mixed
    {
        return Models::scope($scope);
    }

    /**
     * Resolve a dependency from the service container.
     *
     * Internal helper method that retrieves instances from Laravel's service container.
     * Used throughout Warden to resolve dependencies like cache repositories and gates
     * without direct coupling to the container.
     *
     * @param  string               $abstract   The abstract type to resolve (e.g., interface or class name)
     * @param  array<string, mixed> $parameters Optional constructor parameters
     * @return mixed                The resolved instance from the container
     */
    private function resolve(string $abstract, array $parameters = []): mixed
    {
        return Container::getInstance()->make($abstract, $parameters);
    }
}
