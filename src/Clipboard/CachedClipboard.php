<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Clipboard;

use Cline\Warden\Contracts\CachedClipboardInterface;
use Cline\Warden\Database\Ability;
use Cline\Warden\Database\Models;
use Illuminate\Cache\TaggedCache;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as BaseCollection;
use Override;

use function assert;
use function implode;
use function is_array;
use function is_int;
use function is_string;
use function mb_strtolower;
use function method_exists;
use function sprintf;

/**
 * Cached clipboard implementation for high-performance authorization checks.
 *
 * Wraps the base clipboard functionality with a caching layer to avoid repeated
 * database queries for abilities and roles. Uses tagged caching when available
 * for efficient cache invalidation, falling back to individual key invalidation
 * when tags are not supported by the cache driver.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class CachedClipboard extends AbstractClipboard implements CachedClipboardInterface
{
    /**
     * The base cache tag identifier for all authorization data.
     */
    private string $tag = 'silber-bouncer';

    /**
     * The cache store instance for persisting authorization data.
     */
    private Store|TaggedCache $cache;

    /**
     * Create a new cached clipboard instance.
     *
     * @param Store $cache The cache store to use for caching authorization data
     */
    public function __construct(Store $cache)
    {
        $this->setCache($cache);
    }

    /**
     * Set the cache instance with optional tag support.
     *
     * Wraps the cache store with tags if the driver supports tagging, enabling
     * efficient bulk cache invalidation. Falls back to untagged caching for
     * drivers like file or database that don't support tags.
     *
     * @param  Store $cache The cache store to use
     * @return self  Fluent interface for method chaining
     */
    public function setCache(Store $cache): static
    {
        if (method_exists($cache, 'tags')) {
            $tagged = $cache->tags($this->tag());
            assert($tagged instanceof Store || $tagged instanceof TaggedCache);
            $cache = $tagged;
        }

        $this->cache = $cache;

        return $this;
    }

    /**
     * Get the cache instance.
     *
     * @return Store|TaggedCache The currently configured cache store
     */
    public function getCache(): Store|TaggedCache
    {
        return $this->cache;
    }

    /**
     * Determine if the given authority has the given ability, and return the ability ID.
     *
     * Checks abilities using cached data for performance. Forbidden abilities take
     * precedence - if any forbidden ability matches, returns false immediately.
     * Otherwise returns the ability ID if allowed, or null if not found.
     *
     * @param  Model             $authority The authority model to check
     * @param  string            $ability   The ability name to verify
     * @param  null|Model|string $model     Optional model to scope the ability check
     * @return null|false|int    False if forbidden, ability ID if allowed, null if not found
     */
    public function checkGetId(Model $authority, string $ability, Model|string|null $model = null): false|int|null
    {
        $applicable = $this->compileAbilityIdentifiers($ability, $model);

        // We will first check if any of the applicable abilities have been forbidden.
        // If so, we'll return false right away, so as to not pass the check. Then,
        // we'll check if any of them have been allowed & return the matched ID.
        $forbiddenId = $this->findMatchingAbility(
            /** @phpstan-ignore-next-line argument.type (Laravel Collection invariant generics) */
            $this->getForbiddenAbilities($authority),
            $applicable,
            $model,
            $authority,
        );

        if ($forbiddenId) {
            return false;
        }

        return $this->findMatchingAbility(
            /** @phpstan-ignore-next-line argument.type (Laravel Collection invariant generics) */
            $this->getAbilities($authority),
            $applicable,
            $model,
            $authority,
        );
    }

    /**
     * Get the given authority's abilities from cache or database.
     *
     * Checks cache first for performance. If not cached, fetches from database
     * and stores in cache indefinitely. The cache is invalidated when abilities
     * are modified through the authorization system.
     *
     * @param  Model                    $authority The authority model to retrieve abilities for
     * @param  bool                     $allowed   True for allowed abilities, false for forbidden
     * @return Collection<int, Ability> Collection of ability models
     */
    #[Override()]
    public function getAbilities(Model $authority, bool $allowed = true): Collection
    {
        $key = $this->getCacheKey($authority, 'abilities', $allowed);

        $cached = $this->cache->get($key);

        if (is_array($cached)) {
            /** @var array<int, array<string, mixed>> $cached */
            return $this->deserializeAbilities($cached);
        }

        $abilities = $this->getFreshAbilities($authority, $allowed);

        $this->cache->forever($key, $this->serializeAbilities($abilities));

        return $abilities;
    }

    /**
     * Get a fresh copy of the given authority's abilities bypassing cache.
     *
     * Forces a database query to retrieve current abilities, ignoring any cached
     * values. Useful after modifying abilities to ensure latest data is retrieved.
     *
     * @param  Model                    $authority The authority model to retrieve abilities for
     * @param  bool                     $allowed   True for allowed abilities, false for forbidden
     * @return Collection<int, Ability> Fresh collection of ability models from database
     */
    public function getFreshAbilities(Model $authority, bool $allowed): Collection
    {
        return parent::getAbilities($authority, $allowed);
    }

    /**
     * Get the given authority's roles' IDs and names from cache.
     *
     * Uses the sear pattern (store and retrieve) to cache the roles lookup
     * indefinitely until explicitly invalidated via refresh methods.
     *
     * @param  Model                                                                       $authority The authority model to retrieve roles for
     * @return array{ids: BaseCollection<int, string>, names: BaseCollection<string, int>} Cached roles lookup array
     */
    #[Override()]
    public function getRolesLookup(Model $authority): array
    {
        $key = $this->getCacheKey($authority, 'roles');

        $result = $this->sear($key, fn (): array => parent::getRolesLookup($authority));

        assert(is_array($result) && isset($result['ids'], $result['names']));

        /** @var array{ids: BaseCollection<int, string>, names: BaseCollection<string, int>} $result */
        return $result;
    }

    /**
     * Clear the authorization cache globally or for a specific authority.
     *
     * When authority is null, clears all cached authorization data. Uses tagged
     * cache flush for performance when available, otherwise iterates through all
     * users and roles to clear individual cache entries.
     *
     * @param  null|Model $authority Optional authority to refresh cache for. If null, refreshes all.
     * @return self       Fluent interface for method chaining
     */
    public function refresh(?Model $authority = null): static
    {
        if ($authority instanceof Model) {
            return $this->refreshFor($authority);
        }

        if ($this->cache instanceof TaggedCache) {
            $this->cache->flush();
        } else {
            $this->refreshAllIteratively();
        }

        return $this;
    }

    /**
     * Clear the cache for the given authority.
     *
     * Removes all cached abilities (allowed and forbidden) and roles for the
     * specified authority model, forcing fresh database queries on next access.
     *
     * @param  Model $authority The authority model to clear cache for
     * @return self  Fluent interface for method chaining
     */
    public function refreshFor(Model $authority): static
    {
        $this->cache->forget($this->getCacheKey($authority, 'abilities', true));
        $this->cache->forget($this->getCacheKey($authority, 'abilities', false));
        $this->cache->forget($this->getCacheKey($authority, 'roles'));

        return $this;
    }

    /**
     * Determine if any of the abilities can be matched against the provided applicable ones.
     *
     * Searches through the authority's abilities to find one matching the compiled
     * ability identifiers. Checks both regular abilities and ownership-scoped variants
     * when the authority owns the model.
     *
     * @param  BaseCollection<int, mixed>|Collection<int, mixed> $abilities  Collection of authority's abilities
     * @param  BaseCollection<int, string>                       $applicable Compiled ability identifiers to match against
     * @param  mixed|Model                                       $model      The model being checked for abilities
     * @param  Model                                             $authority  The authority performing the check
     * @return null|int                                          The matched ability ID, or null if no match found
     */
    private function findMatchingAbility(Collection|BaseCollection $abilities, BaseCollection $applicable, mixed $model, Model $authority): ?int
    {
        /** @var BaseCollection<int, string> */
        $abilities = $abilities->toBase()->pluck('identifier', 'id');

        if ($id = $this->getMatchedAbilityId($abilities, $applicable)) {
            return $id;
        }

        if ($this->isOwnedBy($authority, $model)) {
            /** @var BaseCollection<int, string> */
            $ownedApplicable = $applicable->map(fn (string $identifier): string => $identifier.'-owned');

            return $this->getMatchedAbilityId(
                $abilities,
                $ownedApplicable,
            );
        }

        return null;
    }

    /**
     * Get the ID of the ability that matches one of the applicable abilities.
     *
     * Iterates through the ability map to find the first identifier that matches
     * one of the applicable ability identifiers generated from the request.
     *
     * @param  BaseCollection<int, string> $abilityMap Map of ability IDs to their identifiers
     * @param  BaseCollection<int, string> $applicable Collection of applicable ability identifiers
     * @return null|int                    The matched ability ID, or null if no match
     */
    private function getMatchedAbilityId(BaseCollection $abilityMap, BaseCollection $applicable): ?int
    {
        foreach ($abilityMap as $id => $identifier) {
            if ($applicable->contains($identifier)) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Compile a list of ability identifiers that match the provided parameters.
     *
     * Generates all possible ability identifier variations for matching against stored
     * abilities. Includes wildcards and specific model identifiers, normalized to
     * lowercase for case-insensitive matching.
     *
     * @param  string                      $ability The ability name being checked
     * @param  null|Model|string           $model   Optional model to generate model-specific identifiers
     * @return BaseCollection<int, string> Collection of lowercase ability identifiers to match
     */
    private function compileAbilityIdentifiers(string $ability, Model|string|null $model): BaseCollection
    {
        $identifiers = new BaseCollection(
            null === $model
                ? [$ability, '*-*', '*']
                : $this->compileModelAbilityIdentifiers($ability, $model),
        );

        /** @var BaseCollection<int, string> */
        // @codeCoverageIgnoreStart
        return $identifiers->map(fn ($value) => mb_strtolower($value));
        // @codeCoverageIgnoreEnd
    }

    /**
     * Compile a list of ability identifiers that match the given model.
     *
     * Generates hierarchical ability identifiers from most specific to most general:
     * ability-type-id, ability-type, ability-*, *-type, *-*. For existing models,
     * includes instance-specific identifiers with the model's primary key.
     *
     * @param  string             $ability The ability name being checked
     * @param  Model|string       $model   The model instance or class to generate identifiers for
     * @return array<int, string> Array of ability identifiers from specific to general
     */
    private function compileModelAbilityIdentifiers(string $ability, Model|string $model): array
    {
        if ($model === '*') {
            return [$ability.'-*', '*-*'];
        }

        if (is_string($model)) {
            $model = new $model();
        }

        assert($model instanceof Model);

        $type = $model->getMorphClass();

        $abilities = [
            sprintf('%s-%s', $ability, $type),
            $ability.'-*',
            '*-'.$type,
            '*-*',
        ];

        if ($model->exists) {
            $key = $model->getKey();
            assert($key !== null && (is_string($key) || is_int($key)));
            $abilities[] = sprintf('%s-%s-%s', $ability, $type, $key);
            $abilities[] = sprintf('*-%s-%s', $type, $key);
        }

        return $abilities;
    }

    /**
     * Get an item from the cache, or store the default value forever.
     *
     * Laravel-style "sear" pattern that retrieves from cache if present, otherwise
     * executes the callback and stores the result indefinitely. Used for permanent
     * caching of authorization data that only changes when explicitly invalidated.
     *
     * @param  string   $key      The cache key to retrieve or store under
     * @param  callable $callback Function to execute if cache miss, result will be cached
     * @return mixed    The cached or freshly computed value
     */
    private function sear(string $key, callable $callback): mixed
    {
        if (null === ($value = $this->cache->get($key))) {
            $this->cache->forever($key, $value = $callback());
        }

        return $value;
    }

    /**
     * Refresh the cache for all roles and users, iteratively.
     *
     * Fallback method used when the cache driver doesn't support tags. Iterates
     * through all users and roles in the system to clear their individual cache
     * entries. Less performant than tagged cache flush but ensures compatibility
     * with all cache drivers.
     */
    private function refreshAllIteratively(): void
    {
        foreach (Models::user()->all() as $user) {
            $this->refreshFor($user);
        }

        foreach (Models::role()->all() as $role) {
            $this->refreshFor($role);
        }
    }

    /**
     * Get the cache key for the given model's cache type.
     *
     * Generates a unique cache key combining the scope-aware tag, cache type
     * (abilities/roles), model identity, and allowed/forbidden flag. Ensures
     * cache isolation between different scopes and authority types.
     *
     * @param  Model  $model   The model to generate cache key for
     * @param  string $type    The cache type ('abilities' or 'roles')
     * @param  bool   $allowed True for allowed abilities, false for forbidden
     * @return string The fully qualified cache key
     */
    private function getCacheKey(Model $model, string $type, bool $allowed = true): string
    {
        return implode('-', [
            $this->tag(),
            $type,
            $model->getMorphClass(),
            $model->getKey(),
            $allowed ? 'a' : 'f',
        ]);
    }

    /**
     * Get the cache tag with scope suffix.
     *
     * Appends the current authorization scope to the base cache tag, enabling
     * multi-tenancy where different scopes have isolated authorization caches.
     *
     * @return string The scope-aware cache tag
     */
    private function tag(): string
    {
        return Models::scope()->appendToCacheKey($this->tag);
    }

    /**
     * Deserialize an array of abilities into a collection of models.
     *
     * Rehydrates ability models from cached attribute arrays without querying
     * the database. Models are created as existing instances without timestamps
     * or attribute casting to match their original state.
     *
     * @param  array<int, array<string, mixed>> $abilities Array of ability attribute arrays from cache
     * @return Collection<int, Ability>         Collection of hydrated ability models
     */
    private function deserializeAbilities(array $abilities): Collection
    {
        /** @var Collection<int, Ability> */
        return Ability::query()->hydrate($abilities);
    }

    /**
     * Serialize a collection of ability models into a plain array.
     *
     * Extracts only the model attributes for cache storage, excluding relationships
     * and computed properties to minimize cache size and avoid serialization issues.
     *
     * @param  Collection<int, Ability>         $abilities Collection of ability models to serialize
     * @return array<int, array<string, mixed>> Array of ability attribute arrays suitable for caching
     */
    private function serializeAbilities(Collection $abilities): array
    {
        // @codeCoverageIgnoreStart
        return $abilities->map(fn ($ability) => $ability->getAttributes())->all();
        // @codeCoverageIgnoreEnd
    }
}
