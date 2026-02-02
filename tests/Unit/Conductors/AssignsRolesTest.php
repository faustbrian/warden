<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Conductors\AssignsRoles;
use Cline\Warden\Database\Models;
use Cline\Warden\Database\Role;
use Tests\Fixtures\Models\User;

describe('AssignsRoles Conductor', function (): void {
    describe('Regression Tests', function (): void {
        test('assigns roles to correct users when using custom keymap configuration', function (): void {
            // This is a regression test for the bug where all role assignments
            // were incorrectly assigned to user ID 1 when using ULID/UUID keymaps.
            // The bug occurred because find() was used with keymap values instead
            // of where($keyColumn, $value)->first()

            // Arrange - Create multiple users with different IDs
            $user1 = User::query()->create(['name' => 'Alice']);
            $user2 = User::query()->create(['name' => 'Bob']);
            $user3 = User::query()->create(['name' => 'Charlie']);

            $adminRole = Role::query()->create(['name' => 'admin']);
            $editorRole = Role::query()->create(['name' => 'editor']);
            $viewerRole = Role::query()->create(['name' => 'viewer']);

            // Configure keymap to use 'id' column (simulating ULID scenario)
            // In production this would be 'ulid', but we use 'id' for testing
            Models::enforceMorphKeyMap([
                User::class => 'id',
            ]);

            // Act - Assign different roles to different users
            $conductor1 = new AssignsRoles('admin');
            $conductor1->to($user1);

            $conductor2 = new AssignsRoles('editor');
            $conductor2->to($user2);

            $conductor3 = new AssignsRoles('viewer');
            $conductor3->to($user3);

            // Assert - Verify each user received ONLY their assigned role
            // User 1 should have admin role
            $this->assertDatabaseHas('assigned_roles', [
                'role_id' => $adminRole->id,
                'actor_id' => $user1->id,
                'actor_type' => $user1->getMorphClass(),
            ]);

            // User 1 should NOT have editor or viewer roles
            $this->assertDatabaseMissing('assigned_roles', [
                'role_id' => $editorRole->id,
                'actor_id' => $user1->id,
            ]);
            $this->assertDatabaseMissing('assigned_roles', [
                'role_id' => $viewerRole->id,
                'actor_id' => $user1->id,
            ]);

            // User 2 should have editor role
            $this->assertDatabaseHas('assigned_roles', [
                'role_id' => $editorRole->id,
                'actor_id' => $user2->id,
                'actor_type' => $user2->getMorphClass(),
            ]);

            // User 2 should NOT have admin or viewer roles
            $this->assertDatabaseMissing('assigned_roles', [
                'role_id' => $adminRole->id,
                'actor_id' => $user2->id,
            ]);
            $this->assertDatabaseMissing('assigned_roles', [
                'role_id' => $viewerRole->id,
                'actor_id' => $user2->id,
            ]);

            // User 3 should have viewer role
            $this->assertDatabaseHas('assigned_roles', [
                'role_id' => $viewerRole->id,
                'actor_id' => $user3->id,
                'actor_type' => $user3->getMorphClass(),
            ]);

            // User 3 should NOT have admin or editor roles
            $this->assertDatabaseMissing('assigned_roles', [
                'role_id' => $adminRole->id,
                'actor_id' => $user3->id,
            ]);
            $this->assertDatabaseMissing('assigned_roles', [
                'role_id' => $editorRole->id,
                'actor_id' => $user3->id,
            ]);

            // Verify counts
            expect($user1->fresh()->roles)->toHaveCount(1);
            expect($user2->fresh()->roles)->toHaveCount(1);
            expect($user3->fresh()->roles)->toHaveCount(1);

            // Verify the bug doesn't occur: user 1 should NOT have all roles
            $user1Roles = $this->db()
                ->table('assigned_roles')
                ->where('actor_id', $user1->id)
                ->count();

            expect($user1Roles)->toBe(1)
                ->and($user1Roles)->not->toBe(3);
        });

        test('assigns multiple roles to multiple users correctly with keymap', function (): void {
            // Arrange
            $alice = User::query()->create(['name' => 'Alice']);
            $bob = User::query()->create(['name' => 'Bob']);

            Role::query()->create(['name' => 'admin']);
            Role::query()->create(['name' => 'editor']);

            // Configure keymap
            Models::enforceMorphKeyMap([
                User::class => 'id',
            ]);

            // Act - Assign multiple roles to multiple users
            $conductor = new AssignsRoles(['admin', 'editor']);
            $conductor->to([$alice, $bob]);

            // Assert
            expect($alice->fresh()->roles)->toHaveCount(2);
            expect($bob->fresh()->roles)->toHaveCount(2);

            // Verify total assignments is 4 (2 users × 2 roles), not 2
            $totalAssignments = $this->db()
                ->table('assigned_roles')
                ->count();

            expect($totalAssignments)->toBe(4);
        });
    });

    describe('Happy Paths', function (): void {
        test('assigns single role to single user', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'John Doe']);
            $conductor = new AssignsRoles('admin');

            // Act
            $result = $conductor->to($user);

            // Assert
            expect($result)->toBeTrue();
            expect($user->fresh()->roles)->toHaveCount(1);
            expect($user->fresh()->roles->first()->name)->toBe('admin');
        });

        test('assigns multiple roles to single user', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'Jane Doe']);
            $conductor = new AssignsRoles(['admin', 'editor', 'viewer']);

            // Act
            $conductor->to($user);

            // Assert
            expect($user->fresh()->roles)->toHaveCount(3);
            expect($user->fresh()->roles->pluck('name')->all())
                ->toContain('admin', 'editor', 'viewer');
        });

        test('creates non existent roles automatically', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'Bob Smith']);
            $conductor = new AssignsRoles('new-role');

            // Act
            $conductor->to($user);

            // Assert
            $this->assertDatabaseHas('roles', ['name' => 'new-role']);
            expect($user->fresh()->roles)->toHaveCount(1);
            expect($user->fresh()->roles->first()->name)->toBe('new-role');
        });
    });

    describe('Edge Cases', function (): void {
        test('skips duplicate role assignments', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'Alice']);
            $role = Role::query()->create(['name' => 'admin']);

            // Assign role once
            $conductor1 = new AssignsRoles('admin');
            $conductor1->to($user);

            // Act - Try to assign same role again
            $conductor2 = new AssignsRoles('admin');
            $conductor2->to($user);

            // Assert - Should still have only 1 role assignment
            expect($user->fresh()->roles)->toHaveCount(1);

            $assignmentCount = $this->db()
                ->table('assigned_roles')
                ->where('role_id', $role->id)
                ->where('actor_id', $user->id)
                ->count();

            expect($assignmentCount)->toBe(1);
        });

        test('handles empty role array gracefully', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'Charlie']);
            $conductor = new AssignsRoles([]);

            // Act
            $result = $conductor->to($user);

            // Assert
            expect($result)->toBeTrue();
            expect($user->fresh()->roles)->toHaveCount(0);
        });

        test('skips assignment when authority does not exist', function (): void {
            // Arrange
            $existingUser = User::query()->create(['name' => 'David']);
            $nonExistentUserId = config('warden.primary_key_type') === 'id' ? 999_999 : '99999999-9999-9999-9999-999999999999';
            $role = Role::query()->create(['name' => 'admin']);

            // Configure keymap to use 'id' column
            Models::enforceMorphKeyMap([
                User::class => 'id',
            ]);

            $conductor = new AssignsRoles('admin');

            // Act - Try to assign role to both existing and non-existent user
            $result = $conductor->to([$existingUser->id, $nonExistentUserId]);

            // Assert - Operation completes successfully
            expect($result)->toBeTrue();

            // Existing user should have the role
            expect($existingUser->fresh()->roles)->toHaveCount(1);
            expect($existingUser->fresh()->roles->first()->name)->toBe('admin');

            // Verify no assignment was created for the non-existent user
            $assignmentCount = $this->db()
                ->table('assigned_roles')
                ->where('role_id', $role->id)
                ->where('actor_id', $nonExistentUserId)
                ->count();

            expect($assignmentCount)->toBe(0);

            // Verify only one assignment exists total (for the existing user)
            $totalAssignments = $this->db()
                ->table('assigned_roles')
                ->where('role_id', $role->id)
                ->count();

            expect($totalAssignments)->toBe(1);
        });
    });
});
