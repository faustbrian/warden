<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Conductors\RemovesRoles;
use Cline\Warden\Database\Role;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Models\User;

describe('RemovesRoles Conductor', function (): void {
    describe('Happy Paths', function (): void {
        test('removes valid roles but skips non existent roles in mixed array', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'Alice Johnson']);
            $adminRole = Role::query()->create(['name' => 'admin']);
            $editorRole = Role::query()->create(['name' => 'editor']);

            // Assign both roles to the user
            $this->db()
                ->table('assigned_roles')
                ->insert([
                    [
                        'role_id' => $adminRole->id,
                        'actor_id' => $user->id,
                        'actor_type' => $user->getMorphClass(),
                    ],
                    [
                        'role_id' => $editorRole->id,
                        'actor_id' => $user->id,
                        'actor_type' => $user->getMorphClass(),
                    ],
                ]);

            // Remove admin role and a non-existent role
            $conductor = new RemovesRoles(['admin', 'non-existent-role']);

            // Act
            $conductor->from($user);

            // Assert - Verify admin role was removed
            $this->assertDatabaseMissing('assigned_roles', [
                'role_id' => $adminRole->id,
                'actor_id' => $user->id,
            ]);

            // Verify editor role still exists
            $this->assertDatabaseHas('assigned_roles', [
                'role_id' => $editorRole->id,
                'actor_id' => $user->id,
                'actor_type' => $user->getMorphClass(),
            ]);

            // Verify user has exactly 1 role remaining
            expect($user->fresh()->roles)->toHaveCount(1);
        });
    });

    describe('Edge Cases', function (): void {
        test('returns early when removing non existent role names', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'John Doe']);
            $conductor = new RemovesRoles(['non-existent-role', 'another-fake-role']);

            // Act
            $conductor->from($user);

            // Assert - Verify no roles were removed (user has no roles to begin with)
            expect($user->fresh()->roles)->toHaveCount(0);

            // Verify no database records exist for these role names
            $this->assertDatabaseMissing('roles', ['name' => 'non-existent-role']);
            $this->assertDatabaseMissing('roles', ['name' => 'another-fake-role']);

            // Verify no role assignments exist
            $this->assertDatabaseMissing('assigned_roles', ['actor_id' => $user->id]);
        });

        test('returns early when removing empty array of roles', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'Jane Doe']);
            $conductor = new RemovesRoles([]);

            // Act
            $conductor->from($user);

            // Assert - Verify no roles were removed
            expect($user->fresh()->roles)->toHaveCount(0);

            // Verify no role assignments exist
            $this->assertDatabaseMissing('assigned_roles', ['actor_id' => $user->id]);
        });

        test('returns early when removing non existent role with existing roles assigned', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'Bob Smith']);
            $existingRole = Role::query()->create(['name' => 'admin']);

            // Assign the existing role to the user
            $this->db()
                ->table('assigned_roles')
                ->insert([
                    'role_id' => $existingRole->id,
                    'actor_id' => $user->id,
                    'actor_type' => $user->getMorphClass(),
                ]);

            $conductor = new RemovesRoles(['non-existent-role']);

            // Act
            $conductor->from($user);

            // Assert - Verify the existing role was NOT removed
            $this->assertDatabaseHas('assigned_roles', [
                'role_id' => $existingRole->id,
                'actor_id' => $user->id,
                'actor_type' => $user->getMorphClass(),
            ]);

            // Verify user still has 1 role
            expect($user->fresh()->roles)->toHaveCount(1);
        });
    });
});
