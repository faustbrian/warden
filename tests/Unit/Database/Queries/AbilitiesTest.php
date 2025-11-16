<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Database\Models;
use Cline\Warden\Database\Queries\Abilities;
use Cline\Warden\Database\Role;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Models\User;

describe('Abilities Query', function (): void {
    describe('Happy Paths', function (): void {
        test('returns forbidden abilities for authority', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'Test User']);
            $warden = $this->bouncer($user);

            $warden->forbid($user)->to('delete-posts');
            $warden->allow($user)->to('create-posts');

            // Act
            $forbiddenAbilities = Abilities::forbiddenForAuthority($user)->get();

            // Assert
            expect($forbiddenAbilities->count())->toBeGreaterThan(0);
            expect($forbiddenAbilities->contains('name', 'delete-posts'))->toBeTrue();
            expect($forbiddenAbilities->contains('name', 'create-posts'))->toBeFalse();
        });

        test('queries forbidden abilities through roles', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'Test User']);
            $warden = $this->bouncer($user);

            $warden->forbid('editor')->to('delete-site');
            $warden->assign('editor')->to($user);

            // Act
            $forbiddenAbilities = Abilities::forbiddenForAuthority($user)->get();

            // Assert
            expect($forbiddenAbilities->count())->toBeGreaterThan(0);
            expect($forbiddenAbilities->contains('name', 'delete-site'))->toBeTrue();
        });

        test('queries allowed abilities for authority', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'Test User']);
            $warden = $this->bouncer($user);

            $warden->allow($user)->to('edit-posts');
            $warden->forbid($user)->to('delete-posts');

            // Act
            $allowedAbilities = Abilities::forAuthority($user, true)->get();

            // Assert
            expect($allowedAbilities->count())->toBeGreaterThan(0);
            expect($allowedAbilities->contains('name', 'edit-posts'))->toBeTrue();
            expect($allowedAbilities->contains('name', 'delete-posts'))->toBeFalse();
        });

        test('queries abilities granted to everyone', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'Test User']);
            $warden = $this->bouncer($user);

            $warden->allowEveryone()->to('view-public-posts');

            // Act
            $abilities = Abilities::forAuthority($user, true)->get();

            // Assert
            expect($abilities->count())->toBeGreaterThan(0);
            expect($abilities->contains('name', 'view-public-posts'))->toBeTrue();
        });

        test('combines abilities from roles direct and everyone', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'Test User']);
            $warden = $this->bouncer($user);

            $warden->allow('editor')->to('edit-posts');
            $warden->assign('editor')->to($user);
            $warden->allow($user)->to('create-posts');
            $warden->allowEveryone()->to('view-posts');

            // Act
            $abilities = Abilities::forAuthority($user, true)->get();

            // Assert
            expect($abilities->count())->toBeGreaterThan(0);
            expect($abilities->contains('name', 'edit-posts'))->toBeTrue();
            expect($abilities->contains('name', 'create-posts'))->toBeTrue();
            expect($abilities->contains('name', 'view-posts'))->toBeTrue();
        });
    });

    describe('Edge Cases', function (): void {
        test('returns empty collection when no forbidden abilities exist', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'Test User']);
            $warden = $this->bouncer($user);

            $warden->allow($user)->to('create-posts');

            // Act
            $forbiddenAbilities = Abilities::forbiddenForAuthority($user)->get();

            // Assert
            expect($forbiddenAbilities)->toHaveCount(0);
        });
    });

    describe('Regression Tests - Keymap Support', function (): void {
        test('getAuthorityRoleConstraint uses keymap column for joins and comparisons', function (): void {
            // Arrange - Configure keymap
            Models::enforceMorphKeyMap([
                User::class => 'id',
                Role::class => 'id',
            ]);

            // Create users with specific IDs
            $user1 = User::query()->create(['name' => 'Alice', 'id' => 100]);
            $user2 = User::query()->create(['name' => 'Bob', 'id' => 200]);

            $warden = $this->bouncer($user1);

            // Assign roles and grant abilities
            $warden->assign('admin')->to($user1);
            $warden->assign('editor')->to($user2);

            $warden->allow('admin')->to('edit-posts');
            $warden->allow('admin')->to('delete-posts');
            $warden->allow('editor')->to('edit-posts');

            // Act - Query abilities through authority's roles
            $user1Abilities = Abilities::forAuthority($user1)->get();
            $user2Abilities = Abilities::forAuthority($user2)->get();

            // Assert - User 1 should have both abilities from admin role
            expect($user1Abilities)->toHaveCount(2);
            expect($user1Abilities->pluck('name')->toArray())->toContain('edit-posts');
            expect($user1Abilities->pluck('name')->toArray())->toContain('delete-posts');

            // Assert - User 2 should only have edit ability from editor role
            expect($user2Abilities)->toHaveCount(1);
            expect($user2Abilities->pluck('name')->toArray())->toContain('edit-posts');
            expect($user2Abilities->pluck('name')->toArray())->not->toContain('delete-posts');
        });

        test('getAuthorityConstraint uses keymap value for direct ability assignments', function (): void {
            // Arrange - Configure keymap
            Models::enforceMorphKeyMap([
                User::class => 'id',
            ]);

            // Create users with specific IDs
            $user1 = User::query()->create(['name' => 'Alice', 'id' => 100]);
            $user2 = User::query()->create(['name' => 'Bob', 'id' => 200]);

            $warden = $this->bouncer($user1);

            // Grant abilities directly to users (not through roles)
            $warden->allow($user1)->to('edit-posts');
            $warden->allow($user1)->to('delete-posts');
            $warden->allow($user2)->to('edit-posts');

            // Act - Query abilities for each user
            $user1Abilities = Abilities::forAuthority($user1)->get();
            $user2Abilities = Abilities::forAuthority($user2)->get();

            // Assert - Verify each user gets ONLY their abilities (not other users')
            expect($user1Abilities)->toHaveCount(2);
            expect($user1Abilities->pluck('name')->toArray())->toContain('edit-posts');
            expect($user1Abilities->pluck('name')->toArray())->toContain('delete-posts');

            expect($user2Abilities)->toHaveCount(1);
            expect($user2Abilities->pluck('name')->toArray())->toContain('edit-posts');
            expect($user2Abilities->pluck('name')->toArray())->not->toContain('delete-posts');
        });

        test('prevents ability leakage between users with custom keymap configuration', function (): void {
            // Arrange - This tests the critical bug fix
            Models::enforceMorphKeyMap([
                User::class => 'id',
            ]);

            $alice = User::query()->create(['name' => 'Alice', 'id' => 1]);
            $bob = User::query()->create(['name' => 'Bob', 'id' => 2]);
            $charlie = User::query()->create(['name' => 'Charlie', 'id' => 3]);

            $warden = $this->bouncer($alice);

            // Only Alice should have this ability
            $warden->allow($alice)->to('view-secrets');

            // Act - Query abilities for all users
            $aliceAbilities = Abilities::forAuthority($alice)->get();
            $bobAbilities = Abilities::forAuthority($bob)->get();
            $charlieAbilities = Abilities::forAuthority($charlie)->get();

            // Assert - Without the fix, all users would incorrectly get Alice's ability
            expect($aliceAbilities->pluck('name')->toArray())->toContain('view-secrets');
            expect($bobAbilities->pluck('name')->toArray())->not->toContain('view-secrets');
            expect($charlieAbilities->pluck('name')->toArray())->not->toContain('view-secrets');
        });
    });
});
