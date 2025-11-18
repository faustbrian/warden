<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Conductors\Concerns;

use Cline\Warden\Database\Models;
use Cline\Warden\Database\Permission;
use Cline\Warden\Support\PrimaryKeyGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

use function array_diff;
use function func_get_args;

/**
 * Trait for associating (allowing) abilities with authorities.
 *
 * Handles the process of granting abilities to authorities (users or roles)
 * or to everyone. Creates ability records if they don't exist, manages
 * polymorphic permission relationships, and avoids duplicate associations.
 *
 * @see     DisassociatesAbilities For revoking abilities
 * @see     \Cline\Warden\Conductors\ForbidsAbilities For denying abilities
 *
 * @author  Brian Faust <brian@cline.sh>
 */
trait AssociatesAbilities
{
    use ConductsAbilities;
    use FindsAndCreatesAbilities;

    /**
     * Associate the abilities with the authority.
     *
     * Grants the specified abilities to the authority, optionally scoped to
     * a model type or instance. Supports lazy conductor pattern when only
     * ability names are provided, enabling chained method calls.
     *
     * @param  array<int|Model|string>|int|Model|string             $abilities  Ability identifier(s) to grant
     * @param  null|Model|string                                    $model      Optional model to scope abilities to
     * @param  array<string, mixed>                                 $attributes Additional attributes for ability records
     * @return null|\Cline\Warden\Conductors\Lazy\ConductsAbilities Lazy conductor if applicable, otherwise null
     */
    public function to($abilities, $model = null, array $attributes = [])
    {
        if ($this->shouldConductLazy(...func_get_args())) {
            return $this->conductLazy($abilities);
        }

        $ids = $this->getAbilityIds($abilities, $model, $attributes);

        $this->associateAbilities($ids, $this->getAuthority());

        return null;
    }

    /**
     * Get the authority, creating a role authority if necessary.
     *
     * When authority is a string (role name), finds or creates the role model.
     * Returns null for global ability assignments (to everyone).
     *
     * @return null|Model The authority model, or null for global assignments
     */
    protected function getAuthority(): ?Model
    {
        if (null === $this->authority) {
            return null;
        }

        if ($this->authority instanceof Model) {
            return $this->authority;
        }

        /** @phpstan-ignore-next-line Dynamic method call on Eloquent model */
        return Models::role()->firstOrCreate([
            'name' => $this->authority,
            'guard_name' => $this->guardName,
        ]);
    }

    /**
     * Get the IDs of abilities already associated with the authority.
     *
     * Queries existing ability associations to prevent duplicate pivot entries.
     * Handles both authority-specific associations and global (everyone) associations.
     *
     * @param  null|Model      $authority  The authority to check associations for, or null for everyone
     * @param  array<int, int> $abilityIds Ability IDs to check for existing associations
     * @return array<int, int> Array of ability IDs that are already associated
     */
    protected function getAssociatedAbilityIds($authority, array $abilityIds): array
    {
        if (null === $authority) {
            return $this->getAbilityIdsAssociatedWithEveryone($abilityIds);
        }

        /** @phpstan-ignore-next-line Dynamic abilities() relationship */
        $relation = $authority->abilities();

        $table = Models::table('abilities');

        /** @phpstan-ignore-next-line Dynamic relation methods */
        $relation->whereIn($table.'.id', $abilityIds)
            ->wherePivot('forbidden', '=', $this->forbidding);

        /** @phpstan-ignore-next-line Dynamic relation type */
        Models::scope()->applyToRelation($relation);

        /** @var array<int, int> */
        /** @phpstan-ignore-next-line Dynamic relation methods */
        return $relation->get([$table.'.id'])->pluck('id')->all();
    }

    /**
     * Get the IDs of abilities associated with everyone globally.
     *
     * Queries permissions table for abilities granted to all users (null actor_id).
     * Respects the forbidden flag to match the current operation context.
     *
     * @param  array<int, int> $abilityIds Ability IDs to check for global associations
     * @return array<int, int> Array of ability IDs that are globally associated
     */
    protected function getAbilityIdsAssociatedWithEveryone(array $abilityIds): array
    {
        $query = Models::query('permissions')
            ->whereNull('actor_id')
            ->whereIn('ability_id', $abilityIds)
            ->where('forbidden', '=', $this->forbidding);

        /** @phpstan-ignore-next-line Expression type may be string */
        Models::scope()->applyToRelationQuery($query, $query->from);

        /** @var array<int, int> */
        return Arr::pluck($query->get(['ability_id']), 'ability_id');
    }

    /**
     * Associate the given ability IDs on the permissions table.
     *
     * Filters out already-associated abilities to avoid duplicates, then creates
     * new permission records. Routes to either authority-specific or global
     * association based on whether an authority model is provided.
     *
     * @param array<int, int> $ids       Ability IDs to associate
     * @param null|Model      $authority Authority model to associate with, or null for everyone
     */
    protected function associateAbilities(array $ids, ?Model $authority = null): void
    {
        $ids = array_diff($ids, $this->getAssociatedAbilityIds($authority, $ids));

        if (!$authority instanceof Model) {
            $this->associateAbilitiesToEveryone($ids);
        } else {
            $this->associateAbilitiesToAuthority($ids, $authority);
        }
    }

    /**
     * Associate these abilities with the given authority via pivot table.
     *
     * Uses Eloquent's attach method to create pivot records with the forbidden
     * flag and scope attributes for multi-tenancy support.
     *
     * @param array<int, int> $ids       Ability IDs to associate
     * @param Model           $authority Authority model to associate abilities with
     */
    protected function associateAbilitiesToAuthority(array $ids, Model $authority): void
    {
        $attributes = Models::scope()->getAttachAttributes($authority::class);

        /** @phpstan-ignore-next-line instanceof.alwaysTrue - Property lacks native type hint */
        if (isset($this->boundary) && $this->boundary instanceof Model) {
            $attributes['boundary_id'] = $this->boundary->getAttribute(Models::getModelKey($this->boundary));
            $attributes['boundary_type'] = $this->boundary->getMorphClass();
        }

        /** @phpstan-ignore-next-line Dynamic abilities() relationship */
        $authority->abilities()->attach(
            PrimaryKeyGenerator::enrichPivotDataForIds(
                $ids,
                ['forbidden' => $this->forbidding] + $attributes,
            ),
        );
    }

    /**
     * Associate these abilities with everyone globally.
     *
     * Creates permission records with null actor_id to grant abilities to all
     * users. Uses Eloquent model creation for proper event handling and ID generation.
     *
     * @param array<int, int> $ids Ability IDs to associate globally
     */
    protected function associateAbilitiesToEveryone(array $ids): void
    {
        $attributes = ['forbidden' => $this->forbidding];

        $attributes += Models::scope()->getAttachAttributes();

        /** @phpstan-ignore-next-line instanceof.alwaysTrue - Property lacks native type hint */
        if (isset($this->boundary) && $this->boundary instanceof Model) {
            $attributes['boundary_id'] = $this->boundary->getAttribute(Models::getModelKey($this->boundary));
            $attributes['boundary_type'] = $this->boundary->getMorphClass();
        }

        foreach ($ids as $id) {
            Permission::query()->firstOrCreate(
                ['ability_id' => $id, 'actor_id' => null, 'actor_type' => null] + $attributes,
                ['ability_id' => $id] + $attributes,
            );
        }
    }
}
