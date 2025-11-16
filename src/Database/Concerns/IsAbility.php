<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Database\Concerns;

use Cline\Warden\Constraints\Constrainer;
use Cline\Warden\Constraints\Group;
use Cline\Warden\Database\Models;
use Cline\Warden\Database\Permission;
use Cline\Warden\Database\Queries\AbilitiesForModel;
use Cline\Warden\Database\Role;
use Cline\Warden\Database\Scope\TenantScope;
use Cline\Warden\Database\Titles\AbilityTitle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

use function array_merge;
use function assert;
use function config;
use function is_string;
use function json_decode;
use function json_encode;
use function mb_strtolower;

/**
 * Provides ability-specific behavior and relationships to ability models.
 *
 * This trait transforms an Eloquent model into a Warden ability, providing
 * constraint management, polymorphic relationships with users and roles,
 * automatic title generation, and tenant scoping support.
 *
 * @author Brian Faust <brian@cline.sh>
 */
trait IsAbility
{
    /**
     * Boot the IsAbility trait for model lifecycle events.
     *
     * Registers tenant scoping and creation hooks. During creation, automatically
     * applies the current tenant scope and generates a human-readable title if not
     * explicitly provided.
     */
    public static function bootIsAbility(): void
    {
        TenantScope::register(static::class);

        static::creating(function ($ability): void {
            assert($ability instanceof Model);
            Models::scope()->applyToModel($ability);

            // @phpstan-ignore-next-line - $title property exists on Ability model
            if ($ability->title === null) {
                // @phpstan-ignore-next-line - $title property exists on Ability model
                $ability->title = AbilityTitle::from($ability)->toString();
            }
        });
    }

    /**
     * Create and persist a new ability scoped to a specific model or entity.
     *
     * Creates an ability that applies to either a specific model instance (if the
     * model exists) or all instances of a model type (if the model doesn't exist).
     * Use '*' as the model to create a global ability that applies to all entities.
     *
     * @param  Model|string                $model      The target model instance, class name, or '*' for global
     * @param  array<string, mixed>|string $attributes Ability attributes (name, options, etc.) or just the ability name
     * @return static                      The created and persisted ability instance
     */
    public static function createForModel($model, $attributes)
    {
        $model = static::makeForModel($model, $attributes);

        $model->save();

        return $model;
    }

    /**
     * Build a new ability instance scoped to a specific model without persisting.
     *
     * Creates an unpersisted ability that targets either a specific model instance,
     * all instances of a model type, or all entities globally. The subject_id is only
     * set if the model exists in the database. Accepts shorthand string syntax for
     * just setting the ability name.
     *
     * @param  Model|string                $model      The target model instance, class name, or '*' for global
     * @param  array<string, mixed>|string $attributes Ability attributes or just the ability name
     * @return static                      The new unpersisted ability instance
     */
    public static function makeForModel($model, $attributes)
    {
        if (is_string($attributes)) {
            $attributes = ['name' => $attributes];
        }

        if ($model === '*') {
            return new static()->forceFill($attributes + [
                'subject_type' => '*',
            ]);
        }

        if (is_string($model)) {
            /** @var Model */
            $model = new $model();
        }

        // @phpstan-ignore-next-line - $model has getMorphClass, exists, getAttribute as Model instance
        return new static()->forceFill($attributes + [
            'subject_type' => $model->getMorphClass(),
            'subject_id' => $model->exists ? $model->getAttribute(Models::getModelKey($model)) : null,
        ]);
    }

    /**
     * Determine if this ability has authorization constraints defined.
     *
     * Constraints provide additional authorization logic beyond simple role/ability
     * checks, such as ownership verification or field-level restrictions.
     *
     * @return bool True if constraints are configured in the options attribute
     */
    public function hasConstraints(): bool
    {
        // @phpstan-ignore-next-line - options is a JSON column, may be string|null
        return !empty($this->options['constraints']);
    }

    /**
     * Retrieve the constraint instance for this ability.
     *
     * Returns a constrainer object that encapsulates the authorization logic
     * for this ability. If no constraints are defined, returns an empty Group
     * constraint that allows all access.
     *
     * @return Constrainer The constraint instance configured for this ability
     */
    public function getConstraints(): Constrainer
    {
        // @phpstan-ignore-next-line - options is a JSON column, may be string|null
        if (empty($this->options['constraints'])) {
            return new Group();
        }

        /** @var array{class: class-string<Constrainer>, params: array<string, mixed>} $data */
        $data = $this->options['constraints'];

        return $data['class']::fromData($data['params']);
    }

    /**
     * Configure authorization constraints for this ability.
     *
     * Sets the constraint logic that will be evaluated during authorization checks.
     * The constrainer is serialized into the options JSON column for persistence.
     *
     * @param  Constrainer $constrainer The constraint instance to apply to this ability
     * @return $this
     */
    public function setConstraints(Constrainer $constrainer)
    {
        // @phpstan-ignore-next-line - options is a JSON column, cast to array for merge
        $this->options = array_merge($this->options ?? [], [
            'constraints' => $constrainer->data(),
        ]);

        return $this;
    }

    /**
     * Define the polymorphic many-to-many relationship with roles.
     *
     * Returns all roles that have been granted (or forbidden) this ability.
     * Includes forbidden and scope columns in the pivot table for permission
     * denial tracking and multi-tenancy support.
     *
     * @return MorphToMany<Role, MorphPivot>
     */
    public function roles(): MorphToMany
    {
        /** @var class-string<Role> $roleClass */
        $roleClass = Models::classname(Role::class);

        $relation = $this->morphedByMany(
            $roleClass,
            'actor',
            Models::table('permissions'),
        )->using(Permission::class)
            ->withPivot('forbidden', 'scope', 'context_id', 'context_type');

        // @phpstan-ignore-next-line - applyToRelation handles BelongsToMany/MorphToMany
        return Models::scope()->applyToRelation($relation);
    }

    /**
     * Define the polymorphic many-to-many relationship with users.
     *
     * Returns all users that have been directly granted (or forbidden) this ability,
     * independent of role assignments. Includes forbidden and scope columns in the
     * pivot table for direct permission management and multi-tenancy.
     *
     * @return MorphToMany<Model, MorphPivot>
     */
    public function users(): MorphToMany
    {
        /** @var class-string<Model> $userModel */
        $userModel = config('warden.user_model', 'App\Models\User');

        /** @var class-string<Model> $userClass */
        $userClass = Models::classname($userModel);

        // @phpstan-ignore-next-line - User class may not exist (App\User)
        $relation = $this->morphedByMany(
            $userClass,
            'actor',
            Models::table('permissions'),
        )->using(Permission::class)
            ->withPivot('forbidden', 'scope', 'context_id', 'context_type');

        // @phpstan-ignore-next-line - applyToRelation handles BelongsToMany/MorphToMany
        return Models::scope()->applyToRelation($relation);
    }

    /**
     * Define the polymorphic relationship to the subject model.
     *
     * Returns the subject model instance (e.g., Invoice, Post) that this
     * ability applies to. The subject represents WHAT the permission is about.
     * Null subject means the ability applies to all instances of a type or globally.
     *
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo('subject');
    }

    /**
     * Define the polymorphic relationship to the context model.
     *
     * Returns the context model instance (e.g., Team, Organization) that this
     * ability is scoped to. Enables context-aware permissions where abilities
     * are only valid within a specific organizational context.
     *
     * @return MorphTo<Model, $this>
     */
    public function context(): MorphTo
    {
        return $this->morphTo('context');
    }

    /**
     * Accessor to decode the JSON options attribute.
     *
     * Transforms the stored JSON string into an associative array containing
     * ability configuration such as constraints. Returns an empty array if no
     * options are configured.
     *
     * @return array<string, mixed> The decoded options configuration
     */
    protected function getOptionsAttribute(): array
    {
        if (empty($this->attributes['options'])) {
            return [];
        }

        $options = $this->attributes['options'];
        assert(is_string($options));

        /** @var array<string, mixed> */
        return json_decode($options, true);
    }

    /**
     * Mutator to encode the options attribute to JSON.
     *
     * Transforms the options array into a JSON string for database storage.
     * Used to persist configuration like constraints in a single column.
     *
     * @param array<string, mixed> $options The options configuration to encode
     */
    protected function setOptionsAttribute(array $options): void
    {
        $this->attributes['options'] = json_encode($options);
    }

    /**
     * Generate a unique identifier string for this ability.
     *
     * Constructs a machine-friendly identifier combining the ability name,
     * entity type, entity ID, and ownership restriction flag. Used for
     * caching and internal ability lookups. Format: "name-type-id-owned".
     *
     * @return string The lowercase unique identifier for this ability
     */
    final protected function getIdentifierAttribute(): string
    {
        $name = $this->attributes['name'];
        assert(is_string($name));
        $slug = $name;

        // @phpstan-ignore-next-line - attributes are mixed from Eloquent
        if ($this->attributes['subject_type'] !== null) {
            // @phpstan-ignore-next-line
            $slug .= '-'.$this->attributes['subject_type'];
        }

        // @phpstan-ignore-next-line
        if ($this->attributes['subject_id'] !== null) {
            // @phpstan-ignore-next-line
            $slug .= '-'.$this->attributes['subject_id'];
        }

        if (!empty($this->attributes['only_owned'])) {
            $slug .= '-owned';
        }

        return mb_strtolower($slug);
    }

    /**
     * Accessor for the virtual "slug" attribute.
     *
     * Provides backward compatibility and semantic aliasing for the identifier
     * attribute. Returns the same unique identifier string.
     *
     * @return string The ability's unique identifier slug
     */
    protected function getSlugAttribute(): string
    {
        return $this->getIdentifierAttribute();
    }

    /**
     * Query scope to filter abilities by guard name.
     *
     * Constrains the abilities query to only include abilities for a specific guard.
     * Useful for multi-guard applications where abilities are segregated by authentication guard.
     *
     * @param  Builder<static> $query     The query builder instance
     * @param  string          $guardName The guard name to filter by
     * @return Builder<static>
     */
    protected function scopeForGuard(Builder $query, string $guardName): Builder
    {
        return $query->where('guard_name', $guardName);
    }

    /**
     * Query scope to filter abilities by name.
     *
     * In non-strict mode, automatically includes wildcard abilities ('*') which
     * represent global permissions. In strict mode, only the exact name(s) provided
     * are matched without wildcard expansion.
     *
     * @param Builder<static>|\Illuminate\Database\Query\Builder $query  The query builder instance
     * @param array<string>|string                               $name   One or more ability names to filter by
     * @param bool                                               $strict If true, exclude wildcard abilities from results
     */
    protected function scopeByName($query, $name, $strict = false): void
    {
        $names = (array) $name;

        if (!$strict && $name !== '*') {
            $names[] = '*';
        }

        $query->whereIn($this->getTable().'.name', $names);
    }

    /**
     * Query scope to filter for simple (non-entity-specific) abilities.
     *
     * Returns only abilities that are not scoped to a specific entity type,
     * effectively filtering for global or general-purpose abilities that
     * apply across the entire application.
     *
     * @param Builder<static>|\Illuminate\Database\Query\Builder $query The query builder instance
     */
    protected function scopeSimpleAbility($query): void
    {
        $query->whereNull($this->getTable().'.subject_type');
    }

    /**
     * Query scope to filter abilities for a specific model or entity.
     *
     * Returns abilities scoped to the given model type and/or instance. In non-strict
     * mode, includes wildcard abilities that apply to all entities. In strict mode,
     * only returns abilities explicitly scoped to the specific model.
     *
     * @param Builder<static>|\Illuminate\Database\Query\Builder $query  The query builder instance
     * @param Model|string                                       $model  The target model instance or class name
     * @param bool                                               $strict If true, exclude wildcard and blanket abilities
     */
    protected function scopeForModel(Builder|\Illuminate\Database\Query\Builder $query, Model|string $model, bool $strict = false): void
    {
        new AbilitiesForModel()->constrain($query, $model, $strict);
    }
}
