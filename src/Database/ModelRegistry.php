<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Database;

use Cline\Warden\Contracts\ScopeInterface;
use Cline\Warden\Database\Scope\Scope;
use Cline\Warden\Exceptions\MorphKeyViolationException;
use Closure;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use RuntimeException;

use function array_filter;
use function array_key_exists;
use function array_merge;
use function assert;
use function class_exists;
use function is_bool;
use function is_string;
use function throw_if;

/**
 * Central registry for Warden model and table configuration.
 *
 * This registry pattern allows customization of model classes, table names,
 * ownership resolution, and tenant scoping across the entire Warden system.
 * All model instantiation and database queries should use this registry to
 * respect custom configurations.
 *
 * Bound as singleton in the service container for Octane compatibility.
 *
 * @author Brian Faust <brian@cline.sh>
 */
#[Singleton()]
final class ModelRegistry
{
    /**
     * Default User model class key for registration.
     *
     * This string is used as a lookup key in the models registry since
     * packages cannot hard-code application classes like App\User.
     */
    private const string USER_MODEL_KEY = 'User';

    /**
     * Registry mapping default model classes to custom implementations.
     *
     * Allows developers to extend Warden's Ability, Role, and User models
     * with custom classes while maintaining compatibility with the authorization system.
     *
     * @var array<class-string|string, class-string>
     */
    private array $models = [];

    /**
     * Registry mapping model classes to ownership resolution strategies.
     *
     * Defines how to determine if a model instance is owned by an authority.
     * Keys are model class names or '*' for a global default. Values are
     * attribute names or closures that perform the ownership check.
     *
     * @var array<string, Closure|string>
     */
    private array $ownership = [];

    /**
     * Registry mapping logical table names to physical database table names.
     *
     * Enables custom table naming without modifying model classes. Keys are
     * logical names like 'abilities', 'roles', etc. Values are actual table names.
     *
     * @var array<string, string>
     */
    private array $tables = [];

    /**
     * The active tenant scoping instance.
     *
     * Controls multi-tenancy behavior across all Warden models and queries.
     * Set to null for no scoping, or a ScopeInterface implementation for tenant isolation.
     */
    private ScopeInterface|Scope|null $scope = null;

    /**
     * Registry mapping model classes to their polymorphic key column names.
     *
     * Allows specifying which column should be used as the foreign key when a model
     * is referenced in polymorphic relationships (actor, boundary, subject, restricted_to).
     * Keys are model class names. Values are column names (e.g., 'id', 'ulid', 'uuid').
     *
     * @var array<class-string, string>
     */
    private array $keyMap = [];

    /**
     * Whether to enforce that all models have a defined key mapping.
     *
     * When true, using a model in a polymorphic relationship without a key mapping
     * will throw a MorphKeyViolationException. When false, defaults to 'id'.
     */
    private bool $enforceKeyMap = false;

    /**
     * Register a custom model class for abilities.
     *
     * Allows replacing the default Ability model with a custom implementation.
     * Automatically updates Eloquent's morph map to ensure polymorphic relations
     * resolve correctly.
     *
     * @param class-string $model Fully-qualified class name of the custom Ability model
     */
    public function setAbilitiesModel(string $model): void
    {
        $this->models[Ability::class] = $model;

        $this->updateMorphMap([$model]);
    }

    /**
     * Register a custom model class for roles.
     *
     * Allows replacing the default Role model with a custom implementation.
     * Automatically updates Eloquent's morph map to ensure polymorphic relations
     * resolve correctly.
     *
     * @param class-string $model Fully-qualified class name of the custom Role model
     */
    public function setRolesModel(string $model): void
    {
        $this->models[Role::class] = $model;

        $this->updateMorphMap([$model]);
    }

    /**
     * Register a custom model class for users.
     *
     * Allows replacing the default User model with a custom implementation.
     * Also registers the custom model's table name in the table registry for
     * consistent query building across the authorization system.
     *
     * @param class-string $model Fully-qualified class name of the custom User model
     */
    public function setUsersModel(string $model): void
    {
        $this->models[self::USER_MODEL_KEY] = $model;

        $this->tables['users'] = $this->user()->getTable();
    }

    /**
     * Register custom table names for Warden's database tables.
     *
     * Merges the provided table name mappings with existing registrations.
     * Common keys: 'abilities', 'roles', 'permissions', 'assigned_roles', 'users'.
     * Updates the morph map to ensure polymorphic relations work with custom names.
     *
     * @param array<string, string> $map Associative array of logical name => physical table name
     */
    public function setTables(array $map): void
    {
        $this->tables = array_merge($this->tables, $map);

        $this->updateMorphMap();
    }

    /**
     * Resolve a logical table name to its physical database table name.
     *
     * Returns the registered custom table name if one exists, otherwise returns
     * the logical name unchanged. This enables transparent table name customization
     * throughout the authorization system.
     *
     * @param  string $table Logical table name (e.g., 'abilities', 'roles')
     * @return string Physical database table name
     */
    public function table(string $table): string
    {
        if (array_key_exists($table, $this->tables)) {
            return $this->tables[$table];
        }

        return $table;
    }

    /**
     * Get or set the tenant scoping instance.
     *
     * When called with a ScopeInterface argument, registers it as the active scope.
     * When called without arguments, returns the current scope (creating a default
     * Scope instance if none exists). Controls multi-tenancy behavior system-wide.
     *
     * @param  null|ScopeInterface  $scope Optional scope instance to register
     * @return Scope|ScopeInterface The active scope instance
     */
    public function scope(?ScopeInterface $scope = null): ScopeInterface|Scope
    {
        if ($scope instanceof ScopeInterface) {
            return $this->scope = $scope;
        }

        if (!$this->scope instanceof ScopeInterface) {
            $this->scope = new Scope();
        }

        return $this->scope;
    }

    /**
     * Resolve a model class to its registered custom implementation.
     *
     * Returns the custom model class if one has been registered, otherwise
     * returns the original class name. Used internally to ensure all model
     * instantiation respects custom model registrations.
     *
     * @param  class-string $model Default model class name
     * @return class-string Registered custom class or the original class
     */
    public function classname(string $model): string
    {
        if (array_key_exists($model, $this->models)) {
            return $this->models[$model];
        }

        return $model;
    }

    /**
     * Update Eloquent's morph map with Warden model classes.
     *
     * Registers Warden models in Eloquent's polymorphic relation map to ensure
     * polymorphic relations (like permissions, assigned_roles) resolve to the
     * correct model classes. Called automatically when custom models are registered.
     *
     * @param null|array<int, class-string> $classNames Optional array of model classes to register
     */
    public function updateMorphMap(?array $classNames = null): void
    {
        if (null === $classNames) {
            $classNames = [
                $this->classname(Role::class),
                $this->classname(Ability::class),
            ];
        }

        /** @var array<string, class-string<Model>> $morphMap */
        $morphMap = array_filter($classNames, class_exists(...));
        Relation::morphMap($morphMap);
    }

    /**
     * Register an ownership resolution strategy for a model type.
     *
     * Defines how to determine if a model instance is owned by an authority during
     * authorization checks. Pass just $model to set a global default strategy.
     * Pass both parameters to configure a specific model type.
     *
     * ```php
     * // Set global default to check 'user_id' attribute
     * $registry->ownedVia('user_id');
     *
     * // Set specific model to use custom attribute
     * $registry->ownedVia(Post::class, 'author_id');
     *
     * // Use closure for complex logic
     * $registry->ownedVia(Post::class, fn($post, $user) => $post->author_id === $user->id);
     * ```
     *
     * @param Closure|string      $model     Model class name, or closure/attribute for global default
     * @param null|Closure|string $attribute Attribute name or closure to check ownership
     */
    public function ownedVia(Closure|string $model, Closure|string|null $attribute = null): void
    {
        if (null === $attribute) {
            $this->ownership['*'] = $model;

            return;
        }

        if (is_string($model)) {
            $this->ownership[$model] = $attribute;
        }
    }

    /**
     * Determine if a model is owned by an authority.
     *
     * Uses the registered ownership resolution strategy for the model's type,
     * falling back to a global strategy if defined, or defaulting to checking
     * the polymorphic actor relationship (actor_type/actor_id).
     *
     * @param  Model $authority The authority (typically a User) to check ownership against
     * @param  Model $model     The model instance to check ownership for
     * @return bool  True if the authority owns the model
     */
    public function isOwnedBy(Model $authority, Model $model): bool
    {
        $type = $model::class;

        // Check for custom ownership configuration
        if (array_key_exists($type, $this->ownership)) {
            $attribute = $this->ownership[$type];
        } elseif (array_key_exists('*', $this->ownership)) {
            $attribute = $this->ownership['*'];
        } else {
            // Use actor morph relationship
            return $authority->getMorphClass() === $model->getAttribute('actor_type')
                && $authority->getAttribute($this->getModelKey($authority)) === $model->getAttribute('actor_id');
        }

        return $this->isOwnedVia($attribute, $authority, $model);
    }

    /**
     * Create a new instance of the registered Ability model.
     *
     * Respects custom Ability model registrations and applies the given attributes.
     * Returns an unpersisted model instance.
     *
     * @param  array<string, mixed> $attributes Initial attribute values
     * @return Ability              Instance of the Ability model (or custom subclass)
     */
    public function ability(array $attributes = []): Ability
    {
        $model = $this->make(Ability::class, $attributes);
        assert($model instanceof Ability);

        return $model;
    }

    /**
     * Create a new instance of the registered Role model.
     *
     * Respects custom Role model registrations and applies the given attributes.
     * Returns an unpersisted model instance.
     *
     * @param  array<string, mixed> $attributes Initial attribute values
     * @return Role                 Instance of the Role model (or custom subclass)
     */
    public function role(array $attributes = []): Role
    {
        $model = $this->make(Role::class, $attributes);
        assert($model instanceof Role);

        return $model;
    }

    /**
     * Create a new instance of the registered User model.
     *
     * Respects custom User model registrations and applies the given attributes.
     * Returns an unpersisted model instance.
     *
     * @param  array<string, mixed> $attributes Initial attribute values
     * @return Model                Instance of the User model (or custom subclass)
     */
    public function user(array $attributes = []): Model
    {
        if (array_key_exists(self::USER_MODEL_KEY, $this->models)) {
            return $this->make($this->models[self::USER_MODEL_KEY], $attributes);
        }

        throw new RuntimeException('User model not configured. Call Models::setUsersModel() first.');
    }

    /**
     * Create a new query builder for a Warden table.
     *
     * Returns a fresh query builder instance configured with the User model's
     * database connection and targeting the resolved physical table name.
     * Useful for raw queries that need to respect custom table configurations.
     *
     * @param  string  $table Logical table name
     * @return Builder Configured query builder instance
     */
    public function query(string $table): Builder
    {
        $connection = $this->user()->getConnection();
        $grammar = $connection->getQueryGrammar();
        $processor = $connection->getPostProcessor();

        $query = new Builder($connection, $grammar, $processor);

        return $query->from($this->table($table));
    }

    /**
     * Register polymorphic key mappings for models.
     *
     * Defines which column should be used as the foreign key when models are
     * referenced in polymorphic relationships. Does not enforce the mapping.
     * Use enforceMorphKeyMap() to enable strict enforcement.
     *
     * ```php
     * $registry->morphKeyMap([
     *     User::class => 'uuid',
     *     Team::class => 'ulid',
     *     Post::class => 'id',
     * ]);
     * ```
     *
     * @param array<class-string, string> $map Model class => column name mappings
     */
    public function morphKeyMap(array $map): void
    {
        $this->keyMap = array_merge($this->keyMap, $map);
    }

    /**
     * Register polymorphic key mappings and enforce their usage.
     *
     * Like morphKeyMap(), but also enables strict enforcement. Any model used
     * in a polymorphic relationship without a defined mapping will throw a
     * MorphKeyViolationException, preventing accidental use of unmapped models.
     *
     * ```php
     * $registry->enforceMorphKeyMap([
     *     User::class => 'uuid',
     *     Team::class => 'ulid',
     * ]);
     *
     * // These will work fine
     * $ability->users()->attach($user);
     * $role->assignTo($team);
     *
     * // This will throw MorphKeyViolationException
     * $ability->users()->attach($post); // Post not in mapping
     * ```
     *
     * @param array<class-string, string> $map Model class => column name mappings
     */
    public function enforceMorphKeyMap(array $map): void
    {
        $this->morphKeyMap($map);
        $this->requireKeyMap();
    }

    /**
     * Enable strict enforcement of key mappings.
     *
     * After calling this, all models used in polymorphic relationships must
     * have a defined key mapping or a MorphKeyViolationException will be thrown.
     * Typically used via enforceMorphKeyMap() rather than directly.
     */
    public function requireKeyMap(): void
    {
        $this->enforceKeyMap = true;
    }

    /**
     * Get the polymorphic key column name for a model.
     *
     * Returns the mapped column name if one exists, otherwise returns the model's
     * actual key name. If enforcement is enabled and no mapping exists, throws
     * a MorphKeyViolationException to prevent accidental use of unmapped models.
     *
     * ```php
     * // With key map configured
     * $registry->enforceMorphKeyMap([
     *     User::class => 'uuid',
     *     Team::class => 'ulid',
     * ]);
     *
     * $key = $registry->getModelKey($user); // Returns 'uuid'
     * $key = $registry->getModelKey($team); // Returns 'ulid'
     * $key = $registry->getModelKey($post); // Throws MorphKeyViolationException
     * ```
     *
     * @param Model $model The model instance to get the key column for
     *
     * @throws MorphKeyViolationException When enforcement is enabled and model has no mapping
     *
     * @return string The column name to use for the foreign key
     */
    public function getModelKey(Model $model): string
    {
        $class = $model::class;

        if (array_key_exists($class, $this->keyMap)) {
            return $this->keyMap[$class];
        }

        throw_if($this->enforceKeyMap, MorphKeyViolationException::class, $class);

        return $model->getKeyName();
    }

    /**
     * Get the polymorphic key column name for a model class.
     *
     * Similar to getModelKey() but accepts a class name string instead of
     * a model instance. Returns the mapped column name if configured, otherwise
     * instantiates the model to retrieve its key name. If enforcement is enabled
     * and no mapping exists, throws a MorphKeyViolationException.
     *
     * ```php
     * $key = $registry->getModelKeyFromClass(User::class); // Returns 'uuid'
     * $key = $registry->getModelKeyFromClass(Team::class); // Returns 'ulid'
     * ```
     *
     * @param class-string $class The model class to get the key column for
     *
     * @throws MorphKeyViolationException When enforcement is enabled and model has no mapping
     *
     * @return string The column name to use for the foreign key
     */
    public function getModelKeyFromClass(string $class): string
    {
        if (array_key_exists($class, $this->keyMap)) {
            return $this->keyMap[$class];
        }

        throw_if($this->enforceKeyMap, MorphKeyViolationException::class, $class);

        /** @phpstan-ignore-next-line Instantiated class is expected to be Model with getKeyName() */
        return new $class()->getKeyName();
    }

    /**
     * Reset all registry settings to defaults.
     *
     * Clears all custom model registrations, table name mappings, and ownership
     * strategies. Primarily useful for testing to ensure a clean state between tests.
     * Does not affect the scope instance.
     */
    public function reset(): void
    {
        $this->models = [];
        $this->tables = [];
        $this->ownership = [];
        $this->keyMap = [];
        $this->enforceKeyMap = false;
    }

    /**
     * Evaluate ownership using an attribute or closure strategy.
     *
     * Internal helper that performs the actual ownership check. If the strategy
     * is a closure, invokes it with the model and authority. If it's an attribute
     * name, compares the authority's key to the model's attribute value.
     *
     * @param  Closure|string $attribute Ownership resolution strategy (attribute name or closure)
     * @param  Model          $authority The authority to check ownership against
     * @param  Model          $model     The model instance to check
     * @return bool           True if the authority owns the model according to the strategy
     */
    private function isOwnedVia(Closure|string $attribute, Model $authority, Model $model): bool
    {
        if ($attribute instanceof Closure) {
            $result = $attribute($model, $authority);

            return is_bool($result) && $result;
        }

        return $authority->getKey() === $model->getAttribute($attribute);
    }

    /**
     * Instantiate a model class respecting custom registrations.
     *
     * Internal factory method that resolves the model class through the registry
     * and creates a new instance with the given attributes. Used by ability(),
     * role(), and user() factory methods.
     *
     * @param  class-string         $model      Default model class name
     * @param  array<string, mixed> $attributes Initial attribute values
     * @return Model                New model instance
     */
    private function make(string $model, array $attributes = []): Model
    {
        $modelClass = $this->classname($model);

        $instance = new $modelClass($attributes);
        assert($instance instanceof Model);

        return $instance;
    }
}
