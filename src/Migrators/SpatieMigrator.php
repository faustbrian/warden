<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Migrators;

use Cline\Warden\Conductors\AssignsRoles;
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
use function sprintf;

/**
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class SpatieMigrator implements MigratorInterface
{
    public function __construct(
        private string $userModel,
        private string $logChannel = 'migration',
    ) {}

    public function migrate(): void
    {
        Log::channel($this->logChannel)->debug('Starting migration from Spatie Permission to Warden...');

        $this->migrateRoles();
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
            assert(is_string($role->name) && is_string($role->guard_name));

            Models::role()->firstOrCreate(
                ['name' => $role->name, 'guard_name' => $role->guard_name],
                ['title' => $role->name, 'guard_name' => $role->guard_name],
            );

            Log::channel($this->logChannel)->debug('Created role: '.$role->name);
        }
    }

    private function migrateModelRoles(): void
    {
        Log::channel($this->logChannel)->debug('Migrating user role assignments...');

        $assignments = DB::table($this->getTableName('model_has_roles'))
            ->where('model_type', $this->getModelType())
            ->get();

        foreach ($assignments as $assignment) {
            assert(is_int($assignment->model_id) && is_int($assignment->role_id));

            $user = $this->findUser($assignment->model_id);

            if (!$user instanceof Model) {
                Log::channel($this->logChannel)->debug('User not found: '.$assignment->model_id);

                continue;
            }

            $role = DB::table($this->getTableName('roles'))->find($assignment->role_id);

            if ($role === null) {
                Log::channel($this->logChannel)->debug('Role not found: '.$assignment->role_id);

                continue;
            }

            /** @phpstan-ignore-next-line property.nonObject (stdClass from DB query) */
            assert(is_string($role->name) && is_string($role->guard_name));

            /** @phpstan-ignore-next-line property.nonObject (stdClass from DB query) */
            new AssignsRoles($role->name, $role->guard_name)->to($user);

            /** @phpstan-ignore-next-line property.nonObject,property.notFound (stdClass/Model properties from DB query) */
            Log::channel($this->logChannel)->debug(sprintf("Assigned role '%s' to user: %s", $role->name, $user->email ?? $user->getKey()));
        }
    }

    private function migrateModelPermissions(): void
    {
        Log::channel($this->logChannel)->debug('Migrating direct user permissions...');

        $assignments = DB::table($this->getTableName('model_has_permissions'))
            ->where('model_type', $this->getModelType())
            ->get();

        foreach ($assignments as $assignment) {
            assert(is_int($assignment->model_id) && is_int($assignment->permission_id));

            $user = $this->findUser($assignment->model_id);

            if (!$user instanceof Model) {
                // @codeCoverageIgnoreStart
                Log::channel($this->logChannel)->debug('User not found: '.$assignment->model_id);

                continue;
                // @codeCoverageIgnoreEnd
            }

            $permission = DB::table($this->getTableName('permissions'))->find($assignment->permission_id);

            if ($permission === null) {
                Log::channel($this->logChannel)->debug('Permission not found: '.$assignment->permission_id);

                continue;
            }

            /** @phpstan-ignore-next-line property.nonObject (stdClass from DB query) */
            assert(is_string($permission->guard_name) && is_string($permission->name));

            /** @phpstan-ignore-next-line property.nonObject (stdClass from DB query) */
            new GivesAbilities($user, $permission->guard_name)->to($permission->name);

            /** @phpstan-ignore-next-line property.nonObject,property.notFound (stdClass/Model properties from DB query) */
            Log::channel($this->logChannel)->debug(sprintf("Granted permission '%s' to user: %s", $permission->name, $user->email ?? $user->getKey()));
        }
    }

    private function migrateRolePermissions(): void
    {
        Log::channel($this->logChannel)->debug('Migrating role permissions...');

        $assignments = DB::table($this->getTableName('role_has_permissions'))->get();

        foreach ($assignments as $assignment) {
            assert(is_int($assignment->role_id) && is_int($assignment->permission_id));

            $role = DB::table($this->getTableName('roles'))->find($assignment->role_id);

            if ($role === null) {
                Log::channel($this->logChannel)->debug('Role not found: '.$assignment->role_id);

                continue;
            }

            $permission = DB::table($this->getTableName('permissions'))->find($assignment->permission_id);

            if ($permission === null) {
                Log::channel($this->logChannel)->debug('Permission not found: '.$assignment->permission_id);

                continue;
            }

            /** @phpstan-ignore-next-line property.nonObject (stdClass from DB query) */
            assert(is_string($role->name) && is_string($role->guard_name));

            /** @phpstan-ignore-next-line property.nonObject (stdClass from DB query) */
            assert(is_string($permission->guard_name) && is_string($permission->name));

            /** @phpstan-ignore-next-line property.nonObject (stdClass from DB query) */
            $roleModel = Models::role()->where('name', $role->name)->where('guard_name', $role->guard_name)->first();

            /** @phpstan-ignore-next-line property.nonObject (stdClass from DB query) */
            new GivesAbilities($roleModel, $permission->guard_name)->to($permission->name);

            /** @phpstan-ignore-next-line property.nonObject (stdClass from DB query) */
            Log::channel($this->logChannel)->debug(sprintf("Granted permission '%s' to role: %s", $permission->name, $role->name));
        }
    }

    private function getTableName(string $table): string
    {
        $tableName = config('warden.migrators.spatie.tables.'.$table);
        assert(is_string($tableName));

        return $tableName;
    }

    private function getModelType(): string
    {
        $modelType = config('warden.migrators.spatie.model_type', 'user');
        assert(is_string($modelType));

        return $modelType;
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
