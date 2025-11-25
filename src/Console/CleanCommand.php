<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Console;

use Cline\Warden\Database\Ability;
use Cline\Warden\Database\Models;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

use function assert;
use function sprintf;

/**
 * Artisan command to clean up unused abilities.
 *
 * This maintenance command removes ability records that are no longer useful:
 * unassigned abilities (not granted to any authority) and orphaned abilities
 * (referencing deleted models). This helps maintain database hygiene and
 * prevents accumulation of stale permission records.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class CleanCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'warden:clean
                            {--u|unassigned : Whether to delete abilities not assigned to anyone}
                            {--o|orphaned : Whether to delete abilities for missing models}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete abilities that are no longer in use';

    /**
     * Execute the console command.
     *
     * Performs cleanup operations based on the provided options. If no specific
     * options are given, both unassigned and orphaned abilities are removed by default.
     */
    public function handle(): void
    {
        [$unassigned, $orphaned] = $this->getComputedOptions();

        if ($unassigned) {
            $this->deleteUnassignedAbilities();
        }

        if ($orphaned) {
            $this->deleteOrphanedAbilities();
        }
    }

    /**
     * Get the options to use, computed by omission.
     *
     * When no specific options are provided, defaults to enabling both cleanup
     * operations. This provides a safe default where running the command without
     * flags performs a comprehensive cleanup.
     *
     * @return array{bool, bool} array containing [unassigned flag, orphaned flag]
     */
    private function getComputedOptions(): array
    {
        $unassigned = (bool) $this->option('unassigned');
        $orphaned = (bool) $this->option('orphaned');

        if (!$unassigned && !$orphaned) {
            $unassigned = true;
            $orphaned = true;
        }

        return [$unassigned, $orphaned];
    }

    /**
     * Delete abilities not assigned to anyone.
     *
     * Removes ability records that exist in the abilities table but have no
     * corresponding entries in the permissions pivot table. These are abilities
     * that were created but never granted to any authority, or were granted but
     * have since had all their assignments removed.
     */
    private function deleteUnassignedAbilities(): void
    {
        $query = $this->getUnassignedAbilitiesQuery();

        if (($count = $query->count()) > 0) {
            $query->delete();

            $this->info(sprintf('Deleted %s unassigned ', $count).Str::plural('ability', $count).'.');
        } else {
            $this->info('No unassigned abilities.');
        }
    }

    /**
     * Get the base query for all unassigned abilities.
     *
     * Constructs a query that identifies abilities without any permission assignments.
     * Uses a subquery to find abilities whose IDs don't appear in the permissions table.
     *
     * @return Builder<Ability> Eloquent query builder for unassigned abilities
     */
    private function getUnassignedAbilitiesQuery(): Builder
    {
        $model = Models::ability();

        return $model::query()->whereNotIn($model->getKeyName(), function (QueryBuilder $query): void {
            $query->from(Models::table('permissions'))->select('ability_id');
        });
    }

    /**
     * Delete model abilities whose models have been deleted.
     *
     * Removes ability records that reference specific model instances (subject_id/subject_type)
     * where the referenced model no longer exists in the database. This cleanup prevents
     * accumulation of stale permissions for deleted resources.
     */
    private function deleteOrphanedAbilities(): void
    {
        $query = $this->getBaseOrphanedQuery()->where(function (Builder $query): void {
            foreach ($this->getEntityTypes() as $entityType) {
                $query->orWhere(function (Builder $query) use ($entityType): void {
                    $this->scopeQueryToWhereModelIsMissing($query, $entityType);
                });
            }
        });

        if (($count = $query->count()) > 0) {
            $query->delete();

            $this->info(sprintf('Deleted %d orphaned ', $count).Str::plural('ability', $count).'.');
        } else {
            $this->info('No orphaned abilities.');
        }
    }

    /**
     * Scope the given query to where the ability's model is missing.
     *
     * Adds conditions to find abilities for a specific entity type where the
     * referenced subject_id no longer exists in the entity's table. This identifies
     * orphaned abilities that reference deleted model instances.
     *
     * @param Builder<Ability> $query      The query builder instance to apply conditions to
     * @param string           $entityType The morph type of the entity to check for orphans
     */
    private function scopeQueryToWhereModelIsMissing(Builder $query, string $entityType): void
    {
        $model = $this->makeModel($entityType);
        $abilities = $this->abilitiesTable();

        $query->where($abilities.'.subject_type', $entityType);

        $query->whereNotIn($abilities.'.subject_id', function (QueryBuilder $query) use ($model): void {
            $table = $model->getTable();
            $keyColumn = Models::getModelKey($model);

            $query->from($table)->select($table.'.'.$keyColumn);
        });
    }

    /**
     * Get the entity types of all model abilities.
     *
     * Retrieves the distinct subject_type values from abilities that reference
     * specific models (not global abilities). These types are used to determine
     * which model classes to check for orphaned records.
     *
     * @return Collection<int, string>
     */
    private function getEntityTypes(): Collection
    {
        /** @var Collection<int, string> */
        return $this
            ->getBaseOrphanedQuery()
            ->distinct()
            ->pluck('subject_type');
    }

    /**
     * Get the base query for abilities with missing models.
     *
     * Returns a query builder for abilities that reference specific model instances
     * (have non-null subject_id and subject_type != '*'). These are the abilities that
     * could potentially be orphaned if their referenced models are deleted.
     *
     * @return Builder<Ability> Eloquent query builder for potentially orphaned abilities
     */
    private function getBaseOrphanedQuery(): Builder
    {
        $table = $this->abilitiesTable();

        return Models::ability()::query()
            ->whereNotNull($table.'.subject_id')
            ->where($table.'.subject_type', '!=', '*');
    }

    /**
     * Get the name of the abilities table.
     *
     * @return string The abilities table name from the model
     */
    private function abilitiesTable(): string
    {
        return Models::ability()->getTable();
    }

    /**
     * Get an instance of the model for the given entity type.
     *
     * Resolves the entity type (morph alias or class name) to an actual model instance.
     * Uses Laravel's morph map to handle aliased entity types, falling back to treating
     * the entity type as a direct class name if no alias is registered.
     *
     * @param  string $entityType The morph type or fully-qualified class name to instantiate
     * @return Model  Fresh model instance for the entity type
     */
    private function makeModel(string $entityType): Model
    {
        $class = Relation::getMorphedModel($entityType) ?? $entityType;

        $model = new $class();
        assert($model instanceof Model);

        return $model;
    }
}
