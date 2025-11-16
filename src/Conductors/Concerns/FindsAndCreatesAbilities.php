<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Conductors\Concerns;

use BackedEnum;
use Cline\Warden\Database\Ability;
use Cline\Warden\Database\Models;
use Cline\Warden\Support\Helpers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use InvalidArgumentException;

use function array_diff;
use function array_key_exists;
use function array_map;
use function array_unique;
use function is_array;
use function is_int;
use function throw_unless;

/**
 * Trait for finding existing abilities or creating them on demand.
 *
 * Handles the complex task of resolving ability identifiers into database IDs.
 * Accepts abilities in various formats (names, IDs, models, maps) and creates
 * missing ability records as needed. Supports both simple abilities and
 * model-scoped abilities with ownership constraints.
 *
 * @author Brian Faust <brian@cline.sh>
 */
trait FindsAndCreatesAbilities
{
    /**
     * Get the IDs of the provided abilities.
     *
     * Resolves ability identifiers into database IDs, creating ability records
     * if they don't exist. Handles multiple input formats: single IDs, ability
     * models, names, and associative maps of abilities to model types.
     *
     * @param  array<int|string, mixed>|int|Model|string         $abilities  Ability identifier(s) in any supported format
     * @param  null|array<int|string, Model|string>|Model|string $model      Optional model(s) to scope abilities to
     * @param  array<string, mixed>                              $attributes Additional attributes for new ability records
     * @return array<int, int>                                   Array of ability IDs ready for permission association
     */
    protected function getAbilityIds($abilities, $model = null, array $attributes = [])
    {
        if ($abilities instanceof Model) {
            /** @var int $key */
            $key = $abilities->getKey();

            return [$key];
        }

        if (null !== $model) {
            /** @var array<int|string, string>|string $abilities */
            return $this->getModelAbilityKeys($abilities, $model, $attributes);
        }

        if (Helpers::isAssociativeArray($abilities)) {
            /** @var array<string, string> $abilities */
            return $this->getAbilityIdsFromMap($abilities, $attributes);
        }

        if (!is_array($abilities)) {
            $abilities = [$abilities];
        }

        /** @var iterable<int|string, int|Model|string> $abilities */
        return $this->getAbilityIdsFromArray($abilities, $attributes);
    }

    /**
     * Get the ability IDs for the given associative map.
     *
     * Processes maps in the format ['ability-name' => Entity::class] by recursively
     * resolving each ability-model pair. Also handles mixed arrays containing both
     * mapped and indexed ability names.
     *
     * @param  array<string, string> $map        Associative array mapping ability names to model classes
     * @param  array<string, mixed>  $attributes Additional attributes for ability records
     * @return array<int, int>       Array of resolved ability IDs from the map
     */
    protected function getAbilityIdsFromMap(array $map, array $attributes)
    {
        [$map, $list] = Helpers::partition($map, fn ($value, $key): bool => !is_int($key));

        /** @var array<int, int> */
        // @phpstan-ignore-next-line - $list is iterable after partition
        return $map->map(fn ($entity, $ability) => $this->getAbilityIds($ability, $entity, $attributes))->collapse()->merge($this->getAbilityIdsFromArray($list, $attributes))->all();
    }

    /**
     * Get the ability IDs from the provided array, creating missing ones.
     *
     * Groups abilities by type (strings, integers, models) for efficient batch
     * processing. String abilities are resolved by name, creating records as needed.
     * Integer IDs and model instances are used directly. BackedEnums are normalized to strings.
     *
     * @param  iterable<int|string, BackedEnum|int|Model|string> $abilities  Ability identifiers to resolve
     * @param  array<string, mixed>                              $attributes Additional attributes for new ability records
     * @return array<int, int>                                   Array of ability IDs
     */
    protected function getAbilityIdsFromArray($abilities, array $attributes)
    {
        $normalizedAbilities = [];

        foreach ($abilities as $ability) {
            $normalizedAbilities[] = Helpers::normalizeEnumValue($ability);
        }

        /** @var iterable<int|Model|string> $normalizedAbilities */
        $groups = Helpers::groupModelsAndIdentifiersByType($normalizedAbilities);

        $keyName = Models::ability()->getKeyName();

        /** @var array<int, string> $strings */
        $strings = $groups['strings'];
        $groups['strings'] = $this->abilitiesByName($strings, $attributes)
            ->pluck($keyName)->all();

        $groups['models'] = Arr::pluck($groups['models'], $keyName);

        /** @var array<int, int> */
        return Arr::collapse($groups);
    }

    /**
     * Get the abilities for the given model ability descriptors.
     *
     * Creates a Cartesian product of abilities and models, generating one ability
     * record for each combination. Finds existing abilities or creates new ones
     * with model scope constraints.
     *
     * @param  array<int|string, string>|string             $abilities  Ability name(s) to create for the model(s)
     * @param  array<int|string, Model|string>|Model|string $model      Model(s) to scope the abilities to
     * @param  array<string, mixed>                         $attributes Additional attributes for ability records
     * @return array<int, int>                              Array of ability IDs for all ability-model combinations
     */
    protected function getModelAbilityKeys($abilities, $model, array $attributes)
    {
        $abilities = Collection::make(is_array($abilities) ? $abilities : [$abilities]);

        $models = Collection::make(is_array($model) ? $model : [$model]);

        /** @var array<int, int> */
        return $abilities->map(fn ($ability) => $models->map(fn ($model) => $this->getModelAbility($ability, $model, $attributes)->getKey()))->collapse()->all();
    }

    /**
     * Get an ability for the given entity.
     *
     * Attempts to find an existing ability record with matching name, entity type,
     * and ownership flag. Creates a new ability if no match is found.
     *
     * @param  string               $ability    Ability name to find or create
     * @param  Model|string         $entity     Entity type to scope the ability to
     * @param  array<string, mixed> $attributes Additional attributes including only_owned flag
     * @return Ability              The existing or newly created ability model
     */
    protected function getModelAbility($ability, $entity, array $attributes)
    {
        $entity = $this->getEntityInstance($entity);

        $existing = $this->findAbility($ability, $entity, $attributes);

        return $existing ?: $this->createAbility($ability, $entity, $attributes);
    }

    /**
     * Find the ability for the given entity.
     *
     * Queries for an existing ability matching the name, entity type, and ownership
     * constraint. Applies scope for multi-tenancy isolation.
     *
     * @param  string               $ability    Ability name to search for
     * @param  Model|string         $entity     Entity type the ability is scoped to
     * @param  array<string, mixed> $attributes Attributes including only_owned flag
     * @return null|Ability         The found ability model, or null if not found
     */
    protected function findAbility($ability, $entity, array $attributes): ?Ability
    {
        $onlyOwned = array_key_exists('only_owned', $attributes) ? $attributes['only_owned'] : false;

        // @phpstan-ignore-next-line - Model instances support query builder methods via __call
        $query = Models::ability()
            ->where('name', $ability)
            ->where('guard_name', $this->guardName)
            ->forModel($entity, true)
            ->where('only_owned', $onlyOwned);

        // @phpstan-ignore-next-line - $query is EloquentBuilder after query methods
        return Models::scope()->applyToModelQuery($query)->first();
    }

    /**
     * Create an ability for the given entity.
     *
     * Creates a new ability record with model scope constraints. Sets the actor_type
     * and actor_id based on the provided entity (model class or instance).
     *
     * @param  string               $ability    Ability name for the new record
     * @param  Model|string         $entity     Entity to scope the ability to
     * @param  array<string, mixed> $attributes Additional attributes for the ability
     * @return Ability              The newly created ability model
     */
    protected function createAbility($ability, $entity, $attributes)
    {
        return Models::ability()->createForModel($entity, $attributes + [
            'name' => $ability,
            'guard_name' => $this->guardName,
        ]);
    }

    /**
     * Get an instance of the given model.
     *
     * Ensures the model is in a usable state for ability creation. Returns wildcard
     * as-is for universal abilities. Instantiates class names. Validates that model
     * instances exist in the database to prevent unintended universal grants.
     *
     * @param Model|string $model Model instance, class name, or wildcard
     *
     * @throws InvalidArgumentException If a non-existent model instance is provided
     *
     * @return Model|string Model instance or wildcard string
     */
    protected function getEntityInstance(Model|string $model): Model|string
    {
        if ($model === '*') {
            return '*';
        }

        if (!$model instanceof Model) {
            /** @var class-string<Model> $modelClass */
            $modelClass = $model;

            return new $modelClass();
        }

        // Creating an ability for a non-existent model gives the authority that
        // ability on all instances of that model. If the developer passed in
        // a model instance that does not exist, it is probably a mistake.
        throw_unless($model->exists, InvalidArgumentException::class, 'The model does not exist. To edit access to all models, use the class name instead');

        return $model;
    }

    /**
     * Get or create simple abilities by their name.
     *
     * Finds existing simple abilities (without entity scope) matching the provided
     * names, then creates any missing ones. Returns a collection of ability models.
     *
     * @param  array<int, string>|string $abilities  Ability name(s) to find or create
     * @param  array<string, mixed>      $attributes Additional attributes for new abilities
     * @return Collection<int, Ability>  Collection of ability models
     */
    protected function abilitiesByName(array|string $abilities, array $attributes = []): Collection
    {
        $abilities = array_unique(is_array($abilities) ? $abilities : [$abilities]);

        if ($abilities === []) {
            return new Collection();
        }

        // @phpstan-ignore-next-line - Model instances support query builder methods via __call
        $existing = Models::ability()
            ->simpleAbility()
            ->where('guard_name', $this->guardName)
            ->whereIn('name', $abilities)
            ->get();

        return $existing->merge($this->createMissingAbilities(
            $existing,
            $abilities,
            $attributes,
        ));
    }

    /**
     * Create the non-existent abilities by name.
     *
     * Diffs the requested ability names against existing ones, then bulk creates
     * the missing abilities as simple (non-scoped) ability records.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Ability> $existing   Existing ability models
     * @param  array<int, string>                                     $abilities  All requested ability names
     * @param  array<string, mixed>                                   $attributes Additional attributes for new abilities
     * @return array<int, Ability>                                    Array of newly created ability models
     */
    protected function createMissingAbilities(\Illuminate\Database\Eloquent\Collection $existing, array $abilities, array $attributes = []): array
    {
        $missing = array_diff($abilities, $existing->pluck('name')->all());

        // @phpstan-ignore-next-line - Model instances support create() method via __call
        return array_map(fn (string $ability) => Models::ability()->create($attributes + [
            'name' => $ability,
            'guard_name' => $this->guardName,
        ]), $missing);
    }
}
