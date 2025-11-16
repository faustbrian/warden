<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Contracts;

use Cline\Warden\Database\Ability;
use Illuminate\Cache\TaggedCache;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Extends authorization checking with caching capabilities.
 *
 * Adds cache management methods to the clipboard interface, enabling
 * performance optimization through cached ability lookups. Supports
 * selective cache invalidation when permissions change.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface CachedClipboardInterface extends ClipboardInterface
{
    /**
     * Set the cache store instance for ability caching.
     *
     * Configures the cache backend used to store ability lookups.
     * Typically set during service container binding to share the
     * application's default cache store.
     *
     * @param  Store $cache The Laravel cache store instance
     * @return $this Returns $this for method chaining
     */
    public function setCache(Store $cache): static;

    /**
     * Retrieve the configured cache store instance.
     *
     * @return Store|TaggedCache The cache store being used for ability caching
     */
    public function getCache(): Store|TaggedCache;

    /**
     * Retrieve abilities directly from storage, bypassing cache.
     *
     * Forces a database query to get the current abilities for the authority,
     * ignoring any cached values. Useful when you need to ensure you have
     * the absolute latest data after permission changes.
     *
     * @param  Model                    $authority The authority (user) whose abilities to retrieve
     * @param  bool                     $allowed   Whether to retrieve allowed (true) or forbidden (false) abilities
     * @return Collection<int, Ability> The fresh collection of ability models
     */
    public function getFreshAbilities(Model $authority, bool $allowed): Collection;

    /**
     * Clear cached abilities for all or a specific authority.
     *
     * When called without arguments, clears the entire ability cache.
     * When called with an authority, clears only that authority's cached
     * abilities. Use after permission changes to ensure fresh data.
     *
     * @param  null|Model $authority The specific authority to clear cache for, or null for all
     * @return $this      Returns $this for method chaining
     */
    public function refresh(?Model $authority = null): static;

    /**
     * Clear cached abilities for a specific authority.
     *
     * Convenience method for clearing a single authority's cached abilities.
     * Equivalent to calling refresh() with the authority parameter.
     *
     * @param  Model $authority The authority whose cache should be cleared
     * @return $this Returns $this for method chaining
     */
    public function refreshFor(Model $authority): static;
}
