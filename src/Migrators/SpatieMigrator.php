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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use stdClass;

use function assert;
use function config;
use function is_bool;
use function is_string;
use function now;
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
     * Whether to track migration status with a migrated_at timestamp.
     *
     * When enabled, rows are marked with the migration timestamp to prevent
     * re-migration during subsequent runs.
     */
    private bool $trackMigratedAt;

    /**
     * The column name used to store the migrated_at timestamp.
     */
    private string $migratedAtColumn;

    /**
     * Create a new Spatie migrator instance.
     *
     * @param string                     $userModel         Fully-qualified class name of the user model to migrate
     *                                                      permissions for. Used to locate and restore user permission
     *                                                      assignments from Spatie's model_has_roles and model_has_permissions tables.
     * @param string                     $logChannel        Laravel log channel name where migration progress and errors
     *                                                      will be written. Defaults to 'migration' channel. Configure
     *                                                      this channel in config/logging.php to capture migration logs.
     * @param array<string, bool|string> $migrationTracking Configuration for migration tracking containing:
     *                                                      - 'enabled': Whether to track migration with migrated_at timestamp
     *                                                      - 'column': Column name for the migrated_at timestamp (default: 'migrated_at')
     */
    public function __construct(
        private string $userModel,
        private string $logChannel = 'migration',
        array $migrationTracking = ['enabled' => false, 'column' => 'migrated_at'],
    ) {
        $enabled = $migrationTracking['enabled'] ?? false;
        assert(is_bool($enabled));
        $this->trackMigratedAt = $enabled;

        $column = $migrationTracking['column'] ?? 'migrated_at';
        assert(is_string($column));
        $this->migratedAtColumn = $column;
    }

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
     *
     * When track_migrated_at is enabled, only processes rows where the migrated_at
     * column is null and marks them with the current timestamp after migration.
     */
    private function migrateRoles(): void
    {
        Log::channel($this->logChannel)->debug('Migrating roles...');

        $query = DB::table($this->getTableName('roles'));

        if ($this->trackMigratedAt) {
            $query = $query->whereNull($this->migratedAtColumn);
        }

        $roles = $query->get();

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

            if ($this->trackMigratedAt) {
                DB::table($this->getTableName('roles'))
                    ->where('name', $name)
                    ->where('guard_name', $guardName)
                    ->update([$this->migratedAtColumn => now()]);
            }

            Log::channel($this->logChannel)->debug('Created role: '.$name);
        }
    }

    /**
     * Migrate user role assignments from Spatie Permission to Warden.
     *
     * Reads from Spatie's model_has_roles pivot table and assigns roles to users
     * in Warden. Skips assignments where the user or role no longer exists. Uses
     * Warden's fluent API to ensure proper guard scoping and logging of results.
     *
     * When track_migrated_at is enabled, only processes rows where the migrated_at
     * column is null and marks them with the current timestamp after migration.
     */
    private function migrateModelRoles(): void
    {
        Log::channel($this->logChannel)->debug('Migrating user role assignments...');

        $query = DB::table($this->getTableName('model_has_roles'))
            ->where('model_type', $this->getModelType());

        if ($this->trackMigratedAt) {
            $query = $query->whereNull($this->migratedAtColumn);
        }

        $assignments = $query->get();

        /** @var stdClass $assignment */
        foreach ($assignments as $assignment) {
            /** @var int $modelId */
            $modelId = $assignment->model_id;

            if (!$this->userExists($modelId)) {
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

            $user = $this->createMinimalUserModel($modelId);

            Log::channel($this->logChannel)->debug(sprintf("Assigning role '%s' (guard: %s) to user ID: %s", $roleName, $guardName, $modelId));

            $result = Warden::guard($guardName)->assign($roleName)->to($user);

            Log::channel($this->logChannel)->debug(sprintf('Assignment result: %s', $result ? 'success' : 'failed'));
            Log::channel($this->logChannel)->debug(sprintf("Assigned role '%s' to user ID: %s", $roleName, $modelId));

            if (!$this->trackMigratedAt) {
                continue;
            }

            DB::table($this->getTableName('model_has_roles'))
                ->where('model_id', $modelId)
                ->where('role_id', $roleId)
                ->where('model_type', $this->getModelType())
                ->update([$this->migratedAtColumn => now()]);
        }
    }

    /**
     * Migrate direct user permissions from Spatie Permission to Warden.
     *
     * Reads from Spatie's model_has_permissions pivot table and grants abilities
     * directly to users in Warden. Unlike role-based permissions, these are direct
     * grants to individual users. Skips permissions where the user or permission
     * record no longer exists, logging each operation for audit purposes.
     *
     * When track_migrated_at is enabled, only processes rows where the migrated_at
     * column is null and marks them with the current timestamp after migration.
     */
    private function migrateModelPermissions(): void
    {
        Log::channel($this->logChannel)->debug('Migrating direct user permissions...');

        $query = DB::table($this->getTableName('model_has_permissions'))
            ->where('model_type', $this->getModelType());

        if ($this->trackMigratedAt) {
            $query = $query->whereNull($this->migratedAtColumn);
        }

        $assignments = $query->get();

        /** @var stdClass $assignment */
        foreach ($assignments as $assignment) {
            /** @var int $modelId */
            $modelId = $assignment->model_id;

            if (!$this->userExists($modelId)) {
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

            $user = $this->createMinimalUserModel($modelId);

            Warden::guard($guardName)->allow($user)->to($permissionName);
            Log::channel($this->logChannel)->debug(sprintf("Granted permission '%s' to user ID: %s", $permissionName, $modelId));

            if (!$this->trackMigratedAt) {
                continue;
            }

            DB::table($this->getTableName('model_has_permissions'))
                ->where('model_id', $modelId)
                ->where('permission_id', $permissionId)
                ->where('model_type', $this->getModelType())
                ->update([$this->migratedAtColumn => now()]);
        }
    }

    /**
     * Migrate role permissions from Spatie Permission to Warden.
     *
     * Reads from Spatie's role_has_permissions pivot table and grants abilities
     * to roles in Warden. This establishes the role-based authorization model
     * where users inherit permissions through their assigned roles. Skips entries
     * where the role or permission record no longer exists.
     *
     * When track_migrated_at is enabled, only processes rows where the migrated_at
     * column is null and marks them with the current timestamp after migration.
     */
    private function migrateRolePermissions(): void
    {
        Log::channel($this->logChannel)->debug('Migrating role permissions...');

        $query = DB::table($this->getTableName('role_has_permissions'));

        if ($this->trackMigratedAt) {
            $query = $query->whereNull($this->migratedAtColumn);
        }

        $assignments = $query->get();

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

            if (!$this->trackMigratedAt) {
                continue;
            }

            DB::table($this->getTableName('role_has_permissions'))
                ->where('role_id', $roleId)
                ->where('permission_id', $permissionId)
                ->update([$this->migratedAtColumn => now()]);
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
     * @param string $table The table key from configuration (e.g., 'roles', 'permissions')
     *
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
     * Check if a user exists by their primary key.
     *
     * Uses raw database query to avoid Eloquent model instantiation, which
     * prevents issues with eager-loaded relationships on the User model.
     * Includes soft-deleted users since permission assignments may still be valid.
     *
     * @param mixed $userId The user's primary key value
     *
     * @return bool True if user exists
     */
    private function userExists(mixed $userId): bool
    {
        return DB::table($this->getUserTable())
            ->where($this->getUserKeyColumn(), $userId)
            ->exists();
    }

    /**
     * Create a minimal user model instance without triggering eager loading.
     *
     * Creates a model instance using raw database data and newFromBuilder() to
     * bypass Eloquent's normal model instantiation, which would trigger $with
     * relationships and other boot logic that might fail during migration.
     *
     * @param mixed $userId The user's primary key value
     *
     * @return Model The minimal user model instance
     */
    private function createMinimalUserModel(mixed $userId): Model
    {
        $row = DB::table($this->getUserTable())
            ->where($this->getUserKeyColumn(), $userId)
            ->first();

        assert($row !== null, 'User must exist - checked by userExists()');

        $model = new ($this->userModel)();

        /** @var Model */
        return $model->newFromBuilder($row);
    }

    /**
     * Get the database table name for the user model.
     *
     * @return string The table name
     */
    private function getUserTable(): string
    {
        return new $this->userModel()->getTable();
    }

    /**
     * Get the primary key column name for the user model.
     *
     * @return string The primary key column name
     */
    private function getUserKeyColumn(): string
    {
        return new $this->userModel()->getKeyName();
    }
}
