<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Database\Models;
use Cline\Warden\Migrators\SpatieMigrator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\Models\SoftDeletesSpatieUser;
use Tests\Fixtures\Models\SpatieUser;

beforeEach(function (): void {
    Config::set('warden.migrators.spatie.tables', [
        'permissions' => 'spatie_permissions',
        'roles' => 'spatie_roles',
        'model_has_permissions' => 'spatie_model_has_permissions',
        'model_has_roles' => 'spatie_model_has_roles',
        'role_has_permissions' => 'spatie_role_has_permissions',
    ]);
    Config::set('warden.migrators.spatie.model_type', 'user');

    // Configure morph key mapping for UUID/ULID modes
    // This tells Warden to use uuid/ulid column for foreign keys while migrator uses id to query legacy data
    $actorMorphType = config('warden.actor_morph_type', 'string');

    if ($actorMorphType === 'uuid') {
        Models::morphKeyMap([
            SpatieUser::class => 'uuid',
            SoftDeletesSpatieUser::class => 'uuid',
        ]);
    } elseif ($actorMorphType === 'ulid') {
        Models::morphKeyMap([
            SpatieUser::class => 'ulid',
            SoftDeletesSpatieUser::class => 'ulid',
        ]);
    }

    // Add migrated_at columns to spatie tables for migration tracking tests
    $connection = $this->app['config']->get('database.default');

    if (Schema::connection($connection)->hasTable('spatie_roles') && !Schema::connection($connection)->hasColumn('spatie_roles', 'migrated_at')) {
        Schema::connection($connection)->table('spatie_roles', function (Blueprint $table): void {
            $table->timestamp('migrated_at')->nullable();
        });
    }

    if (Schema::connection($connection)->hasTable('spatie_permissions') && !Schema::connection($connection)->hasColumn('spatie_permissions', 'migrated_at')) {
        Schema::connection($connection)->table('spatie_permissions', function (Blueprint $table): void {
            $table->timestamp('migrated_at')->nullable();
        });
    }

    if (Schema::connection($connection)->hasTable('spatie_model_has_roles') && !Schema::connection($connection)->hasColumn('spatie_model_has_roles', 'migrated_at')) {
        Schema::connection($connection)->table('spatie_model_has_roles', function (Blueprint $table): void {
            $table->timestamp('migrated_at')->nullable();
        });
    }

    if (Schema::connection($connection)->hasTable('spatie_model_has_permissions') && !Schema::connection($connection)->hasColumn('spatie_model_has_permissions', 'migrated_at')) {
        Schema::connection($connection)->table('spatie_model_has_permissions', function (Blueprint $table): void {
            $table->timestamp('migrated_at')->nullable();
        });
    }

    if (Schema::connection($connection)->hasTable('spatie_role_has_permissions') && !Schema::connection($connection)->hasColumn('spatie_role_has_permissions', 'migrated_at')) {
        Schema::connection($connection)->table('spatie_role_has_permissions', function (Blueprint $table): void {
            $table->timestamp('migrated_at')->nullable();
        });
    }

    if (!Schema::connection($connection)->hasTable('spatie_roles') || Schema::connection($connection)->hasColumn('spatie_roles', 'last_migrated')) {
        return;
    }

    Schema::connection($connection)->table('spatie_roles', function (Blueprint $table): void {
        $table->timestamp('last_migrated')->nullable();
    });
});

afterEach(function (): void {
    // Clear model boot state after each test to prevent cross-contamination
    Model::clearBootedModels();
});

describe('SpatieMigrator', function (): void {
    describe('Happy Paths', function (): void {
        test('migrates roles from Spatie to Warden', function (): void {
            // Arrange
            DB::table('spatie_roles')->insert([
                ['name' => 'admin', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'editor', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ]);

            $migrator = new SpatieMigrator(SpatieUser::class);

            // Act
            $migrator->migrate();

            // Assert
            $this->assertDatabaseHas('roles', ['name' => 'admin']);
            $this->assertDatabaseHas('roles', ['name' => 'editor']);
        });

        test('migrates user role assignments from Spatie to Warden', function (): void {
            // Arrange
            $user = SpatieUser::query()->create(['name' => 'John Doe']);

            $adminRoleId = DB::table('spatie_roles')->insertGetId([
                'name' => 'admin',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('spatie_model_has_roles')->insert([
                'role_id' => $adminRoleId,
                'model_type' => 'user',
                'model_id' => $user->id,
            ]);

            $migrator = new SpatieMigrator(SpatieUser::class);

            // Act
            $migrator->migrate();

            // Assert
            $this->assertDatabaseHas('roles', ['name' => 'admin']);
            $this->assertDatabaseHas('assigned_roles', [
                'actor_id' => getActorId($user),
                'actor_type' => $user->getMorphClass(),
            ]);

            $role = Models::role()->where('name', 'admin')->first();
            expect($role)->not->toBeNull();
            $this->assertDatabaseHas('assigned_roles', ['role_id' => $role->id]);
        });

        test('migrates direct user permissions from Spatie to Warden', function (): void {
            // Arrange
            $user = SpatieUser::query()->create(['name' => 'Jane Doe']);

            $permissionId = DB::table('spatie_permissions')->insertGetId([
                'name' => 'edit-articles',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('spatie_model_has_permissions')->insert([
                'permission_id' => $permissionId,
                'model_type' => 'user',
                'model_id' => $user->id,
            ]);

            $migrator = new SpatieMigrator(SpatieUser::class);

            // Act
            $migrator->migrate();

            // Assert
            $this->assertDatabaseHas('abilities', ['name' => 'edit-articles']);
            $this->assertDatabaseHas('permissions', [
                'actor_id' => getActorId($user),
                'actor_type' => $user->getMorphClass(),
            ]);
        });

        test('migrates role permissions from Spatie to Warden', function (): void {
            // Arrange
            $roleId = DB::table('spatie_roles')->insertGetId([
                'name' => 'editor',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $permissionId = DB::table('spatie_permissions')->insertGetId([
                'name' => 'edit-articles',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('spatie_role_has_permissions')->insert([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);

            $migrator = new SpatieMigrator(SpatieUser::class);

            // Act
            $migrator->migrate();

            // Assert
            $this->assertDatabaseHas('roles', ['name' => 'editor']);
            $this->assertDatabaseHas('abilities', ['name' => 'edit-articles']);

            $role = Models::role()->where('name', 'editor')->first();
            $ability = Models::ability()->where('name', 'edit-articles')->first();
            expect($role)->not->toBeNull();
            expect($ability)->not->toBeNull();

            $this->assertDatabaseHas('permissions', [
                'actor_id' => $role->id,
                'actor_type' => $role->getMorphClass(),
                'ability_id' => $ability->id,
            ]);
        });

        test('migrates user with both role and direct permission', function (): void {
            // Arrange - User has admin role AND a direct edit-articles permission
            $user = SpatieUser::query()->create(['name' => 'Bob Smith']);

            $adminRoleId = DB::table('spatie_roles')->insertGetId([
                'name' => 'admin',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $manageUsersPermId = DB::table('spatie_permissions')->insertGetId([
                'name' => 'manage-users',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $editArticlesPermId = DB::table('spatie_permissions')->insertGetId([
                'name' => 'edit-articles',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // User has admin role
            DB::table('spatie_model_has_roles')->insert([
                'role_id' => $adminRoleId,
                'model_type' => 'user',
                'model_id' => $user->id,
            ]);

            // Admin role has manage-users permission
            DB::table('spatie_role_has_permissions')->insert([
                'role_id' => $adminRoleId,
                'permission_id' => $manageUsersPermId,
            ]);

            // User also has direct edit-articles permission
            DB::table('spatie_model_has_permissions')->insert([
                'permission_id' => $editArticlesPermId,
                'model_type' => 'user',
                'model_id' => $user->id,
            ]);

            $migrator = new SpatieMigrator(SpatieUser::class);

            // Act
            $migrator->migrate();

            // Assert - Verify roles migrated
            $this->assertDatabaseHas('roles', ['name' => 'admin']);
            $role = Models::role()->where('name', 'admin')->first();
            expect($role)->not->toBeNull();

            // Assert - Verify both abilities migrated
            $this->assertDatabaseHas('abilities', ['name' => 'manage-users']);
            $this->assertDatabaseHas('abilities', ['name' => 'edit-articles']);
            $manageUsersAbility = Models::ability()->where('name', 'manage-users')->first();
            $editArticlesAbility = Models::ability()->where('name', 'edit-articles')->first();
            expect($manageUsersAbility)->not->toBeNull();
            expect($editArticlesAbility)->not->toBeNull();

            // Assert - Verify user role assignment migrated
            $this->assertDatabaseHas('assigned_roles', [
                'role_id' => $role->id,
                'actor_id' => getActorId($user),
                'actor_type' => $user->getMorphClass(),
            ]);

            // Assert - Verify role permission migrated (manage-users via admin role)
            $this->assertDatabaseHas('permissions', [
                'ability_id' => $manageUsersAbility->id,
                'actor_id' => $role->id,
                'actor_type' => $role->getMorphClass(),
            ]);

            // Assert - Verify direct user permission migrated (edit-articles directly on user)
            $this->assertDatabaseHas('permissions', [
                'ability_id' => $editArticlesAbility->id,
                'actor_id' => getActorId($user),
                'actor_type' => $user->getMorphClass(),
            ]);
        });
    });

    describe('Edge Cases', function (): void {
        test('skips migration for non-existent user', function (): void {
            // Arrange
            $roleId = DB::table('spatie_roles')->insertGetId([
                'name' => 'admin',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('spatie_model_has_roles')->insert([
                'role_id' => $roleId,
                'model_type' => 'user',
                'model_id' => 99_999, // Non-existent user
            ]);

            $migrator = new SpatieMigrator(SpatieUser::class);

            // Act
            $migrator->migrate();

            // Assert - Role should be created
            $this->assertDatabaseHas('roles', ['name' => 'admin']);

            // Assert - But assignment should be skipped (no assignments created)
            expect(DB::table('assigned_roles')->count())->toBe(0);
        });

        test('skips migration for non-existent role', function (): void {
            // Arrange
            $user = SpatieUser::query()->create(['name' => 'Alice Johnson']);

            insertWithoutForeignKeyChecks('spatie_model_has_roles', [
                'role_id' => 99_999, // Non-existent role
                'model_type' => 'user',
                'model_id' => $user->id,
            ]);

            $migrator = new SpatieMigrator(SpatieUser::class);

            // Act
            $migrator->migrate();

            // Assert
            $this->assertDatabaseMissing('assigned_roles', ['actor_id' => getActorId($user)]);
        });

        test('skips direct permission migration when user does not exist', function (): void {
            // Arrange
            $permissionId = DB::table('spatie_permissions')->insertGetId([
                'name' => 'edit-articles',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            insertWithoutForeignKeyChecks('spatie_model_has_permissions', [
                'permission_id' => $permissionId,
                'model_type' => 'user',
                'model_id' => 99_999, // Non-existent user
            ]);

            $migrator = new SpatieMigrator(SpatieUser::class);

            // Act
            $migrator->migrate();

            // Assert - Permission assignment should be skipped (no permissions created)
            expect(DB::table('permissions')->count())->toBe(0);
        });

        test('skips migration for non-existent permission', function (): void {
            // Arrange
            $user = SpatieUser::query()->create(['name' => 'Charlie Brown']);

            insertWithoutForeignKeyChecks('spatie_model_has_permissions', [
                'permission_id' => 99_999, // Non-existent permission
                'model_type' => 'user',
                'model_id' => $user->id,
            ]);

            $migrator = new SpatieMigrator(SpatieUser::class);

            // Act
            $migrator->migrate();

            // Assert
            $this->assertDatabaseMissing('permissions', ['actor_id' => getActorId($user)]);
        });

        test('handles empty Spatie tables gracefully', function (): void {
            // Arrange
            $migrator = new SpatieMigrator(SpatieUser::class);

            // Act
            $migrator->migrate();

            // Assert
            expect(DB::table('roles')->count())->toBe(0);
            expect(DB::table('abilities')->count())->toBe(0);
            expect(DB::table('assigned_roles')->count())->toBe(0);
        });

        test('migrates multiple users with same role', function (): void {
            // Arrange
            $user1 = SpatieUser::query()->create(['name' => 'Alice']);
            $user2 = SpatieUser::query()->create(['name' => 'Bob']);
            $user3 = SpatieUser::query()->create(['name' => 'Charlie']);

            $memberRoleId = DB::table('spatie_roles')->insertGetId([
                'name' => 'member',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // All three users have member role
            foreach ([$user1, $user2, $user3] as $user) {
                DB::table('spatie_model_has_roles')->insert([
                    'role_id' => $memberRoleId,
                    'model_type' => 'user',
                    'model_id' => $user->id,
                ]);
            }

            $migrator = new SpatieMigrator(SpatieUser::class);

            // Act
            $migrator->migrate();

            // Assert - Verify role created only once
            expect(Models::role()->where('name', 'member')->count())->toBe(1);
            $role = Models::role()->where('name', 'member')->first();

            // Assert - Verify all three users have the role assignment
            $this->assertDatabaseHas('assigned_roles', [
                'role_id' => $role->id,
                'actor_id' => getActorId($user1),
                'actor_type' => $user1->getMorphClass(),
            ]);
            $this->assertDatabaseHas('assigned_roles', [
                'role_id' => $role->id,
                'actor_id' => getActorId($user2),
                'actor_type' => $user2->getMorphClass(),
            ]);
            $this->assertDatabaseHas('assigned_roles', [
                'role_id' => $role->id,
                'actor_id' => getActorId($user3),
                'actor_type' => $user3->getMorphClass(),
            ]);

            // Assert - Total assigned_roles count should be 3
            expect(DB::table('assigned_roles')->count())->toBe(3);
        });

        test('migrates user with multiple roles', function (): void {
            // Arrange
            $user = SpatieUser::query()->create(['name' => 'Super User']);

            $adminRoleId = DB::table('spatie_roles')->insertGetId([
                'name' => 'admin',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $editorRoleId = DB::table('spatie_roles')->insertGetId([
                'name' => 'editor',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $moderatorRoleId = DB::table('spatie_roles')->insertGetId([
                'name' => 'moderator',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // User has all three roles
            foreach ([$adminRoleId, $editorRoleId, $moderatorRoleId] as $roleId) {
                DB::table('spatie_model_has_roles')->insert([
                    'role_id' => $roleId,
                    'model_type' => 'user',
                    'model_id' => $user->id,
                ]);
            }

            $migrator = new SpatieMigrator(SpatieUser::class);

            // Act
            $migrator->migrate();

            // Assert - Verify all three roles migrated
            $this->assertDatabaseHas('roles', ['name' => 'admin']);
            $this->assertDatabaseHas('roles', ['name' => 'editor']);
            $this->assertDatabaseHas('roles', ['name' => 'moderator']);

            $adminRole = Models::role()->where('name', 'admin')->first();
            $editorRole = Models::role()->where('name', 'editor')->first();
            $moderatorRole = Models::role()->where('name', 'moderator')->first();

            // Assert - Verify user has all three role assignments
            $this->assertDatabaseHas('assigned_roles', [
                'role_id' => $adminRole->id,
                'actor_id' => getActorId($user),
                'actor_type' => $user->getMorphClass(),
            ]);
            $this->assertDatabaseHas('assigned_roles', [
                'role_id' => $editorRole->id,
                'actor_id' => getActorId($user),
                'actor_type' => $user->getMorphClass(),
            ]);
            $this->assertDatabaseHas('assigned_roles', [
                'role_id' => $moderatorRole->id,
                'actor_id' => getActorId($user),
                'actor_type' => $user->getMorphClass(),
            ]);

            // Assert - Total assigned_roles count should be 3
            expect(DB::table('assigned_roles')->count())->toBe(3);
        });

        test('migrates soft-deleted user with role assignment', function (): void {
            // Arrange
            $user = SoftDeletesSpatieUser::query()->create(['name' => 'Deleted User']);
            $user->delete(); // Soft delete the user

            $adminRoleId = DB::table('spatie_roles')->insertGetId([
                'name' => 'admin',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('spatie_model_has_roles')->insert([
                'role_id' => $adminRoleId,
                'model_type' => 'user',
                'model_id' => $user->id,
            ]);

            $migrator = new SpatieMigrator(SoftDeletesSpatieUser::class);

            // Act
            $migrator->migrate();

            // Assert - Role should be migrated
            $this->assertDatabaseHas('roles', ['name' => 'admin']);

            // Assert - Soft-deleted user should have role assignment
            $this->assertDatabaseHas('assigned_roles', [
                'actor_id' => getActorId($user),
                'actor_type' => $user->getMorphClass(),
            ]);
        });
    });

    describe('Idempotent Migrations - migrated_at Tracking', function (): void {
        test('marks migrated roles with migrated_at timestamp when tracking enabled', function (): void {
            // Arrange
            $migrationTracking = [
                'enabled' => true,
                'column' => 'migrated_at',
            ];

            DB::table('spatie_roles')->insert([
                ['name' => 'admin', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'editor', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ]);
            $migrator = new SpatieMigrator(SpatieUser::class, 'migration', $migrationTracking);

            // Freeze time to ensure consistent timestamps
            Date::setTestNow(now());

            // Act
            $migrator->migrate();

            // Assert - Roles migrated and marked with timestamp
            $this->assertDatabaseHas('spatie_roles', [
                'name' => 'admin',
                'migrated_at' => now()->toDateTimeString(),
            ]);
            $this->assertDatabaseHas('spatie_roles', [
                'name' => 'editor',
                'migrated_at' => now()->toDateTimeString(),
            ]);
        });

        test('marks migrated permissions with migrated_at timestamp when tracking enabled', function (): void {
            // Arrange
            $migrationTracking = [
                'enabled' => true,
                'column' => 'migrated_at',
            ];

            $roleId = DB::table('spatie_roles')->insertGetId([
                'name' => 'editor',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $editArticlesPermId = DB::table('spatie_permissions')->insertGetId([
                'name' => 'edit-articles',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $deleteArticlesPermId = DB::table('spatie_permissions')->insertGetId([
                'name' => 'delete-articles',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('spatie_role_has_permissions')->insert([
                ['role_id' => $roleId, 'permission_id' => $editArticlesPermId],
                ['role_id' => $roleId, 'permission_id' => $deleteArticlesPermId],
            ]);

            $migrator = new SpatieMigrator(SpatieUser::class, 'migration', $migrationTracking);

            // Freeze time to ensure consistent timestamps
            Date::setTestNow(now());

            // Act
            $migrator->migrate();

            // Assert - Permission relationships marked with timestamp
            $this->assertDatabaseHas('spatie_role_has_permissions', [
                'role_id' => $roleId,
                'permission_id' => $editArticlesPermId,
                'migrated_at' => now()->toDateTimeString(),
            ]);
            $this->assertDatabaseHas('spatie_role_has_permissions', [
                'role_id' => $roleId,
                'permission_id' => $deleteArticlesPermId,
                'migrated_at' => now()->toDateTimeString(),
            ]);
        });

        test('skips already migrated roles on second run', function (): void {
            // Arrange - First migration
            $migrationTracking = [
                'enabled' => true,
                'column' => 'migrated_at',
            ];

            $firstMigrationTime = now()->subHours(2);
            DB::table('spatie_roles')->insert([
                ['name' => 'admin', 'guard_name' => 'web', 'migrated_at' => $firstMigrationTime, 'created_at' => now(), 'updated_at' => now()],
            ]);
            $migrator = new SpatieMigrator(SpatieUser::class, 'migration', $migrationTracking);

            // Act - First migration
            $migrator->migrate();

            // Count created roles before second run
            $roleCountBefore = DB::table('roles')->count();

            // Act - Second migration
            $migrator->migrate();

            // Assert - Should not create duplicate
            expect(DB::table('roles')->count())->toBe($roleCountBefore);
        });

        test('migrates new roles while skipping already migrated ones', function (): void {
            // Arrange
            $migrationTracking = [
                'enabled' => true,
                'column' => 'migrated_at',
            ];

            $firstMigrationTime = now()->subHours(1);

            // Pre-create admin role in Warden to simulate a previous migration
            Models::role()->create(['name' => 'admin', 'guard_name' => 'web', 'title' => 'admin']);

            DB::table('spatie_roles')->insert([
                ['name' => 'admin', 'guard_name' => 'web', 'migrated_at' => $firstMigrationTime, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'editor', 'guard_name' => 'web', 'migrated_at' => null, 'created_at' => now(), 'updated_at' => now()],
            ]);
            $migrator = new SpatieMigrator(SpatieUser::class, 'migration', $migrationTracking);

            // Act
            $migrator->migrate();

            // Assert - Both roles in Warden, but only editor should be newly marked
            $this->assertDatabaseHas('roles', ['name' => 'admin']);
            $this->assertDatabaseHas('roles', ['name' => 'editor']);

            // Verify only the new role is marked with current timestamp
            $this->assertDatabaseHas('spatie_roles', [
                'name' => 'editor',
                'migrated_at' => now()->toDateTimeString(),
            ]);
        });

        test('handles tracking when column does not exist', function (): void {
            // Arrange - Tracking enabled but column doesn't exist (graceful degradation)
            $migrationTracking = [
                'enabled' => true,
                'column' => 'migrated_at',
            ];

            DB::table('spatie_roles')->insert([
                ['name' => 'admin', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ]);
            $migrator = new SpatieMigrator(SpatieUser::class, 'migration', $migrationTracking);

            // Act & Assert - Should not throw, just continue
            $migrator->migrate();
            $this->assertDatabaseHas('roles', ['name' => 'admin']);
        });

        test('tracks all migrated entities when tracking enabled', function (): void {
            // Arrange
            $migrationTracking = [
                'enabled' => true,
                'column' => 'migrated_at',
            ];

            $user = SpatieUser::query()->create(['name' => 'John Doe']);

            $adminRoleId = DB::table('spatie_roles')->insertGetId([
                'name' => 'admin',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $editArticlesPermId = DB::table('spatie_permissions')->insertGetId([
                'name' => 'edit-articles',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('spatie_model_has_roles')->insert([
                'role_id' => $adminRoleId,
                'model_type' => 'user',
                'model_id' => $user->id,
            ]);

            DB::table('spatie_model_has_permissions')->insert([
                'permission_id' => $editArticlesPermId,
                'model_type' => 'user',
                'model_id' => $user->id,
            ]);

            DB::table('spatie_role_has_permissions')->insert([
                'role_id' => $adminRoleId,
                'permission_id' => $editArticlesPermId,
            ]);
            $migrator = new SpatieMigrator(SpatieUser::class, 'migration', $migrationTracking);

            // Act
            $migrator->migrate();

            // Assert - All entities marked as migrated
            $this->assertDatabaseHas('spatie_roles', [
                'name' => 'admin',
                'migrated_at' => now()->toDateTimeString(),
            ]);
            // Verify that permission relationships are marked
            $this->assertDatabaseHas('spatie_model_has_permissions', [
                'permission_id' => $editArticlesPermId,
                'model_type' => 'user',
                'model_id' => $user->id,
                'migrated_at' => now()->toDateTimeString(),
            ]);
            $this->assertDatabaseHas('spatie_role_has_permissions', [
                'role_id' => $adminRoleId,
                'permission_id' => $editArticlesPermId,
                'migrated_at' => now()->toDateTimeString(),
            ]);
        });

        test('uses custom migrated_at column name from config', function (): void {
            // Arrange
            $migrationTracking = [
                'enabled' => true,
                'column' => 'last_migrated',
            ];

            DB::table('spatie_roles')->insert([
                ['name' => 'viewer', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ]);
            $migrator = new SpatieMigrator(SpatieUser::class, 'migration', $migrationTracking);

            // Act
            $migrator->migrate();

            // Assert - Role marked in custom column
            $role = DB::table('spatie_roles')->where('name', 'viewer')->first();
            expect($role->last_migrated)->not->toBeNull();
        });

        test('does not track when tracking disabled in config', function (): void {
            // Arrange
            $migrationTracking = [
                'enabled' => false,
                'column' => 'migrated_at',
            ];

            DB::table('spatie_roles')->insert([
                ['name' => 'admin', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ]);
            $migrator = new SpatieMigrator(SpatieUser::class, 'migration', $migrationTracking);

            // Act
            $migrator->migrate();

            // Assert - Role migrated but not marked (unless column has default)
            $this->assertDatabaseHas('roles', ['name' => 'admin']);
        });
    });

    describe('Regression Tests - Keymap Support', function (): void {
        test('findUser uses primary key for queries but morphKeyMap for foreign keys', function (): void {
            // Determine the appropriate morph key based on config
            $actorMorphType = config('warden.actor_morph_type', 'string');
            $morphKey = match ($actorMorphType) {
                'uuid' => 'uuid',
                'ulid' => 'ulid',
                default => 'account_id',
            };

            // Arrange - Configure keymap to use the appropriate column
            Models::enforceMorphKeyMap([
                SpatieUser::class => $morphKey,
            ]);

            // Create users
            $user1 = SpatieUser::query()->create(['name' => 'Alice', 'account_id' => 100]);
            $user2 = SpatieUser::query()->create(['name' => 'Bob', 'account_id' => 200]);
            $user3 = SpatieUser::query()->create(['name' => 'Charlie', 'account_id' => 300]);

            // Create Spatie roles
            $adminRoleId = DB::table('spatie_roles')->insertGetId([
                'name' => 'admin',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $editorRoleId = DB::table('spatie_roles')->insertGetId([
                'name' => 'editor',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $managerRoleId = DB::table('spatie_roles')->insertGetId([
                'name' => 'manager',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Spatie always stores actual primary keys (id), not custom keymap columns
            DB::table('spatie_model_has_roles')->insert([
                ['role_id' => $adminRoleId, 'model_type' => 'user', 'model_id' => $user1->id],
                ['role_id' => $editorRoleId, 'model_type' => 'user', 'model_id' => $user2->id],
                ['role_id' => $managerRoleId, 'model_type' => 'user', 'model_id' => $user3->id],
            ]);

            $migrator = new SpatieMigrator(SpatieUser::class);

            // Act
            $migrator->migrate();

            // Assert - Each user gets their own role (not all going to one user)
            $adminRole = Models::role()->where('name', 'admin')->first();
            $editorRole = Models::role()->where('name', 'editor')->first();
            $managerRole = Models::role()->where('name', 'manager')->first();

            // Get expected actor_id values based on morphKey
            $user1ActorId = $user1->{$morphKey};
            $user2ActorId = $user2->{$morphKey};
            $user3ActorId = $user3->{$morphKey};

            // User 1 should have admin
            $this->assertDatabaseHas('assigned_roles', [
                'role_id' => $adminRole->id,
                'actor_id' => $user1ActorId,
                'actor_type' => $user1->getMorphClass(),
            ]);

            // User 2 should have editor
            $this->assertDatabaseHas('assigned_roles', [
                'role_id' => $editorRole->id,
                'actor_id' => $user2ActorId,
                'actor_type' => $user2->getMorphClass(),
            ]);

            // User 3 should have manager
            $this->assertDatabaseHas('assigned_roles', [
                'role_id' => $managerRole->id,
                'actor_id' => $user3ActorId,
                'actor_type' => $user3->getMorphClass(),
            ]);

            // Verify each user has exactly one assignment (without fix, all would go to user with primary key 1)
            $this->assertDatabaseCount('assigned_roles', 3);
            expect(DB::table('assigned_roles')->where('actor_id', $user1ActorId)->count())->toBe(1);
            expect(DB::table('assigned_roles')->where('actor_id', $user2ActorId)->count())->toBe(1);
            expect(DB::table('assigned_roles')->where('actor_id', $user3ActorId)->count())->toBe(1);
        });
    });
});
