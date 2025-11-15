<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Migrators;

use Cline\Warden\Contracts\MigratorInterface;
use Cline\Warden\Database\Models;
use Cline\Warden\Facades\Warden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use function assert;
use function class_uses_recursive;
use function config;
use function in_array;
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
            $user = $this->findUser($assignment->model_id);

            if (!$user instanceof Model) {
                Log::channel($this->logChannel)->debug('User not found: '.$assignment->model_id);

                continue;
            }

            $role = DB::table($this->getTableName('roles'))->find($assignment->role_id);

            if (!$role) {
                Log::channel($this->logChannel)->debug('Role not found: '.$assignment->role_id);

                continue;
            }

            Warden::guard($role->guard_name)->assign($role->name)->to($user);
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
            $user = $this->findUser($assignment->model_id);

            if (!$user instanceof Model) {
                Log::channel($this->logChannel)->debug('User not found: '.$assignment->model_id);

                continue;
            }

            $permission = DB::table($this->getTableName('permissions'))->find($assignment->permission_id);

            if (!$permission) {
                Log::channel($this->logChannel)->debug('Permission not found: '.$assignment->permission_id);

                continue;
            }

            Warden::guard($permission->guard_name)->allow($user)->to($permission->name);
            Log::channel($this->logChannel)->debug(sprintf("Granted permission '%s' to user: %s", $permission->name, $user->email ?? $user->getKey()));
        }
    }

    private function migrateRolePermissions(): void
    {
        Log::channel($this->logChannel)->debug('Migrating role permissions...');

        $assignments = DB::table($this->getTableName('role_has_permissions'))->get();

        foreach ($assignments as $assignment) {
            $role = DB::table($this->getTableName('roles'))->find($assignment->role_id);

            if (!$role) {
                Log::channel($this->logChannel)->debug('Role not found: '.$assignment->role_id);

                continue;
            }

            $permission = DB::table($this->getTableName('permissions'))->find($assignment->permission_id);

            if (!$permission) {
                Log::channel($this->logChannel)->debug('Permission not found: '.$assignment->permission_id);

                continue;
            }

            Warden::guard($permission->guard_name)->allow(Warden::role($role->name))->to($permission->name);
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

    private function findUser(mixed $userId): ?Model
    {
        $model = $this->userModel;

        if (in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
            /** @phpstan-ignore-next-line method.nonObject (static call on class-string) */
            return $model::withTrashed()->find($userId);
        }

        /** @phpstan-ignore-next-line method.nonObject (static call on class-string) */
        return $model::find($userId);
    }
}
