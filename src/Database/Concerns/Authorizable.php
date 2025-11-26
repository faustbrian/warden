<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Database\Concerns;

use BackedEnum;
use Cline\Warden\Contracts\ClipboardInterface;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;

/**
 * Provides authorization checking methods for authority models.
 *
 * Adds can(), cant(), and cannot() methods to any model that needs to
 * act as an authority (typically User models). These methods integrate
 * with Laravel's Gate system and provide a fluent API for authorization
 * checks throughout the application.
 *
 * @author Brian Faust <brian@cline.sh>
 */
trait Authorizable
{
    /**
     * Check if this authority has the specified ability.
     *
     * Primary authorization check method that evaluates whether this model
     * has permission to perform the given ability, optionally on a specific
     * model instance or class. Considers granted and forbidden abilities
     * along with any attached constraints.
     *
     * ```php
     * // Check if user can edit posts in general
     * $user->can('edit', Post::class);
     *
     * // Check if user can edit a specific post
     * $user->can('edit', $post);
     * ```
     *
     * @param  BackedEnum|string $ability The ability name to check (e.g., 'edit', 'delete')
     * @param  null|Model        $model   Optional model instance or class to check against
     * @return bool              True if the authority has the ability, false otherwise
     */
    public function can(BackedEnum|string $ability, Model|string|null $model = null): bool
    {
        return Container::getInstance()
            ->make(ClipboardInterface::class)
            ->check($this, $ability, $model);
    }

    /**
     * Check if this authority lacks the specified ability.
     *
     * Convenience method that inverts the result of can(). Useful for
     * conditional logic and guard clauses where lack of permission
     * should trigger specific behavior.
     *
     * @param  BackedEnum|string $ability The ability name to check
     * @param  null|Model|string $model   Optional model instance or class to check against
     * @return bool              True if the authority lacks the ability, false if they have it
     */
    public function cant(BackedEnum|string $ability, Model|string|null $model = null): bool
    {
        return !$this->can($ability, $model);
    }

    /**
     * Check if this authority lacks the specified ability.
     *
     * Alias for cant() that matches Laravel's convention. Provides
     * more grammatically correct method naming in some contexts.
     *
     * @param  BackedEnum|string $ability The ability name to check
     * @param  null|Model|string $model   Optional model instance or class to check against
     * @return bool              True if the authority lacks the ability, false if they have it
     */
    public function cannot(BackedEnum|string $ability, Model|string|null $model = null): bool
    {
        return $this->cant($ability, $model);
    }
}
