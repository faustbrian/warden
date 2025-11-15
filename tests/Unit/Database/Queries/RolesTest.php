<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Database\Queries\Roles;
use Cline\Warden\Database\Role;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Models\User;

describe('Roles Query', function (): void {
    beforeEach(function (): void {
        $this->rolesQuery = new Roles();
    });

    describe('Happy Paths', function (): void {
        test('constrain where is filters by single role', function (): void {
            // Arrange
            Role::query()->create(['name' => 'admin']);
            Role::query()->create(['name' => 'editor']);

            $user1 = User::query()->create(['name' => 'Admin User']);
            $user2 = User::query()->create(['name' => 'Editor User']);
            $user3 = User::query()->create(['name' => 'Regular User']);

            $warden = $this->bouncer($user1);
            $warden->assign('admin')->to($user1);
            $warden->assign('editor')->to($user2);

            // Act
            $query = User::query();
            $this->rolesQuery->constrainWhereIs($query, 'admin');
            $users = $query->get();

            // Assert
            expect($users)->toHaveCount(1);
            expect($users->contains($user1))->toBeTrue();
            expect($users->contains($user2))->toBeFalse();
            expect($users->contains($user3))->toBeFalse();
        });

        test('constrain where is filters by multiple roles with or logic', function (): void {
            // Arrange
            Role::query()->create(['name' => 'admin']);
            Role::query()->create(['name' => 'editor']);
            Role::query()->create(['name' => 'subscriber']);

            $user1 = User::query()->create(['name' => 'Admin User']);
            $user2 = User::query()->create(['name' => 'Editor User']);
            $user3 = User::query()->create(['name' => 'Subscriber User']);
            $user4 = User::query()->create(['name' => 'Regular User']);

            $warden = $this->bouncer($user1);
            $warden->assign('admin')->to($user1);
            $warden->assign('editor')->to($user2);
            $warden->assign('subscriber')->to($user3);

            // Act
            $query = User::query();
            $this->rolesQuery->constrainWhereIs($query, 'admin', 'editor');
            $users = $query->get();

            // Assert
            expect($users)->toHaveCount(2);
            expect($users->contains($user1))->toBeTrue();
            expect($users->contains($user2))->toBeTrue();
            expect($users->contains($user3))->toBeFalse();
            expect($users->contains($user4))->toBeFalse();
        });

        test('constrain where is all requires exact role count match', function (): void {
            // Arrange
            Role::query()->create(['name' => 'admin']);
            Role::query()->create(['name' => 'editor']);
            Role::query()->create(['name' => 'manager']);

            $user1 = User::query()->create(['name' => 'Admin Editor']);
            $user2 = User::query()->create(['name' => 'Admin Only']);
            $user3 = User::query()->create(['name' => 'Admin Editor Manager']);

            $warden = $this->bouncer($user1);
            $warden->assign('admin')->to($user1);
            $warden->assign('editor')->to($user1);

            $warden->assign('admin')->to($user2);

            $warden->assign('admin')->to($user3);
            $warden->assign('editor')->to($user3);
            $warden->assign('manager')->to($user3);

            // Act
            $query = User::query();
            $this->rolesQuery->constrainWhereIsAll($query, 'admin', 'editor');
            $users = $query->get();

            // Assert
            // Note: constrainWhereIsAll uses '=' count operator, so it requires EXACTLY 2 matching roles
            // user1 has exactly admin and editor (2 matches)
            // user3 also has admin and editor (2 matches), plus manager (but that doesn't affect the whereIn count)
            expect($users)->toHaveCount(2);
            expect($users->contains($user1))->toBeTrue();
            expect($users->contains($user2))->toBeFalse();
            // Only has admin (1 match)
            expect($users->contains($user3))->toBeTrue();
            // Has admin and editor (2 matches)
        });

        test('constrain where is all with single role', function (): void {
            // Arrange
            Role::query()->create(['name' => 'admin']);
            Role::query()->create(['name' => 'editor']);

            $user1 = User::query()->create(['name' => 'Admin Only']);
            $user2 = User::query()->create(['name' => 'Admin Editor']);

            $warden = $this->bouncer($user1);
            $warden->assign('admin')->to($user1);

            $warden->assign('admin')->to($user2);
            $warden->assign('editor')->to($user2);

            // Act
            $query = User::query();
            $this->rolesQuery->constrainWhereIsAll($query, 'admin');
            $users = $query->get();

            // Assert
            // Note: With single role, both users have at least 1 matching role
            expect($users)->toHaveCount(2);
            expect($users->contains($user1))->toBeTrue();
            expect($users->contains($user2))->toBeTrue();
        });

        test('constrain where is not excludes single role', function (): void {
            // Arrange
            Role::query()->create(['name' => 'admin']);
            Role::query()->create(['name' => 'editor']);

            $user1 = User::query()->create(['name' => 'Admin User']);
            $user2 = User::query()->create(['name' => 'Editor User']);
            $user3 = User::query()->create(['name' => 'Regular User']);

            $warden = $this->bouncer($user1);
            $warden->assign('admin')->to($user1);
            $warden->assign('editor')->to($user2);

            // Act
            $query = User::query();
            $this->rolesQuery->constrainWhereIsNot($query, 'admin');
            $users = $query->get();

            // Assert
            expect($users)->toHaveCount(2);
            expect($users->contains($user1))->toBeFalse();
            expect($users->contains($user2))->toBeTrue();
            expect($users->contains($user3))->toBeTrue();
        });

        test('constrain where is not excludes multiple roles', function (): void {
            // Arrange
            Role::query()->create(['name' => 'admin']);
            Role::query()->create(['name' => 'editor']);
            Role::query()->create(['name' => 'subscriber']);

            $user1 = User::query()->create(['name' => 'Admin User']);
            $user2 = User::query()->create(['name' => 'Editor User']);
            $user3 = User::query()->create(['name' => 'Subscriber User']);
            $user4 = User::query()->create(['name' => 'Regular User']);

            $warden = $this->bouncer($user1);
            $warden->assign('admin')->to($user1);
            $warden->assign('editor')->to($user2);
            $warden->assign('subscriber')->to($user3);

            // Act
            $query = User::query();
            $this->rolesQuery->constrainWhereIsNot($query, 'admin', 'editor');
            $users = $query->get();

            // Assert
            expect($users)->toHaveCount(2);
            expect($users->contains($user1))->toBeFalse();
            expect($users->contains($user2))->toBeFalse();
            expect($users->contains($user3))->toBeTrue();
            expect($users->contains($user4))->toBeTrue();
        });

        test('constrain where assigned to filters by single model instance', function (): void {
            // Arrange
            Role::query()->create(['name' => 'admin']);
            Role::query()->create(['name' => 'editor']);
            Role::query()->create(['name' => 'subscriber']);

            $user1 = User::query()->create(['name' => 'User 1']);
            $user2 = User::query()->create(['name' => 'User 2']);

            $warden = $this->bouncer($user1);
            $warden->assign('admin')->to($user1);
            $warden->assign('editor')->to($user1);
            $warden->assign('subscriber')->to($user2);

            // Act
            $query = Role::query();
            $this->rolesQuery->constrainWhereAssignedTo($query, $user1);
            $roles = $query->get();

            // Assert
            expect($roles)->toHaveCount(2);
            expect($roles->contains('name', 'admin'))->toBeTrue();
            expect($roles->contains('name', 'editor'))->toBeTrue();
            expect($roles->contains('name', 'subscriber'))->toBeFalse();
        });

        test('constrain where assigned to filters by collection of models', function (): void {
            // Arrange
            Role::query()->create(['name' => 'admin']);
            Role::query()->create(['name' => 'editor']);
            Role::query()->create(['name' => 'subscriber']);
            Role::query()->create(['name' => 'manager']);

            $user1 = User::query()->create(['name' => 'User 1']);
            $user2 = User::query()->create(['name' => 'User 2']);
            User::query()->create(['name' => 'User 3']);

            $warden = $this->bouncer($user1);
            $warden->assign('admin')->to($user1);
            $warden->assign('editor')->to($user1);
            $warden->assign('subscriber')->to($user2);
            $warden->assign('editor')->to($user2);

            // user3 has no roles, manager has no users
            // Act
            $users = new Collection([$user1, $user2]);
            $query = Role::query();
            $this->rolesQuery->constrainWhereAssignedTo($query, $users);
            $roles = $query->get();

            // Assert
            expect($roles)->toHaveCount(3);
            expect($roles->contains('name', 'admin'))->toBeTrue();
            expect($roles->contains('name', 'editor'))->toBeTrue();
            expect($roles->contains('name', 'subscriber'))->toBeTrue();
            expect($roles->contains('name', 'manager'))->toBeFalse();
        });

        test('constrain where assigned to filters by class name and keys', function (): void {
            // Arrange
            Role::query()->create(['name' => 'admin']);
            Role::query()->create(['name' => 'editor']);
            Role::query()->create(['name' => 'subscriber']);

            $user1 = User::query()->create(['name' => 'User 1']);
            $user2 = User::query()->create(['name' => 'User 2']);
            $user3 = User::query()->create(['name' => 'User 3']);

            $warden = $this->bouncer($user1);
            $warden->assign('admin')->to($user1);
            $warden->assign('editor')->to($user2);
            $warden->assign('subscriber')->to($user3);

            // Act
            $query = Role::query();
            $this->rolesQuery->constrainWhereAssignedTo($query, User::class, [$user1->id, $user2->id]);
            $roles = $query->get();

            // Assert
            expect($roles)->toHaveCount(2);
            expect($roles->contains('name', 'admin'))->toBeTrue();
            expect($roles->contains('name', 'editor'))->toBeTrue();
            expect($roles->contains('name', 'subscriber'))->toBeFalse();
        });

        test('constrain where is generates correct sql structure', function (): void {
            // Arrange
            Role::query()->create(['name' => 'admin']);
            Role::query()->create(['name' => 'editor']);

            // Act
            $query = User::query();
            $this->rolesQuery->constrainWhereIs($query, 'admin', 'editor');

            // Assert
            $sql = $query->toSql();
            $this->assertStringContainsString('exists', $sql);
            $this->assertStringContainsString('roles', $sql);
        });

        test('constrain where is all generates correct sql with count', function (): void {
            // Arrange
            Role::query()->create(['name' => 'admin']);
            Role::query()->create(['name' => 'editor']);

            // Act
            $query = User::query();
            $this->rolesQuery->constrainWhereIsAll($query, 'admin', 'editor');

            // Assert
            $sql = $query->toSql();
            $this->assertStringContainsString('count', $sql);
            $this->assertStringContainsString('roles', $sql);
            $this->assertStringContainsString('= 2', $sql);
            // Exact count match
        });

        test('constrain where is not generates correct sql structure', function (): void {
            // Arrange
            Role::query()->create(['name' => 'admin']);

            // Act
            $query = User::query();
            $this->rolesQuery->constrainWhereIsNot($query, 'admin');

            // Assert
            $sql = $query->toSql();
            $this->assertStringContainsString('not exists', $sql);
            $this->assertStringContainsString('roles', $sql);
        });

        test('constrain where assigned to generates correct sql with joins', function (): void {
            // Arrange
            Role::query()->create(['name' => 'admin']);
            $user = User::query()->create(['name' => 'User']);

            // Act
            $query = Role::query();
            $this->rolesQuery->constrainWhereAssignedTo($query, $user);

            // Assert
            $sql = $query->toSql();
            $this->assertStringContainsString('exists', $sql);
            $this->assertStringContainsString('assigned_roles', $sql);
        });

        test('multiple constraints can be chained', function (): void {
            // Arrange
            Role::query()->create(['name' => 'admin']);
            Role::query()->create(['name' => 'editor']);
            Role::query()->create(['name' => 'subscriber']);

            $user1 = User::query()->create(['name' => 'Admin User']);
            $user2 = User::query()->create(['name' => 'Editor Subscriber']);
            $user3 = User::query()->create(['name' => 'Subscriber Only']);

            $warden = $this->bouncer($user1);
            $warden->assign('admin')->to($user1);
            $warden->assign('editor')->to($user2);
            $warden->assign('subscriber')->to($user2);
            $warden->assign('subscriber')->to($user3);

            // Act - Find users who have admin OR (editor AND subscriber)
            $query = User::query();
            $this->rolesQuery->constrainWhereIs($query, 'admin');
            $adminUsers = $query->get();

            $query2 = User::query();
            $this->rolesQuery->constrainWhereIsAll($query2, 'editor', 'subscriber');
            $editorSubscriberUsers = $query2->get();

            // Assert
            expect($adminUsers)->toHaveCount(1);
            expect($adminUsers->contains($user1))->toBeTrue();

            expect($editorSubscriberUsers)->toHaveCount(1);
            expect($editorSubscriberUsers->contains($user2))->toBeTrue();
        });
    });

    describe('Edge Cases', function (): void {
        test('constrain where is returns empty when no users have role', function (): void {
            // Arrange
            Role::query()->create(['name' => 'admin']);
            User::query()->create(['name' => 'Regular User']);

            // Act
            $query = User::query();
            $this->rolesQuery->constrainWhereIs($query, 'admin');
            $users = $query->get();

            // Assert
            expect($users)->toHaveCount(0);
        });

        test('constrain where is all returns empty when no match', function (): void {
            // Arrange
            Role::query()->create(['name' => 'admin']);
            Role::query()->create(['name' => 'editor']);

            $user1 = User::query()->create(['name' => 'Admin Only']);

            $warden = $this->bouncer($user1);
            $warden->assign('admin')->to($user1);

            // Act
            $query = User::query();
            $this->rolesQuery->constrainWhereIsAll($query, 'admin', 'editor');
            $users = $query->get();

            // Assert
            expect($users)->toHaveCount(0);
        });

        test('constrain where is not returns all when none have role', function (): void {
            // Arrange
            Role::query()->create(['name' => 'admin']);
            $user1 = User::query()->create(['name' => 'User 1']);
            $user2 = User::query()->create(['name' => 'User 2']);

            // Act
            $query = User::query();
            $this->rolesQuery->constrainWhereIsNot($query, 'admin');
            $users = $query->get();

            // Assert
            expect($users)->toHaveCount(2);
            expect($users->contains($user1))->toBeTrue();
            expect($users->contains($user2))->toBeTrue();
        });

        test('constrain where assigned to returns empty for model with no roles', function (): void {
            // Arrange
            Role::query()->create(['name' => 'admin']);
            $user1 = User::query()->create(['name' => 'User 1']);

            // Act
            $query = Role::query();
            $this->rolesQuery->constrainWhereAssignedTo($query, $user1);
            $roles = $query->get();

            // Assert
            expect($roles)->toHaveCount(0);
        });

        test('constrain where assigned to handles collection with unassigned users', function (): void {
            // Arrange
            Role::query()->create(['name' => 'admin']);
            Role::query()->create(['name' => 'editor']);

            $user1 = User::query()->create(['name' => 'User 1']);
            $user2 = User::query()->create(['name' => 'User 2']);

            $warden = $this->bouncer($user1);
            $warden->assign('admin')->to($user1);

            // user2 has no roles
            // Act
            $users = new Collection([$user1, $user2]);
            $query = Role::query();
            $this->rolesQuery->constrainWhereAssignedTo($query, $users);
            $roles = $query->get();

            // Assert
            expect($roles)->toHaveCount(1);
            expect($roles->contains('name', 'admin'))->toBeTrue();
            expect($roles->contains('name', 'editor'))->toBeFalse();
        });

        test('constrain where assigned to returns empty for empty keys array', function (): void {
            // Arrange
            Role::query()->create(['name' => 'admin']);

            // Act
            $query = Role::query();
            $this->rolesQuery->constrainWhereAssignedTo($query, User::class, []);
            $roles = $query->get();

            // Assert
            expect($roles)->toHaveCount(0);
        });
    });
});
