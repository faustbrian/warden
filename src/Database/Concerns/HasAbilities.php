<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Database\Concerns;

use Cline\Warden\Conductors\ForbidsAbilities;
use Cline\Warden\Conductors\GivesAbilities;
use Cline\Warden\Conductors\RemovesAbilities;
use Cline\Warden\Conductors\UnforbidsAbilities;
use Cline\Warden\Contracts\ClipboardInterface;
use Cline\Warden\Database\Ability;
use Cline\Warden\Database\Models;
use Cline\Warden\Database\Permission;
use Cline\Warden\Support\Helpers;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

use function assert;

/**
 * Provides ability management for entities and authorities.
 *
 * Enables models to have abilities granted or forbidden, with support for
 * fluent builder patterns for complex permission assignments. Automatically
 * cleans up ability relationships when models are deleted. Used by both
 * User models (authorities) and other models that can have permissions.
 *
 * @author Brian Faust <brian@cline.sh>
 */
trait HasAbilities
{
    /**
     * Register model event handlers for ability cleanup.
     *
     * Automatically detaches all abilities when the model is permanently
     * deleted. Respects soft deletes - abilities are only removed when
     * the model is force deleted or doesn't use soft deletes.
     */
    public static function bootHasAbilities(): void
    {
        static::deleted(function ($model): void {
            assert($model instanceof Model);

            if (!Helpers::isSoftDeleting($model)) {
                /** @phpstan-ignore-next-line Dynamic abilities() relationship from trait */
                $model->abilities()->detach();
            }
        });
    }

    /**
     * Define the many-to-many relationship with abilities.
     *
     * Establishes the polymorphic relationship between this model and
     * abilities through the permissions pivot table. Includes forbidden
     * flag and scope identifier for multi-tenant support.
     *
     * @return MorphToMany<Ability, $this> The configured relationship instance
     */
    public function abilities(): MorphToMany
    {
        /** @var class-string<Ability> $abilityClass */
        $abilityClass = Models::classname(Ability::class);

        $relation = $this->morphToMany(
            $abilityClass,
            'actor',
            Models::table('permissions'),
            relatedPivotKey: null,
            parentKey: Models::getModelKey($this),
        )->using(Permission::class)
            ->withPivot('forbidden', 'scope', 'boundary_id', 'boundary_type');

        /** @phpstan-ignore-next-line Laravel's MorphToMany extends BelongsToMany with compatible generics */
        return Models::scope()->applyToRelation($relation);
    }

    /**
     * Retrieve all abilities granted to this model.
     *
     * Returns the collection of allowed abilities, including those inherited
     * from roles if this is an authority model. Does not include forbidden
     * abilities. May return cached results if caching is enabled.
     *
     * @return Collection<int, Ability> Collection of allowed ability models
     */
    public function getAbilities(): Collection
    {
        return Container::getInstance()
            ->make(ClipboardInterface::class)
            ->getAbilities($this);
    }

    /**
     * Retrieve all abilities explicitly forbidden for this model.
     *
     * Returns abilities marked as forbidden, which take precedence over
     * granted abilities in authorization checks. Useful for denying
     * specific permissions to users who would otherwise inherit them.
     *
     * @return Collection<int, Ability> Collection of forbidden ability models
     */
    public function getForbiddenAbilities(): Collection
    {
        return Container::getInstance()
            ->make(ClipboardInterface::class)
            ->getAbilities($this, false);
    }

    /**
     * Grant an ability to this model or return a fluent builder.
     *
     * When called without arguments, returns a GivesAbilities builder for
     * fluent permission assignment with constraints. When called with
     * arguments, immediately grants the specified ability.
     *
     * ```php
     * // Simple grant
     * $user->allow('edit', Post::class);
     *
     * // Fluent builder with constraints
     * $user->allow()->to('edit', Post::class)->where('status', 'draft');
     * ```
     *
     * @param  null|array<int|Model|string>|int|Model|string $ability The ability name or ID to grant
     * @param  null|Model|string                             $model   The model class or instance to grant ability for
     * @return $this|GivesAbilities                          Returns builder if no args, $this if ability granted
     */
    public function allow($ability = null, $model = null)
    {
        if (null === $ability) {
            return new GivesAbilities($this);
        }

        new GivesAbilities($this)->to($ability, $model);

        return $this;
    }

    /**
     * Remove an ability from this model or return a fluent builder.
     *
     * When called without arguments, returns a RemovesAbilities builder.
     * When called with arguments, immediately removes the specified ability.
     * Only removes allowed abilities; use unforbid() to remove forbidden ones.
     *
     * @param  null|array<int|string, mixed>|int|Model|string $ability The ability name or ID to remove
     * @param  null|Model|string                              $model   The model class or instance to remove ability for
     * @return $this|RemovesAbilities                         Returns builder if no args, $this if ability removed
     */
    public function disallow(array|Model|int|string|null $ability = null, Model|string|null $model = null)
    {
        if (null === $ability) {
            return new RemovesAbilities($this);
        }

        new RemovesAbilities($this)->to($ability, $model);

        return $this;
    }

    /**
     * Explicitly forbid an ability for this model or return a fluent builder.
     *
     * Forbidden abilities take precedence over granted abilities, useful for
     * denying specific permissions that would otherwise be inherited from
     * roles. When called without arguments, returns a ForbidsAbilities builder.
     *
     * ```php
     * // Forbid ability immediately
     * $user->forbid('delete', Post::class);
     *
     * // Fluent builder with constraints
     * $user->forbid()->to('edit', Post::class)->where('user_id', '!=', $user->id);
     * ```
     *
     * @param  null|array<int|Model|string>|int|Model|string $ability The ability name or ID to forbid
     * @param  null|Model|string                             $model   The model class or instance to forbid ability for
     * @return $this|ForbidsAbilities                        Returns builder if no args, $this if ability forbidden
     */
    public function forbid($ability = null, $model = null)
    {
        if (null === $ability) {
            return new ForbidsAbilities($this);
        }

        new ForbidsAbilities($this)->to($ability, $model);

        return $this;
    }

    /**
     * Remove a forbidden ability from this model or return a fluent builder.
     *
     * Removes the forbidden flag from an ability, potentially allowing it
     * again if granted elsewhere (e.g., through roles). When called without
     * arguments, returns an UnforbidsAbilities builder.
     *
     * @param  null|array<int|string, mixed>|int|Model|string $ability The ability name or ID to unforbid
     * @param  null|Model|string                              $model   The model class or instance to unforbid ability for
     * @return $this|UnforbidsAbilities                       Returns builder if no args, $this if ability unforbidden
     */
    public function unforbid(array|Model|int|string|null $ability = null, Model|string|null $model = null)
    {
        if (null === $ability) {
            return new UnforbidsAbilities($this);
        }

        new UnforbidsAbilities($this)->to($ability, $model);

        return $this;
    }
}
