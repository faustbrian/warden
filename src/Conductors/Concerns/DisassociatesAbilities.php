<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Conductors\Concerns;

use Cline\Warden\Database\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Query\Builder;

use function func_get_args;
use function property_exists;

/**
 * Trait for disassociating (revoking) abilities from authorities.
 *
 * Handles removal of ability permissions from authorities or from everyone.
 * Deletes permission pivot records matching the specified criteria while
 * respecting scope constraints for multi-tenancy isolation.
 *
 * @see     AssociatesAbilities For granting abilities
 *
 * @author  Brian Faust <brian@cline.sh>
 */
trait DisassociatesAbilities
{
    use ConductsAbilities;
    use FindsAndCreatesAbilities;

    /**
     * Remove the given ability from the authority.
     *
     * Revokes the specified abilities from the authority, optionally scoped to
     * a model type or instance. Supports lazy conductor pattern when only
     * ability names are provided.
     *
     * @param  array<int|string, mixed>|int|Model|string            $abilities  Ability identifier(s) to revoke
     * @param  null|Model|string                                    $entity     Optional model to scope revocation to
     * @param  array<string, mixed>                                 $attributes Additional criteria for ability lookup
     * @return bool|\Cline\Warden\Conductors\Lazy\ConductsAbilities True on success, or lazy conductor if applicable
     */
    public function to($abilities, $entity = null, array $attributes = [])
    {
        if ($this->shouldConductLazy(...func_get_args())) {
            return $this->conductLazy($abilities);
        }

        if ($ids = $this->getAbilityIds($abilities, $entity, $attributes)) {
            $this->disassociateAbilities($this->getAuthority(), $ids);
        }

        return true;
    }

    /**
     * Detach the given ability IDs from the authority.
     *
     * Routes disassociation to either authority-specific or global removal
     * based on whether an authority model is provided.
     *
     * @param null|Model      $authority Authority model to disassociate from, or null for everyone
     * @param array<int, int> $ids       Ability IDs to disassociate
     */
    protected function disassociateAbilities($authority, array $ids): void
    {
        if (null === $authority) {
            $this->disassociateEveryone($ids);
        } else {
            $this->disassociateAuthority($authority, $ids);
        }
    }

    /**
     * Disassociate the authority from abilities with the given IDs.
     *
     * Deletes pivot records matching the authority, ability IDs, and any
     * additional constraints (like forbidden flag) set by the conductor.
     *
     * @param Model           $authority Authority model to disassociate abilities from
     * @param array<int, int> $ids       Ability IDs to remove
     */
    protected function disassociateAuthority(Model $authority, array $ids): void
    {
        $this->getAbilitiesPivotQuery($authority, $ids)
            ->where($this->constraints())
            ->delete();
    }

    /**
     * Get the base abilities pivot query for an authority.
     *
     * Constructs a query targeting the permissions pivot table filtered by
     * authority identity, morph type, and ability IDs. Applies scope constraints
     * for multi-tenancy support.
     *
     * @param  Model           $model The authority model to query pivot records for (must use HasAbilities trait)
     * @param  array<int, int> $ids   Ability IDs to include in the query
     * @return Builder         Pivot table query builder
     */
    protected function getAbilitiesPivotQuery(Model $model, $ids): Builder
    {
        /** @var MorphToMany<Model, Model> $relation */
        // @phpstan-ignore-next-line method.notFound - abilities() exists via HasAbilities trait
        $relation = $model->abilities();

        $query = $relation
            ->newPivotStatement()
            ->where($relation->getQualifiedForeignPivotKeyName(), $model->getAttribute(Models::getModelKey($model)))
            ->where('actor_type', $model->getMorphClass())
            ->whereIn($relation->getQualifiedRelatedPivotKeyName(), $ids);

        /** @var Builder */
        return Models::scope()->applyToRelationQuery(
            $query,
            $relation->getTable(),
        );
    }

    /**
     * Disassociate everyone from the abilities with the given IDs.
     *
     * Removes global permission records (null actor_id) matching the ability IDs
     * and any additional constraints. Applies scope for multi-tenancy isolation.
     *
     * @param array<int, int> $ids Ability IDs to remove from global permissions
     */
    protected function disassociateEveryone(array $ids): void
    {
        $query = Models::query('permissions')
            ->whereNull('actor_id')
            ->where($this->constraints())
            ->whereIn('ability_id', $ids);

        // @phpstan-ignore-next-line - $query->from can be Expression|string
        Models::scope()->applyToRelationQuery($query, $query->from);

        $query->delete();
    }

    /**
     * Get the authority from which to disassociate the abilities.
     *
     * When authority is a string (role name), finds the role model. Returns null
     * for global disassociations. Throws exception if role doesn't exist.
     *
     * @throws ModelNotFoundException If role name not found
     *
     * @return null|Model The authority model, or null for global disassociation
     */
    protected function getAuthority()
    {
        if (null === $this->authority) {
            return null;
        }

        if ($this->authority instanceof Model) {
            return $this->authority;
        }

        // @phpstan-ignore-next-line - Role model has where() and firstOrFail() via query builder
        return Models::role()
            ->where('name', $this->authority)
            ->where('guard_name', $this->guardName)
            ->firstOrFail();
    }

    /**
     * Get additional constraints for the detaching query.
     *
     * Returns constraints array if defined on the conductor (e.g., forbidden flag),
     * otherwise returns empty array for unconditional disassociation.
     *
     * @return array<string, mixed> Additional where clauses for the delete query
     */
    protected function constraints()
    {
        // @phpstan-ignore-next-line - property_exists with $this is always true but needed for runtime check
        return property_exists($this, 'constraints') ? $this->constraints : [];
    }
}
