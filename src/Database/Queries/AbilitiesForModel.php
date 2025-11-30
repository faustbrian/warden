<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Database\Queries;

use Cline\Warden\Database\Ability;
use Cline\Warden\Database\Models;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

use function assert;
use function is_string;

/**
 * Query builder for filtering abilities scoped to specific models.
 *
 * This class provides query constraint logic for finding abilities that apply
 * to a particular model type and/or instance. Supports both strict mode (exact
 * matches only) and non-strict mode (includes wildcards and blanket abilities).
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class AbilitiesForModel
{
    /**
     * The physical name of the abilities table.
     *
     * Resolved from the Models registry to respect custom table name configurations.
     * Cached during construction to avoid repeated registry lookups.
     */
    private string $table;

    /**
     * Initialize the query builder with the abilities table name.
     *
     * Resolves and caches the physical abilities table name from the Models
     * registry for use in query constraints.
     */
    public function __construct()
    {
        $this->table = Models::table('abilities');
    }

    /**
     * Apply model-specific constraints to an ability query.
     *
     * Filters abilities to those applicable to the given model. In non-strict mode,
     * includes wildcard abilities ('*') and blanket abilities (null subject_id).
     * In strict mode, only exact matches are returned.
     *
     * @param Builder<Ability>|\Illuminate\Database\Query\Builder $query  The query to constrain
     * @param Model|string                                        $model  The model instance or class name to filter by
     * @param bool                                                $strict If true, exclude wildcard and blanket abilities
     */
    public function constrain(Builder|\Illuminate\Database\Query\Builder $query, Model|string $model, bool $strict = false): void
    {
        if ($model === '*') {
            $this->constrainByWildcard($query);

            return;
        }

        $modelInstance = is_string($model) ? new $model() : $model;
        assert($modelInstance instanceof Model);

        $this->constrainByModel($query, $modelInstance, $strict);
    }

    /**
     * Apply wildcard constraint for global abilities.
     *
     * Constrains the query to abilities with subject_type set to '*', which
     * represent global abilities that apply to all entity types.
     *
     * @param Builder<Ability>|\Illuminate\Database\Query\Builder $query The query to constrain
     */
    private function constrainByWildcard(Builder|\Illuminate\Database\Query\Builder $query): void
    {
        $query->where($this->table.'.subject_type', '*');
    }

    /**
     * Apply model-specific constraints with optional wildcard inclusion.
     *
     * In non-strict mode, includes both wildcard abilities and abilities
     * specifically scoped to the model. In strict mode, only includes abilities
     * explicitly scoped to the model type and optionally instance.
     *
     * @param  Builder<Ability>|\Illuminate\Database\Query\Builder $query  The query to constrain
     * @param  Model                                               $model  The model instance to filter by
     * @param  bool                                                $strict If true, exclude wildcard abilities
     * @return Builder<Ability>|\Illuminate\Database\Query\Builder The constrained query
     */
    private function constrainByModel(Builder|\Illuminate\Database\Query\Builder $query, Model $model, bool $strict): Builder|\Illuminate\Database\Query\Builder
    {
        if ($strict) {
            return $query->where(
                $this->modelAbilityConstraint($model, $strict),
            );
        }

        return $query->where(function (Builder|\Illuminate\Database\Query\Builder $query) use ($model, $strict): void {
            $query->where($this->table.'.subject_type', '*')
                ->orWhere($this->modelAbilityConstraint($model, $strict));
        });
    }

    /**
     * Build constraint closure for model-scoped abilities.
     *
     * Creates a closure that filters by the model's morph class and applies
     * appropriate subject_id constraints based on strict mode and model existence.
     *
     * @param  Model   $model  The model to build constraints for
     * @param  bool    $strict Whether to use strict matching rules
     * @return Closure Query constraint closure
     */
    private function modelAbilityConstraint(Model $model, bool $strict): Closure
    {
        return function (Builder|\Illuminate\Database\Query\Builder $query) use ($model, $strict): void {
            $query->where($this->table.'.subject_type', $model->getMorphClass());

            $query->where($this->abilitySubqueryConstraint($model, $strict));
        };
    }

    /**
     * Build subject_id constraint based on model state and strictness.
     *
     * Returns a closure that applies subject_id filtering logic. For non-existent models
     * or non-strict mode, includes blanket abilities (null subject_id). For existing
     * models, optionally includes instance-specific abilities (matching subject_id).
     *
     * @param  Model   $model  The model to build constraints for
     * @param  bool    $strict Whether to exclude blanket abilities for existing models
     * @return Closure Entity ID constraint closure
     */
    private function abilitySubqueryConstraint(Model $model, bool $strict): Closure
    {
        return function (Builder|\Illuminate\Database\Query\Builder $query) use ($model, $strict): void {
            // If the model does not exist, we want to search for blanket abilities
            // that cover all instances of this model. If it does exist, we only
            // want to find blanket abilities if we're not using strict mode.
            if (!$model->exists || !$strict) {
                $query->whereNull($this->table.'.subject_id');
            }

            if ($model->exists) {
                $query->orWhere($this->table.'.subject_id', $model->getAttribute(Models::getModelKey($model)));
            }
        };
    }
}
