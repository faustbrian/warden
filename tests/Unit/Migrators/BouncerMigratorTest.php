<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Database\Models;
use Cline\Warden\Migrators\BouncerMigrator;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\Models\SoftDeletesUser;
use Tests\Fixtures\Models\User;

beforeEach(function (): void {
    Config::set('warden.migrators.bouncer.tables', [
        'abilities' => 'bouncer_abilities',
        'roles' => 'bouncer_roles',
        'assigned_roles' => 'bouncer_assigned_roles',
        'permissions' => 'bouncer_permissions',
    ]);
    Config::set('warden.migrators.bouncer.entity_type', User::class);
});

describe('BouncerMigrator', function (): void {
    describe('Happy Paths', function (): void {
        test('migrates roles from Bouncer to Warden', function (): void {
            // Arrange
            DB::table('bouncer_roles')->insert([
                ['name' => 'admin', 'title' => 'Administrator', 'scope' => null, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'editor', 'title' => 'Editor', 'scope' => null, 'created_at' => now(), 'updated_at' => now()],
            ]);

            $migrator = new BouncerMigrator(User::class);

            // Act
            $migrator->migrate();

            // Assert
            $this->assertDatabaseHas('roles', ['name' => 'admin', 'title' => 'Administrator']);
            $this->assertDatabaseHas('roles', ['name' => 'editor', 'title' => 'Editor']);
        });

        test('migrates abilities with metadata from Bouncer to Warden', function (): void {
            // Arrange
            DB::table('bouncer_abilities')->insert([
                [
                    'name' => 'edit-posts',
                    'title' => 'Edit Posts',
                    'entity_id' => null,
                    'entity_type' => null,
                    'only_owned' => false,
                    'options' => null,
                    'scope' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'delete-own-posts',
                    'title' => 'Delete Own Posts',
                    'entity_id' => null,
                    'entity_type' => null,
                    'only_owned' => true,
                    'options' => json_encode(['limit' => 10]),
                    'scope' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            $migrator = new BouncerMigrator(User::class);

            // Act
            $migrator->migrate();

            // Assert - Verify abilities migrated with metadata
            $this->assertDatabaseHas('abilities', [
                'name' => 'edit-posts',
                'title' => 'Edit Posts',
                'only_owned' => false,
                'options' => null,
                'subject_id' => null,
                'subject_type' => null,
            ]);

            $this->assertDatabaseHas('abilities', [
                'name' => 'delete-own-posts',
                'title' => 'Delete Own Posts',
                'only_owned' => true,
                'options' => json_encode(['limit' => 10]),
                'subject_id' => null,
                'subject_type' => null,
            ]);
        });

        test('migrates user role assignments from Bouncer to Warden', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'John Doe']);

            $adminRoleId = DB::table('bouncer_roles')->insertGetId([
                'name' => 'admin',
                'title' => 'Administrator',
                'scope' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('bouncer_assigned_roles')->insert([
                'role_id' => $adminRoleId,
                'entity_type' => User::class,
                'entity_id' => $user->id,
                'restricted_to_id' => null,
                'restricted_to_type' => null,
                'scope' => null,
            ]);

            $migrator = new BouncerMigrator(User::class);

            // Act
            $migrator->migrate();

            // Assert
            $this->assertDatabaseHas('roles', ['name' => 'admin']);
            $this->assertDatabaseHas('assigned_roles', [
                'actor_id' => $user->id,
                'actor_type' => $user->getMorphClass(),
            ]);
        });

        test('migrates direct user permissions from Bouncer to Warden', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'Jane Doe']);

            $abilityId = DB::table('bouncer_abilities')->insertGetId([
                'name' => 'edit-articles',
                'title' => 'Edit Articles',
                'entity_id' => null,
                'entity_type' => null,
                'only_owned' => false,
                'options' => null,
                'scope' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('bouncer_permissions')->insert([
                'ability_id' => $abilityId,
                'entity_id' => $user->id,
                'entity_type' => User::class,
                'forbidden' => false,
                'scope' => null,
            ]);

            $migrator = new BouncerMigrator(User::class);

            // Act
            $migrator->migrate();

            // Assert
            $this->assertDatabaseHas('abilities', ['name' => 'edit-articles']);
            $this->assertDatabaseHas('permissions', [
                'actor_id' => $user->id,
                'actor_type' => $user->getMorphClass(),
                'forbidden' => false,
            ]);
        });

        test('migrates forbidden user permissions from Bouncer to Warden', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'Evil User']);

            $abilityId = DB::table('bouncer_abilities')->insertGetId([
                'name' => 'delete-users',
                'title' => 'Delete Users',
                'entity_id' => null,
                'entity_type' => null,
                'only_owned' => false,
                'options' => null,
                'scope' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('bouncer_permissions')->insert([
                'ability_id' => $abilityId,
                'entity_id' => $user->id,
                'entity_type' => User::class,
                'forbidden' => true,
                'scope' => null,
            ]);

            $migrator = new BouncerMigrator(User::class);

            // Act
            $migrator->migrate();

            // Assert
            $this->assertDatabaseHas('abilities', ['name' => 'delete-users']);
            $this->assertDatabaseHas('permissions', [
                'actor_id' => $user->id,
                'actor_type' => $user->getMorphClass(),
                'forbidden' => true,
            ]);
        });

        test('migrates user with both role and direct permission', function (): void {
            // Arrange - User has admin role AND a direct edit-articles permission
            $user = User::query()->create(['name' => 'Bob Smith']);

            $adminRoleId = DB::table('bouncer_roles')->insertGetId([
                'name' => 'admin',
                'title' => 'Administrator',
                'scope' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $manageUsersAbilityId = DB::table('bouncer_abilities')->insertGetId([
                'name' => 'manage-users',
                'title' => 'Manage Users',
                'entity_id' => null,
                'entity_type' => null,
                'only_owned' => false,
                'options' => null,
                'scope' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $editArticlesAbilityId = DB::table('bouncer_abilities')->insertGetId([
                'name' => 'edit-articles',
                'title' => 'Edit Articles',
                'entity_id' => null,
                'entity_type' => null,
                'only_owned' => false,
                'options' => null,
                'scope' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // User has admin role
            DB::table('bouncer_assigned_roles')->insert([
                'role_id' => $adminRoleId,
                'entity_type' => User::class,
                'entity_id' => $user->id,
                'restricted_to_id' => null,
                'restricted_to_type' => null,
                'scope' => null,
            ]);

            // Admin role has manage-users permission (no entity_type = role permission)
            DB::table('bouncer_permissions')->insert([
                'ability_id' => $manageUsersAbilityId,
                'entity_id' => $adminRoleId,
                'entity_type' => null,
                'forbidden' => false,
                'scope' => null,
            ]);

            // User also has direct edit-articles permission
            DB::table('bouncer_permissions')->insert([
                'ability_id' => $editArticlesAbilityId,
                'entity_id' => $user->id,
                'entity_type' => User::class,
                'forbidden' => false,
                'scope' => null,
            ]);

            $migrator = new BouncerMigrator(User::class);

            // Act
            $migrator->migrate();

            // Assert - Verify roles migrated
            $this->assertDatabaseHas('roles', ['name' => 'admin', 'title' => 'Administrator']);
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
                'actor_id' => $user->id,
                'actor_type' => $user->getMorphClass(),
            ]);

            // Assert - Verify role permission migrated (manage-users via admin role)
            $this->assertDatabaseHas('permissions', [
                'ability_id' => $manageUsersAbility->id,
                'actor_id' => $role->id,
                'actor_type' => $role->getMorphClass(),
                'forbidden' => false,
            ]);

            // Assert - Verify direct user permission migrated (edit-articles directly on user)
            $this->assertDatabaseHas('permissions', [
                'ability_id' => $editArticlesAbility->id,
                'actor_id' => $user->id,
                'actor_type' => $user->getMorphClass(),
                'forbidden' => false,
            ]);
        });
    });

    describe('Edge Cases', function (): void {
        test('skips migration for non-existent user', function (): void {
            // Arrange
            $roleId = DB::table('bouncer_roles')->insertGetId([
                'name' => 'admin',
                'title' => 'Administrator',
                'scope' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('bouncer_assigned_roles')->insert([
                'role_id' => $roleId,
                'entity_type' => User::class,
                'entity_id' => 99_999, // Non-existent user
                'restricted_to_id' => null,
                'restricted_to_type' => null,
                'scope' => null,
            ]);

            $migrator = new BouncerMigrator(User::class);
            $migrator->migrate();

            // Act
            $migrator->migrate();

            // Assert
            $this->assertDatabaseMissing('assigned_roles', ['actor_id' => 99_999]);
        });

        test('skips migration for non-existent role', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'Alice Johnson']);

            DB::table('bouncer_assigned_roles')->insert([
                'role_id' => 99_999, // Non-existent role
                'entity_type' => User::class,
                'entity_id' => $user->id,
                'restricted_to_id' => null,
                'restricted_to_type' => null,
                'scope' => null,
            ]);

            $migrator = new BouncerMigrator(User::class);

            // Act
            $migrator->migrate();

            // Assert
            $this->assertDatabaseMissing('assigned_roles', ['actor_id' => $user->id]);
        });

        test('skips migration for non-existent ability', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'Charlie Brown']);

            DB::table('bouncer_permissions')->insert([
                'ability_id' => 99_999, // Non-existent ability
                'entity_id' => $user->id,
                'entity_type' => User::class,
                'forbidden' => false,
                'scope' => null,
            ]);

            $migrator = new BouncerMigrator(User::class);

            // Act
            $migrator->migrate();

            // Assert
            $this->assertDatabaseMissing('permissions', ['actor_id' => $user->id]);
        });

        test('handles empty Bouncer tables gracefully', function (): void {
            // Arrange
            $migrator = new BouncerMigrator(User::class);

            // Act
            $migrator->migrate();

            // Assert
            expect(DB::table('roles')->count())->toBe(0);
            expect(DB::table('abilities')->count())->toBe(0);
            expect(DB::table('assigned_roles')->count())->toBe(0);
        });

        test('migrates role without title', function (): void {
            // Arrange
            DB::table('bouncer_roles')->insert([
                'name' => 'viewer',
                'title' => null,
                'scope' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $migrator = new BouncerMigrator(User::class);

            // Act
            $migrator->migrate();

            // Assert
            $this->assertDatabaseHas('roles', ['name' => 'viewer', 'title' => 'viewer']);
        });

        test('migrates role with forbidden permission', function (): void {
            // Arrange
            $editorRoleId = DB::table('bouncer_roles')->insertGetId([
                'name' => 'editor',
                'title' => 'Editor',
                'scope' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $deletePostsAbilityId = DB::table('bouncer_abilities')->insertGetId([
                'name' => 'delete-posts',
                'title' => 'Delete Posts',
                'entity_id' => null,
                'entity_type' => null,
                'only_owned' => false,
                'options' => null,
                'scope' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Editor role has forbidden delete-posts permission (entity_type = null means role permission)
            DB::table('bouncer_permissions')->insert([
                'ability_id' => $deletePostsAbilityId,
                'entity_id' => $editorRoleId,
                'entity_type' => null,
                'forbidden' => true,
                'scope' => null,
            ]);

            $migrator = new BouncerMigrator(User::class);

            // Act
            $migrator->migrate();

            // Assert - Verify role migrated
            $this->assertDatabaseHas('roles', ['name' => 'editor', 'title' => 'Editor']);
            $role = Models::role()->where('name', 'editor')->first();
            expect($role)->not->toBeNull();

            // Assert - Verify ability migrated
            $this->assertDatabaseHas('abilities', ['name' => 'delete-posts']);
            $ability = Models::ability()->where('name', 'delete-posts')->first();
            expect($ability)->not->toBeNull();

            // Assert - Verify forbidden role permission migrated
            $this->assertDatabaseHas('permissions', [
                'ability_id' => $ability->id,
                'actor_id' => $role->id,
                'actor_type' => $role->getMorphClass(),
                'forbidden' => true,
            ]);
        });

        test('migrates role with permission but no users assigned', function (): void {
            // Arrange - Role exists with permission but NO users have this role
            $moderatorRoleId = DB::table('bouncer_roles')->insertGetId([
                'name' => 'moderator',
                'title' => 'Moderator',
                'scope' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $approveCommentsAbilityId = DB::table('bouncer_abilities')->insertGetId([
                'name' => 'approve-comments',
                'title' => 'Approve Comments',
                'entity_id' => null,
                'entity_type' => null,
                'only_owned' => false,
                'options' => null,
                'scope' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Moderator role has approve-comments permission (no users assigned)
            DB::table('bouncer_permissions')->insert([
                'ability_id' => $approveCommentsAbilityId,
                'entity_id' => $moderatorRoleId,
                'entity_type' => null,
                'forbidden' => false,
                'scope' => null,
            ]);

            $migrator = new BouncerMigrator(User::class);

            // Act
            $migrator->migrate();

            // Assert - Verify role migrated
            $this->assertDatabaseHas('roles', ['name' => 'moderator', 'title' => 'Moderator']);
            $role = Models::role()->where('name', 'moderator')->first();
            expect($role)->not->toBeNull();

            // Assert - Verify ability migrated
            $this->assertDatabaseHas('abilities', ['name' => 'approve-comments']);
            $ability = Models::ability()->where('name', 'approve-comments')->first();
            expect($ability)->not->toBeNull();

            // Assert - Verify role permission migrated even without users
            $this->assertDatabaseHas('permissions', [
                'ability_id' => $ability->id,
                'actor_id' => $role->id,
                'actor_type' => $role->getMorphClass(),
                'forbidden' => false,
            ]);
        });

        test('migrates multiple users with same role', function (): void {
            // Arrange
            $user1 = User::query()->create(['name' => 'Alice']);
            $user2 = User::query()->create(['name' => 'Bob']);
            $user3 = User::query()->create(['name' => 'Charlie']);

            $memberRoleId = DB::table('bouncer_roles')->insertGetId([
                'name' => 'member',
                'title' => 'Member',
                'scope' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // All three users have member role
            foreach ([$user1, $user2, $user3] as $user) {
                DB::table('bouncer_assigned_roles')->insert([
                    'role_id' => $memberRoleId,
                    'entity_type' => User::class,
                    'entity_id' => $user->id,
                    'restricted_to_id' => null,
                    'restricted_to_type' => null,
                    'scope' => null,
                ]);
            }

            $migrator = new BouncerMigrator(User::class);

            // Act
            $migrator->migrate();

            // Assert - Verify role created only once
            expect(Models::role()->where('name', 'member')->count())->toBe(1);
            $role = Models::role()->where('name', 'member')->first();

            // Assert - Verify all three users have the role assignment
            $this->assertDatabaseHas('assigned_roles', [
                'role_id' => $role->id,
                'actor_id' => $user1->id,
                'actor_type' => $user1->getMorphClass(),
            ]);
            $this->assertDatabaseHas('assigned_roles', [
                'role_id' => $role->id,
                'actor_id' => $user2->id,
                'actor_type' => $user2->getMorphClass(),
            ]);
            $this->assertDatabaseHas('assigned_roles', [
                'role_id' => $role->id,
                'actor_id' => $user3->id,
                'actor_type' => $user3->getMorphClass(),
            ]);

            // Assert - Total assigned_roles count should be 3
            expect(DB::table('assigned_roles')->count())->toBe(3);
        });

        test('migrates user with multiple roles', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'Super User']);

            $adminRoleId = DB::table('bouncer_roles')->insertGetId([
                'name' => 'admin',
                'title' => 'Administrator',
                'scope' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $editorRoleId = DB::table('bouncer_roles')->insertGetId([
                'name' => 'editor',
                'title' => 'Editor',
                'scope' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $moderatorRoleId = DB::table('bouncer_roles')->insertGetId([
                'name' => 'moderator',
                'title' => 'Moderator',
                'scope' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // User has all three roles
            foreach ([$adminRoleId, $editorRoleId, $moderatorRoleId] as $roleId) {
                DB::table('bouncer_assigned_roles')->insert([
                    'role_id' => $roleId,
                    'entity_type' => User::class,
                    'entity_id' => $user->id,
                    'restricted_to_id' => null,
                    'restricted_to_type' => null,
                    'scope' => null,
                ]);
            }

            $migrator = new BouncerMigrator(User::class);

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
                'actor_id' => $user->id,
                'actor_type' => $user->getMorphClass(),
            ]);
            $this->assertDatabaseHas('assigned_roles', [
                'role_id' => $editorRole->id,
                'actor_id' => $user->id,
                'actor_type' => $user->getMorphClass(),
            ]);
            $this->assertDatabaseHas('assigned_roles', [
                'role_id' => $moderatorRole->id,
                'actor_id' => $user->id,
                'actor_type' => $user->getMorphClass(),
            ]);

            // Assert - Total assigned_roles count should be 3
            expect(DB::table('assigned_roles')->count())->toBe(3);
        });

        test('migrates soft-deleted user with role assignment', function (): void {
            // Arrange
            $user = SoftDeletesUser::query()->create(['name' => 'Deleted User']);
            $user->delete(); // Soft delete the user

            // Override config to use SoftDeletesUser class
            Config::set('warden.migrators.bouncer.entity_type', SoftDeletesUser::class);

            $adminRoleId = DB::table('bouncer_roles')->insertGetId([
                'name' => 'admin',
                'title' => 'Administrator',
                'scope' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('bouncer_assigned_roles')->insert([
                'role_id' => $adminRoleId,
                'entity_type' => SoftDeletesUser::class,
                'entity_id' => $user->id,
                'restricted_to_id' => null,
                'restricted_to_type' => null,
                'scope' => null,
            ]);

            $migrator = new BouncerMigrator(SoftDeletesUser::class);

            // Act
            $migrator->migrate();

            // Assert - Role should be migrated
            $this->assertDatabaseHas('roles', ['name' => 'admin']);

            // Assert - Soft-deleted user should have role assignment
            $this->assertDatabaseHas('assigned_roles', [
                'actor_id' => $user->id,
                'actor_type' => $user->getMorphClass(),
            ]);
        });
    });
});
