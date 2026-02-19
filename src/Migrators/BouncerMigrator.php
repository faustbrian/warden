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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use function assert;
use function config;
use function is_bool;
use function is_int;
use function is_string;
use function json_decode;
use function now;
use function sprintf;

/**
 * Migrates authorization data from Silber/Bouncer to Warden.
 *
 * This migrator reads roles, abilities, and assignments from Bouncer's database
 * schema and recreates them in Warden's format. Handles both direct user permissions
 * and role-based permissions, including forbidden abilities. Preserves all permission
 * data including entity scoping and options.
 *
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
     * Create a new Bouncer migrator instance.
     *
     * @param string                     $userModel         Fully-qualified class name of the user model to migrate
     *                                                      permissions for. Used to locate and restore user permission
     *                                                      assignments from Bouncer's assigned_roles and permissions tables.
     * @param string                     $logChannel        Laravel log channel name where migration progress and errors
     *                                                      will be written. Defaults to 'migration' channel. Configure
     *                                                      this channel in config/logging.php to capture migration logs.
     * @param null|string                $guardName         Authentication guard name to assign to migrated roles and
     *                                                      abilities. If null, uses the default guard from warden.guard
     *                                                      config. All migrated permissions will be scoped to this guard.
     * @param array<string, bool|string> $migrationTracking Configuration for migration tracking containing:
     *                                                      - 'enabled': Whether to track migration with migrated_at timestamp
     *                                                      - 'column': Column name for the migrated_at timestamp (default: 'migrated_at')
     */
    public function __construct(
        private string $userModel,
        private string $logChannel = 'migration',
        ?string $guardName = null,
        array $migrationTracking = ['enabled' => false, 'column' => 'migrated_at'],
    ) {
        $guard = $guardName ?? config('warden.guard', 'web');
        assert(is_string($guard));
        $this->guardName = $guard;

        $enabled = $migrationTracking['enabled'] ?? false;
        assert(is_bool($enabled));
        $this->trackMigratedAt = $enabled;

        $column = $migrationTracking['column'] ?? 'migrated_at';
        assert(is_string($column));
        $this->migratedAtColumn = $column;
    }

    /**
     * Execute the complete migration from Bouncer to Warden.
     *
     * Migrates all authorization data in the following order:
     * 1. Roles - Creates corresponding Warden roles
     * 2. Abilities - Creates Warden abilities with all attributes
     * 3. User role assignments - Links users to roles
     * 4. Direct user permissions - Grants/forbids abilities for specific users
     * 5. Role permissions - Grants/forbids abilities for roles
     */
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

    /**
     * Migrate all roles from Bouncer to Warden.
     *
     * Reads roles from Bouncer's roles table and creates equivalent Warden roles
     * with the configured guard name. Uses firstOrCreate to avoid duplicates.
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

        foreach ($roles as $role) {
            assert(is_string($role->name));

            Models::role()->firstOrCreate(
                ['name' => $role->name, 'guard_name' => $this->guardName],
                ['title' => $role->title ?? $role->name, 'guard_name' => $this->guardName],
            );

            if ($this->trackMigratedAt) {
                DB::table($this->getTableName('roles'))
                    ->where('name', $role->name)
                    ->update([$this->migratedAtColumn => now()]);
            }

            Log::channel($this->logChannel)->debug('Created role: '.$role->name);
        }
    }

    /**
     * Migrate all abilities from Bouncer to Warden.
     *
     * Reads abilities from Bouncer's abilities table including entity scope, options,
     * and only_owned flags. Maps Bouncer's entity_id/entity_type to Warden's
     * subject_id/subject_type for model-specific abilities.
     *
     * When track_migrated_at is enabled, only processes rows where the migrated_at
     * column is null and marks them with the current timestamp after migration.
     */
    private function migrateAbilities(): void
    {
        Log::channel($this->logChannel)->debug('Migrating abilities...');

        $query = DB::table($this->getTableName('abilities'));

        if ($this->trackMigratedAt) {
            $query = $query->whereNull($this->migratedAtColumn);
        }

        $abilities = $query->get();

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

            if ($this->trackMigratedAt) {
                DB::table($this->getTableName('abilities'))
                    ->where('name', $ability->name)
                    ->update([$this->migratedAtColumn => now()]);
            }

            Log::channel($this->logChannel)->debug('Created ability: '.$ability->name);
        }
    }

    /**
     * Migrate user role assignments from Bouncer to Warden.
     *
     * Reads role assignments from Bouncer's assigned_roles table and recreates
     * them using Warden's AssignsRoles conductor. Handles soft-deleted users if
     * the user model uses SoftDeletes trait.
     *
     * When track_migrated_at is enabled, only processes rows where the migrated_at
     * column is null and marks them with the current timestamp after migration.
     */
    private function migrateModelRoles(): void
    {
        Log::channel($this->logChannel)->debug('Migrating user role assignments...');

        $query = DB::table($this->getTableName('assigned_roles'))
            ->where('entity_type', $this->getEntityType());

        if ($this->trackMigratedAt) {
            $query = $query->whereNull($this->migratedAtColumn);
        }

        $assignments = $query->get();

        foreach ($assignments as $assignment) {
            assert($assignment->entity_id !== null && $assignment->role_id !== null);
            assert(is_int($assignment->entity_id) && is_int($assignment->role_id));

            if (!$this->userExists($assignment->entity_id)) {
                Log::channel($this->logChannel)->debug('User not found: '.$assignment->entity_id);

                continue;
            }

            $user = $this->createMinimalUserModel($assignment->entity_id);

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

            if (!$this->trackMigratedAt) {
                continue;
            }

            DB::table($this->getTableName('assigned_roles'))
                ->where('entity_id', $assignment->entity_id)
                ->where('role_id', $assignment->role_id)
                ->where('entity_type', $this->getEntityType())
                ->update([$this->migratedAtColumn => now()]);
        }
    }

    /**
     * Migrate direct user permissions from Bouncer to Warden.
     *
     * Reads permissions from Bouncer's permissions table where entity_type matches
     * the user model. Applies both granted and forbidden abilities to users using
     * GivesAbilities or ForbidsAbilities conductors based on the forbidden flag.
     *
     * When track_migrated_at is enabled, only processes rows where the migrated_at
     * column is null and marks them with the current timestamp after migration.
     */
    private function migrateModelPermissions(): void
    {
        Log::channel($this->logChannel)->debug('Migrating direct user permissions...');

        $query = DB::table($this->getTableName('permissions'))
            ->where('entity_type', $this->getEntityType());

        if ($this->trackMigratedAt) {
            $query = $query->whereNull($this->migratedAtColumn);
        }

        $permissions = $query->get();

        foreach ($permissions as $permission) {
            assert($permission->entity_id !== null && $permission->ability_id !== null);
            assert(is_int($permission->entity_id) && is_int($permission->ability_id));

            if (!$this->userExists($permission->entity_id)) {
                Log::channel($this->logChannel)->debug('User not found: '.$permission->entity_id);

                continue;
            }

            $user = $this->createMinimalUserModel($permission->entity_id);

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

            if (!$this->trackMigratedAt) {
                continue;
            }

            DB::table($this->getTableName('permissions'))
                ->where('entity_id', $permission->entity_id)
                ->where('ability_id', $permission->ability_id)
                ->where('entity_type', $this->getEntityType())
                ->update([$this->migratedAtColumn => now()]);
        }
    }

    /**
     * Migrate role permissions from Bouncer to Warden.
     *
     * Reads permissions from Bouncer's permissions table where entity_type is null
     * (indicating role permissions). Applies both granted and forbidden abilities to
     * roles using GivesAbilities or ForbidsAbilities conductors.
     *
     * When track_migrated_at is enabled, only processes rows where the migrated_at
     * column is null and marks them with the current timestamp after migration.
     */
    private function migrateRolePermissions(): void
    {
        Log::channel($this->logChannel)->debug('Migrating role permissions...');

        $query = DB::table($this->getTableName('permissions'))
            ->whereNull('entity_type');

        if ($this->trackMigratedAt) {
            $query = $query->whereNull($this->migratedAtColumn);
        }

        $permissions = $query->get();

        foreach ($permissions as $permission) {
            assert($permission->ability_id !== null && $permission->entity_id !== null);
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

            if (!$this->trackMigratedAt) {
                continue;
            }

            DB::table($this->getTableName('permissions'))
                ->where('ability_id', $permission->ability_id)
                ->where('entity_id', $permission->entity_id)
                ->whereNull('entity_type')
                ->update([$this->migratedAtColumn => now()]);
        }
    }

    /**
     * Get the configured table name for a Bouncer table.
     *
     * @param string $table The table key (e.g., 'roles', 'abilities', 'permissions')
     *
     * @return string The actual table name from configuration
     */
    private function getTableName(string $table): string
    {
        $tableName = config('warden.migrators.bouncer.tables.'.$table);
        assert(is_string($tableName));

        return $tableName;
    }

    /**
     * Get the entity type string used in Bouncer's polymorphic relationships.
     *
     * @return string The entity type identifier (model class name or alias)
     */
    private function getEntityType(): string
    {
        $entityType = config('warden.migrators.bouncer.entity_type', $this->userModel);
        assert(is_string($entityType));

        return $entityType;
    }

    /**
     * Check if a user exists by their primary key.
     *
     * Uses raw database query to avoid Eloquent model instantiation, which
     * prevents issues with eager-loaded relationships on the User model.
     * Includes soft-deleted users since permission assignments may still be valid.
     *
     * @param int $userId The user's primary key value
     *
     * @return bool True if user exists
     */
    private function userExists(int $userId): bool
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
     * @param int $userId The user's primary key value
     *
     * @return Model The minimal user model instance
     */
    private function createMinimalUserModel(int $userId): Model
    {
        $row = DB::table($this->getUserTable())
            ->where($this->getUserKeyColumn(), $userId)
            ->first();

        assert($row !== null, 'User must exist - checked by userExists()');

        /** @var Model $model */
        $model = new ($this->userModel)();

        /** @var array<string, mixed> $attributes */
        $attributes = (array) $row;

        return $model->newFromBuilder($attributes);
    }

    /**
     * Get the database table name for the user model.
     *
     * @return string The table name
     */
    private function getUserTable(): string
    {
        /** @var Model $model */
        $model = new ($this->userModel)();

        return $model->getTable();
    }

    /**
     * Get the primary key column name for the user model.
     *
     * @return string The primary key column name
     */
    private function getUserKeyColumn(): string
    {
        /** @var Model $model */
        $model = new ($this->userModel)();

        return $model->getKeyName();
    }
}
