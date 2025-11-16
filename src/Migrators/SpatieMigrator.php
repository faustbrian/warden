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
use stdClass;

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

        /** @var stdClass $role */
        foreach ($roles as $role) {
            /** @var string $name */
            $name = $role->name;

            /** @var string $guardName */
            $guardName = $role->guard_name;

            Models::role()->firstOrCreate(
                ['name' => $name, 'guard_name' => $guardName],
                ['title' => $name, 'guard_name' => $guardName],
            );

            Log::channel($this->logChannel)->debug('Created role: '.$name);
        }
    }

    private function migrateModelRoles(): void
    {
        Log::channel($this->logChannel)->debug('Migrating user role assignments...');

        $assignments = DB::table($this->getTableName('model_has_roles'))
            ->where('model_type', $this->getModelType())
            ->get();

        /** @var stdClass $assignment */
        foreach ($assignments as $assignment) {
            /** @var int $modelId */
            $modelId = $assignment->model_id;
            $user = $this->findUser($modelId);

            if (!$user instanceof Model) {
                Log::channel($this->logChannel)->debug('User not found: '.$modelId);

                continue;
            }

            /** @var int|string $roleId */
            $roleId = $assignment->role_id;

            /** @var null|stdClass $role */
            $role = DB::table($this->getTableName('roles'))->find($roleId);

            if (!$role) {
                Log::channel($this->logChannel)->debug('Role not found: '.$roleId);

                continue;
            }

            /** @var string $roleName */
            $roleName = $role->name;

            /** @var string $guardName */
            $guardName = $role->guard_name;

            /** @var string $userIdentifier */
            /** @phpstan-ignore-next-line cast.string (migration code - user email/key assumed castable) */
            $userIdentifier = (string) ($user->email ?? $user->getKey());

            Log::channel($this->logChannel)->debug(sprintf("Assigning role '%s' (guard: %s) to user: %s", $roleName, $guardName, $userIdentifier));

            $result = Warden::guard($guardName)->assign($roleName)->to($user);

            Log::channel($this->logChannel)->debug(sprintf('Assignment result: %s', $result ? 'success' : 'failed'));
            Log::channel($this->logChannel)->debug(sprintf("Assigned role '%s' to user: %s", $roleName, $userIdentifier));
        }
    }

    private function migrateModelPermissions(): void
    {
        Log::channel($this->logChannel)->debug('Migrating direct user permissions...');

        $assignments = DB::table($this->getTableName('model_has_permissions'))
            ->where('model_type', $this->getModelType())
            ->get();

        /** @var stdClass $assignment */
        foreach ($assignments as $assignment) {
            /** @var int $modelId */
            $modelId = $assignment->model_id;
            $user = $this->findUser($modelId);

            if (!$user instanceof Model) {
                Log::channel($this->logChannel)->debug('User not found: '.$modelId);

                continue;
            }

            /** @var int|string $permissionId */
            $permissionId = $assignment->permission_id;

            /** @var null|stdClass $permission */
            $permission = DB::table($this->getTableName('permissions'))->find($permissionId);

            if (!$permission) {
                Log::channel($this->logChannel)->debug('Permission not found: '.$permissionId);

                continue;
            }

            /** @var string $guardName */
            $guardName = $permission->guard_name;

            /** @var string $permissionName */
            $permissionName = $permission->name;

            /** @var string $userIdentifier */
            /** @phpstan-ignore-next-line cast.string (migration code - user email/key assumed castable) */
            $userIdentifier = (string) ($user->email ?? $user->getKey());

            Warden::guard($guardName)->allow($user)->to($permissionName);
            Log::channel($this->logChannel)->debug(sprintf("Granted permission '%s' to user: %s", $permissionName, $userIdentifier));
        }
    }

    private function migrateRolePermissions(): void
    {
        Log::channel($this->logChannel)->debug('Migrating role permissions...');

        $assignments = DB::table($this->getTableName('role_has_permissions'))->get();

        /** @var stdClass $assignment */
        foreach ($assignments as $assignment) {
            /** @var int|string $roleId */
            $roleId = $assignment->role_id;

            /** @var null|stdClass $role */
            $role = DB::table($this->getTableName('roles'))->find($roleId);

            if (!$role) {
                Log::channel($this->logChannel)->debug('Role not found: '.$roleId);

                continue;
            }

            /** @var int|string $permissionId */
            $permissionId = $assignment->permission_id;

            /** @var null|stdClass $permission */
            $permission = DB::table($this->getTableName('permissions'))->find($permissionId);

            if (!$permission) {
                Log::channel($this->logChannel)->debug('Permission not found: '.$permissionId);

                continue;
            }

            /** @var string $guardName */
            $guardName = $permission->guard_name;

            /** @var string $permissionName */
            $permissionName = $permission->name;

            /** @var string $roleName */
            $roleName = $role->name;

            Warden::guard($guardName)->allow($roleName)->to($permissionName);
            Log::channel($this->logChannel)->debug(sprintf("Granted permission '%s' to role: %s", $permissionName, $roleName));
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
        $keyColumn = Models::getModelKeyFromClass($model);

        if (in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
            /** @phpstan-ignore-next-line method.nonObject (static call on class-string) */
            return $model::withTrashed()->where($keyColumn, $userId)->first();
        }

        /** @phpstan-ignore-next-line method.nonObject (static call on class-string) */
        return $model::where($keyColumn, $userId)->first();
    }
}
