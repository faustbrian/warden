<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Conductors\Concerns;

use BackedEnum;
use Cline\Warden\Conductors\Lazy\ConductsAbilities as LazyConductsAbilities;
use Cline\Warden\Conductors\Lazy\HandlesOwnership as LazyHandlesOwnership;
use Cline\Warden\Support\Helpers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

use function func_num_args;
use function is_array;
use function is_string;

/**
 * Trait providing convenience methods for conducting ability operations.
 *
 * Offers shorthand methods for common permission patterns like managing all
 * abilities on models, ownership-based permissions, and wildcard grants.
 * Also handles lazy conductor pattern detection and instantiation.
 *
 * @author Brian Faust <brian@cline.sh>
 */
trait ConductsAbilities
{
    /**
     * Allow/disallow all abilities on everything.
     *
     * Grants or forbids universal permissions across all abilities and all models.
     * This is the most permissive assignment possible and should be used carefully,
     * typically only for super-admin roles.
     *
     * @param  array<string, mixed> $attributes Additional attributes for the ability records
     * @return mixed                Result of the underlying to() method
     */
    public function everything(array $attributes = [])
    {
        return $this->to('*', '*', $attributes);
    }

    /**
     * Allow/disallow all abilities on the given model(s).
     *
     * Grants or forbids full management permissions on specific model types.
     * Supports both single models and arrays of models for bulk operations.
     * Commonly used for resource-scoped admin roles.
     *
     * @param array<int, Model|string>|Model|string $models     Model class name(s) or instance(s) to grant management of
     * @param array<string, mixed>                  $attributes Additional attributes for the ability records
     */
    public function toManage($models, array $attributes = []): void
    {
        if (is_array($models)) {
            foreach ($models as $model) {
                $this->to('*', $model, $attributes);
            }
        } else {
            $this->to('*', $models, $attributes);
        }
    }

    /**
     * Allow/disallow owning the given model.
     *
     * Returns a lazy ownership conductor for setting up ownership-based permissions.
     * Enables abilities that only apply to resources owned by the authority,
     * useful for user-specific data like "edit own posts".
     *
     * @param  Model|string         $model      Model class name or instance to set ownership abilities for
     * @param  array<string, mixed> $attributes Additional attributes for the ability records
     * @return LazyHandlesOwnership Lazy conductor for chaining ownership ability definitions
     */
    public function toOwn(Model|string $model, array $attributes = []): LazyHandlesOwnership
    {
        return new LazyHandlesOwnership($this, $model, $attributes);
    }

    /**
     * Allow/disallow owning all models.
     *
     * Convenience method for granting ownership-based permissions across all model
     * types. Useful for scenarios where users can manage their own resources
     * regardless of the resource type.
     *
     * @param  array<string, mixed> $attributes Additional attributes for the ability records
     * @return LazyHandlesOwnership Lazy conductor for defining ownership abilities
     */
    public function toOwnEverything(array $attributes = [])
    {
        return $this->toOwn('*', $attributes);
    }

    /**
     * Determine whether a call to "to" with the given parameters should be conducted lazily.
     *
     * Returns true only when a single parameter is passed that is either a string, BackedEnum,
     * or a numerically-indexed array of strings/BackedEnums. This enables the fluent API pattern
     * where simple ability names can be chained with further specifications.
     *
     * @param  mixed $abilities The ability parameter(s) being passed to to()
     * @return bool  True if lazy conductor should be created, false for immediate execution
     */
    protected function shouldConductLazy($abilities)
    {
        // We'll only create a lazy conductor if we got a single
        // param, and that single param is either a string or
        // a numerically-indexed array (of simple strings).
        if (func_num_args() > 1) {
            return false;
        }

        if (is_string($abilities) || $abilities instanceof BackedEnum) {
            return true;
        }

        if (!is_array($abilities) || !Helpers::isIndexedArray($abilities)) {
            return false;
        }

        return new Collection($abilities)->every(fn ($item): bool => is_string($item) || $item instanceof BackedEnum);
    }

    /**
     * Create a lazy abilities conductor.
     *
     * Wraps the abilities in a lazy conductor that defers execution until
     * additional chained method calls provide complete context for the operation.
     *
     * @param  array<mixed>|BackedEnum|int|Model|string $abilities Ability identifier(s) to be conducted lazily
     * @return LazyConductsAbilities                    Lazy conductor for method chaining
     */
    protected function conductLazy(array|BackedEnum|Model|int|string $abilities): LazyConductsAbilities
    {
        return new LazyConductsAbilities($this, $abilities);
    }
}
