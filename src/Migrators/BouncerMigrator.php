<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Migrators;

use Cline\Warden\Conductors\AssignsRoles;
use Cline\Warden\Conductors\ForbidsAbilities;
use Cline\Warden\Conductors\GivesAbilities;
use Cline\Warden\Contracts\MigratorInterface;
use Cline\Warden\Database\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use function assert;
use function class_uses_recursive;
use function config;
use function in_array;
use function is_int;
use function is_string;
use function json_decode;
use function sprintf;

/**
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class BouncerMigrator implements MigratorInterface
{
    /**
     * The guard name for migrated roles and abilities.
     */
    private string $guardName;

    public function __construct(
        private string $userModel,
        private string $logChannel = 'migration',
        ?string $guardName = null,
    ) {
        $guard = $guardName ?? config('warden.guard', 'web');
        assert(is_string($guard));
        $this->guardName = $guard;
    }

    public function migrate(): void
    {
        Log::channel($this->logChannel)->debug('Starting migration from Bouncer to Warden...');

        $this->migrateRoles();
        $this->migrateAbilities();
        $this->migrateModelRoles();
        $this->migrateModelPermissions();
        $this->migrateRolePermissions();

        Log::channel($this->logChannel)->debug('Migration completed successfully!');
    }

    private function migrateRoles(): void
    {
        Log::channel($this->logChannel)->debug('Migrating roles...');

        $roles = DB::table($this->getTableName('roles'))->get();

        foreach ($roles as $role) {
            assert(is_string($role->name));

            Models::role()->firstOrCreate(
                ['name' => $role->name, 'guard_name' => $this->guardName],
                ['title' => $role->title ?? $role->name, 'guard_name' => $this->guardName],
            );

            Log::channel($this->logChannel)->debug('Created role: '.$role->name);
        }
    }

    private function migrateAbilities(): void
    {
        Log::channel($this->logChannel)->debug('Migrating abilities...');

        $abilities = DB::table($this->getTableName('abilities'))->get();

        foreach ($abilities as $ability) {
            assert(is_string($ability->name));

            $abilityModel = Models::ability()->updateOrCreate(
                ['name' => $ability->name],
                [
                    'title' => $ability->title ?? $ability->name,
                    'guard_name' => $this->guardName,
                ],
            );

            // Set non-fillable attributes after creation
            /** @phpstan-ignore-next-line assign.propertyType (stdClass property from DB query) */
            $abilityModel->only_owned = $ability->only_owned ?? false;

            /** @phpstan-ignore-next-line assign.propertyType (stdClass property from DB query) */
            $abilityModel->subject_id = $ability->entity_id;

            /** @phpstan-ignore-next-line assign.propertyType (stdClass property from DB query) */
            $abilityModel->subject_type = $ability->entity_type;

            // Handle options - convert JSON string to array if present
            if ($ability->options) {
                /** @phpstan-ignore-next-line cast.string,assign.propertyType (stdClass property from DB query) */
                $abilityModel->options = json_decode((string) $ability->options, true);
            }

            $abilityModel->save();

            Log::channel($this->logChannel)->debug('Created ability: '.$ability->name);
        }
    }

    private function migrateModelRoles(): void
    {
        Log::channel($this->logChannel)->debug('Migrating user role assignments...');

        $assignments = DB::table($this->getTableName('assigned_roles'))
            ->where('entity_type', $this->getEntityType())
            ->get();

        foreach ($assignments as $assignment) {
            assert(is_int($assignment->entity_id) && is_int($assignment->role_id));

            $user = $this->findUser($assignment->entity_id);

            if (!$user instanceof Model) {
                Log::channel($this->logChannel)->debug('User not found: '.$assignment->entity_id);

                continue;
            }

            $role = DB::table($this->getTableName('roles'))->find($assignment->role_id);

            if ($role === null) {
                Log::channel($this->logChannel)->debug('Role not found: '.$assignment->role_id);

                continue;
            }

            /** @phpstan-ignore-next-line property.nonObject (stdClass from DB query) */
            assert(is_string($role->name));

            /** @phpstan-ignore-next-line property.nonObject (stdClass from DB query) */
            new AssignsRoles($role->name, $this->guardName)->to($user);

            /** @phpstan-ignore-next-line property.nonObject,property.notFound (stdClass from DB query, Model may not have email) */
            Log::channel($this->logChannel)->debug(sprintf("Assigned role '%s' to user: %s", $role->name, $user->email ?? $user->getKey()));
        }
    }

    private function migrateModelPermissions(): void
    {
        Log::channel($this->logChannel)->debug('Migrating direct user permissions...');

        $permissions = DB::table($this->getTableName('permissions'))
            ->where('entity_type', $this->getEntityType())
            ->get();

        foreach ($permissions as $permission) {
            assert(is_int($permission->entity_id) && is_int($permission->ability_id));

            $user = $this->findUser($permission->entity_id);

            if (!$user instanceof Model) {
                Log::channel($this->logChannel)->debug('User not found: '.$permission->entity_id);

                continue;
            }

            $ability = DB::table($this->getTableName('abilities'))->find($permission->ability_id);

            if ($ability === null) {
                Log::channel($this->logChannel)->debug('Ability not found: '.$permission->ability_id);

                continue;
            }

            /** @phpstan-ignore-next-line property.nonObject (stdClass from DB query) */
            assert(is_string($ability->name));

            if ($permission->forbidden) {
                /** @phpstan-ignore-next-line property.nonObject (stdClass from DB query) */
                new ForbidsAbilities($user, $this->guardName)->to($ability->name);

                /** @phpstan-ignore-next-line property.nonObject,property.notFound (stdClass from DB query, Model may not have email) */
                Log::channel($this->logChannel)->debug(sprintf("Forbidden permission '%s' for user: %s", $ability->name, $user->email ?? $user->getKey()));
            } else {
                /** @phpstan-ignore-next-line property.nonObject (stdClass from DB query) */
                new GivesAbilities($user, $this->guardName)->to($ability->name);

                /** @phpstan-ignore-next-line property.nonObject,property.notFound (stdClass from DB query, Model may not have email) */
                Log::channel($this->logChannel)->debug(sprintf("Granted permission '%s' to user: %s", $ability->name, $user->email ?? $user->getKey()));
            }
        }
    }

    private function migrateRolePermissions(): void
    {
        Log::channel($this->logChannel)->debug('Migrating role permissions...');

        $permissions = DB::table($this->getTableName('permissions'))
            ->whereNull('entity_type')
            ->get();

        foreach ($permissions as $permission) {
            assert(is_int($permission->ability_id) && is_int($permission->entity_id));

            $ability = DB::table($this->getTableName('abilities'))->find($permission->ability_id);

            if ($ability === null) {
                // @codeCoverageIgnoreStart
                Log::channel($this->logChannel)->debug('Ability not found: '.$permission->ability_id);

                continue;
                // @codeCoverageIgnoreEnd
            }

            /** @phpstan-ignore-next-line property.nonObject (stdClass from DB query) */
            assert(is_string($ability->name));

            $role = DB::table($this->getTableName('roles'))->find($permission->entity_id);

            if ($role === null) {
                // @codeCoverageIgnoreStart
                Log::channel($this->logChannel)->debug('Role not found: '.$permission->entity_id);

                continue;
                // @codeCoverageIgnoreEnd
            }

            /** @phpstan-ignore-next-line property.nonObject (stdClass from DB query) */
            assert(is_string($role->name));

            /** @phpstan-ignore-next-line property.nonObject (stdClass from DB query) */
            $roleModel = Models::role()->where('name', $role->name)->where('guard_name', $this->guardName)->first();

            if (!$roleModel instanceof Model) {
                // @codeCoverageIgnoreStart
                continue;
                // @codeCoverageIgnoreEnd
            }

            if ($permission->forbidden) {
                /** @phpstan-ignore-next-line property.nonObject (stdClass from DB query) */
                new ForbidsAbilities($roleModel, $this->guardName)->to($ability->name);

                /** @phpstan-ignore-next-line property.nonObject (stdClass from DB query) */
                Log::channel($this->logChannel)->debug(sprintf("Forbidden permission '%s' for role: %s", $ability->name, $role->name));
            } else {
                /** @phpstan-ignore-next-line property.nonObject (stdClass from DB query) */
                new GivesAbilities($roleModel, $this->guardName)->to($ability->name);

                /** @phpstan-ignore-next-line property.nonObject (stdClass from DB query) */
                Log::channel($this->logChannel)->debug(sprintf("Granted permission '%s' to role: %s", $ability->name, $role->name));
            }
        }
    }

    private function getTableName(string $table): string
    {
        $tableName = config('warden.migrators.bouncer.tables.'.$table);
        assert(is_string($tableName));

        return $tableName;
    }

    private function getEntityType(): string
    {
        $entityType = config('warden.migrators.bouncer.entity_type', $this->userModel);
        assert(is_string($entityType));

        return $entityType;
    }

    private function findUser(int $userId): ?Model
    {
        $model = $this->userModel;

        // @codeCoverageIgnoreStart
        if (in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
            /** @phpstan-ignore-next-line method.nonObject (static call on class-string) */
            $result = $model::withTrashed()->find($userId);
            assert($result === null || $result instanceof Model);

            return $result;
        }

        /** @codeCoverageIgnoreEnd */
        $result = $model::find($userId);
        assert($result === null || $result instanceof Model);

        return $result;
    }
}
