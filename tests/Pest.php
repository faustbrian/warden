<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Database\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

pest()->extend(TestCase::class)->in(__DIR__);

function role(string $name)
{
    return Role::query()->create(['name' => $name]);
}

/**
 * Get the morph key value for a model (UUID/ULID in UUID mode, id otherwise).
 */
function getActorId(Model $model): mixed
{
    $actorMorphType = config('warden.actor_morph_type', 'morph');

    return match ($actorMorphType) {
        'uuidMorph' => $model->uuid,
        'ulidMorph' => $model->ulid,
        default => $model->id,
    };
}

/**
 * Insert data into a table while bypassing foreign key constraint checks.
 *
 * This helper is needed for test scenarios where we intentionally insert data
 * with non-existent foreign key references to test error handling in migrators.
 * Different databases require different approaches to disable FK constraints.
 */
function insertWithoutForeignKeyChecks(string $table, array $data): void
{
    $driver = DB::connection()->getDriverName();

    if ($driver === 'pgsql') {
        DB::statement(sprintf('ALTER TABLE %s DISABLE TRIGGER ALL', $table));
        DB::table($table)->insert($data);
        DB::statement(sprintf('ALTER TABLE %s ENABLE TRIGGER ALL', $table));
    } elseif ($driver === 'mysql') {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table($table)->insert($data);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    } else {
        DB::table($table)->insert($data);
    }
}
