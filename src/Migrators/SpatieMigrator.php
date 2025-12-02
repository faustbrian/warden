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
 * Migrates authorization data from Spatie/laravel-permission to Warden.
 *
 * This migrator reads roles, permissions, and assignments from Spatie's database
 * schema and recreates them in Warden's format. Preserves guard-scoped permissions
 * and handles both direct user permissions and role-based permissions. Unlike Bouncer,
 * Spatie's permissions are called "abilities" in Warden.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class SpatieMigrator implements MigratorInterface
{
    /**
     * Create a new Spatie migrator instance.
     *
     * @param string $userModel  Fully-qualified class name of the user model to migrate
     *                           permissions for. Used to locate and restore user permission
     *                           assignments from Spatie's model_has_roles and model_has_permissions tables.
     * @param string $logChannel Laravel log channel name where migration progress and errors
     *                           will be written. Defaults to 'migration' channel. Configure
     *                           this channel in config/logging.php to capture migration logs.
     */
    public function __construct(
        private string $userModel,
        private string $logChannel = 'migration',
    ) {}

    /**
     * Execute the complete migration from Spatie Permission to Warden.
     *
     * Migrates all authorization data in the following order:
     * 1. Roles - Creates corresponding Warden roles with guard names
     * 2. User role assignments - Links users to roles
     * 3. Direct user permissions - Grants abilities to specific users
     * 4. Role permissions - Grants abilities to roles
     *
     * Note: Unlike Bouncer, Spatie doesn't support forbidden abilities, so all
     * permissions are granted (not forbidden).
     */
    public function migrate(): void
    {
        Log::channel($this->logChannel)->debug('Starting migration from Spatie Permission to Warden...');

        $this->migrateRoles();
        $this->migrateModelRoles();
        $this->migrateModelPermissions();
        $this->migrateRolePermissions();

        Log::channel($this->logChannel)->debug('Migration completed successfully!');
    }

    /**
     * Migrate all roles from Spatie Permission to Warden.
     *
     * Reads roles from Spatie's roles table and creates corresponding Warden role
     * records, preserving guard names for scoped permissions. Uses firstOrCreate
     * to avoid duplicates if migration is run multiple times.
     */
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

    /**
     * Migrate user role assignments from Spatie Permission to Warden.
     *
     * Reads from Spatie's model_has_roles pivot table and assigns roles to users
     * in Warden. Skips assignments where the user or role no longer exists. Uses
     * Warden's fluent API to ensure proper guard scoping and logging of results.
     */
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

    /**
     * Migrate direct user permissions from Spatie Permission to Warden.
     *
     * Reads from Spatie's model_has_permissions pivot table and grants abilities
     * directly to users in Warden. Unlike role-based permissions, these are direct
     * grants to individual users. Skips permissions where the user or permission
     * record no longer exists, logging each operation for audit purposes.
     */
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

    /**
     * Migrate role permissions from Spatie Permission to Warden.
     *
     * Reads from Spatie's role_has_permissions pivot table and grants abilities
     * to roles in Warden. This establishes the role-based authorization model
     * where users inherit permissions through their assigned roles. Skips entries
     * where the role or permission record no longer exists.
     */
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

    /**
     * Get the configured table name for a Spatie table.
     *
     * Retrieves table names from warden.migrators.spatie.tables configuration,
     * allowing customization of Spatie's table names if they were changed from
     * defaults in the original installation. Asserts the result is a string to
     * satisfy static analysis type requirements.
     *
     * @param  string $table The table key from configuration (e.g., 'roles', 'permissions')
     * @return string The actual database table name from configuration
     */
    private function getTableName(string $table): string
    {
        $tableName = config('warden.migrators.spatie.tables.'.$table);
        assert(is_string($tableName));

        return $tableName;
    }

    /**
     * Get the model type string used in Spatie's polymorphic relationships.
     *
     * Retrieves the model type identifier from configuration, defaulting to 'user'.
     * Spatie stores this in the model_type column of polymorphic pivot tables to
     * identify which model class the permissions belong to. Configure this if your
     * Spatie installation used a custom morph map.
     *
     * @return string The model type identifier (e.g., 'user', 'App\Models\User')
     */
    private function getModelType(): string
    {
        $modelType = config('warden.migrators.spatie.model_type', 'user');
        assert(is_string($modelType));

        return $modelType;
    }

    /**
     * Find a user by their primary key, including soft-deleted users.
     *
     * Locates a user by their primary key value, including soft-deleted records
     * since permission assignments may still be valid for soft-deleted users.
     * Automatically detects if the user model uses SoftDeletes trait and adjusts
     * the query accordingly. Uses the model's actual primary key column name
     * rather than assuming 'id'.
     *
     * @param  mixed      $userId The user's primary key value (int, string, UUID, ULID)
     * @return null|Model The user model instance or null if not found
     */
    private function findUser(mixed $userId): ?Model
    {
        $model = $this->userModel;

        // Use model's actual primary key, not morph key, since legacy tables store bigint IDs
        /** @phpstan-ignore-next-line method.nonObject (Instantiated class is expected to be Model with getKeyName()) */
        $keyColumn = new $model()->getKeyName();

        if (in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
            /** @phpstan-ignore-next-line method.nonObject (static call on class-string) */
            return $model::withTrashed()->where($keyColumn, $userId)->first();
        }

        /** @phpstan-ignore-next-line method.nonObject (static call on class-string) */
        return $model::where($keyColumn, $userId)->first();
    }
}
