<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Database\Models;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\TestsClipboards;
use Tests\Fixtures\Models\User;
use Tests\Fixtures\Models\UserWithSoftDeletes;

uses(TestsClipboards::class);

describe('HasRolesAndAbilitiesTrait', function (): void {
    describe('Happy Paths', function (): void {
        test('retrieves all allowed abilities for user', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();
            $warden->allow('admin')->to('edit-site');
            $warden->allow($user)->to('create-posts');
            $warden->assign('admin')->to($user);
            $warden->forbid($user)->to('create-sites');
            $warden->allow('editor')->to('edit-posts');

            // Act
            $abilities = $user->getAbilities()->pluck('name')->sort()->values()->all();

            // Assert
            expect($abilities)->toEqual(['create-posts', 'edit-site']);
        })->with('bouncerProvider');

        test('retrieves all forbidden abilities for user', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();
            $warden->forbid('admin')->to('edit-site');
            $warden->forbid($user)->to('create-posts');
            $warden->assign('admin')->to($user);
            $warden->allow($user)->to('create-sites');
            $warden->forbid('editor')->to('edit-posts');

            // Act
            $forbiddenAbilities = $user->getForbiddenAbilities()->pluck('name')->sort()->values()->all();

            // Assert
            expect($forbiddenAbilities)->toEqual(['create-posts', 'edit-site']);
        })->with('bouncerProvider');

        test('gives and removes simple abilities successfully', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            // Act
            $user->allow('edit-site');

            // Assert
            expect($warden->can('edit-site'))->toBeTrue();

            // Act - Remove
            $user->disallow('edit-site');

            // Assert
            expect($warden->cannot('edit-site'))->toBeTrue();
        })->with('bouncerProvider');

        test('gives and removes model abilities successfully', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            // Act
            $user->allow('delete', $user);

            // Assert
            expect($warden->cannot('delete'))->toBeTrue();
            expect($warden->cannot('delete', User::class))->toBeTrue();
            expect($warden->can('delete', $user))->toBeTrue();

            // Act - Remove
            $user->disallow('delete', $user);

            // Assert
            expect($warden->cannot('delete', $user))->toBeTrue();
        })->with('bouncerProvider');

        test('gives and removes ability for everything successfully', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            // Act
            $user->allow()->everything();

            // Assert
            expect($warden->can('delete'))->toBeTrue();
            expect($warden->can('delete', '*'))->toBeTrue();
            expect($warden->can('*', '*'))->toBeTrue();

            // Act - Remove
            $user->disallow()->everything();

            // Assert
            expect($warden->cannot('delete'))->toBeTrue();
            expect($warden->cannot('delete', '*'))->toBeTrue();
            expect($warden->cannot('*', '*'))->toBeTrue();
        })->with('bouncerProvider');

        test('forbids and unforbids simple abilities successfully', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();
            $user->allow('edit-site');

            // Act
            $user->forbid('edit-site');

            // Assert
            expect($warden->cannot('edit-site'))->toBeTrue();

            // Act - Unforbid
            $user->unforbid('edit-site');

            // Assert
            expect($warden->can('edit-site'))->toBeTrue();
        })->with('bouncerProvider');

        test('forbids and unforbids model abilities successfully', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();
            $user->allow('delete', $user);

            // Act
            $user->forbid('delete', $user);

            // Assert
            expect($warden->cannot('delete', $user))->toBeTrue();

            // Act - Unforbid
            $user->unforbid('delete', $user);

            // Assert
            expect($warden->can('delete', $user))->toBeTrue();
        })->with('bouncerProvider');

        test('forbids and unforbids everything successfully', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();
            $user->allow('delete', $user);

            // Act
            $user->forbid()->everything();

            // Assert
            expect($warden->cannot('delete', $user))->toBeTrue();

            // Act - Unforbid
            $user->unforbid()->everything();

            // Assert
            expect($warden->can('delete', $user))->toBeTrue();
        })->with('bouncerProvider');

        test('assigns and retracts roles successfully', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();
            $warden->allow('admin')->to('edit-site');

            // Act
            $user->assign('admin');

            // Assert
            expect($user->getRoles()->all())->toEqual(['admin']);
            expect($warden->can('edit-site'))->toBeTrue();

            // Act - Retract
            $user->retract('admin');

            // Assert
            expect($user->getRoles()->all())->toEqual([]);
            expect($warden->cannot('edit-site'))->toBeTrue();
        })->with('bouncerProvider');

        test('checks if user has single role successfully', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            // Assert - Before assignment
            expect($user->isNotAn('admin'))->toBeTrue();
            expect($user->isAn('admin'))->toBeFalse();
            expect($user->isNotA('admin'))->toBeTrue();
            expect($user->isA('admin'))->toBeFalse();

            // Act
            $user->assign('admin');

            // Assert - After assignment
            expect($user->isAn('admin'))->toBeTrue();
            expect($user->isAn('editor'))->toBeFalse();
            expect($user->isNotAn('admin'))->toBeFalse();
            expect($user->isNotAn('editor'))->toBeTrue();
        })->with('bouncerProvider');

        test('checks if user has multiple roles successfully', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            // Assert - Before assignment
            expect($user->isAn('admin', 'editor'))->toBeFalse();

            // Act
            $user->assign('moderator');
            $user->assign('editor');

            // Assert - After assignment
            expect($user->isAn('admin', 'moderator'))->toBeTrue();
            expect($user->isAll('editor', 'moderator'))->toBeTrue();
            expect($user->isAll('moderator', 'admin'))->toBeFalse();
        })->with('bouncerProvider');
    });

    describe('Edge Cases', function (): void {
        test('deleting model removes permissions pivot table records', function (): void {
            // Arrange
            $warden = $this->bouncer();
            $user1 = User::query()->create();
            $user2 = User::query()->create();
            $warden->allow($user1)->everything();
            $warden->allow($user2)->everything();

            // Assert - Before delete
            expect($this->db()->table('permissions')->count())->toEqual(2);

            // Act
            $user1->delete();

            // Assert - After delete
            expect($this->db()->table('permissions')->count())->toEqual(1);
        });

        test('soft deleting model persists permissions pivot table records', function (): void {
            // Arrange
            Models::setUsersModel(UserWithSoftDeletes::class);
            $warden = $this->bouncer();
            $user1 = UserWithSoftDeletes::query()->create();
            $user2 = UserWithSoftDeletes::query()->create();
            $warden->allow($user1)->everything();
            $warden->allow($user2)->everything();

            // Assert - Before soft delete
            expect($this->db()->table('permissions')->count())->toEqual(2);

            // Act
            $user1->delete();

            // Assert - After soft delete
            expect($this->db()->table('permissions')->count())->toEqual(2);
        });

        test('deleting model removes assigned roles pivot table records', function (): void {
            // Arrange
            $warden = $this->bouncer();
            $user1 = User::query()->create();
            $user2 = User::query()->create();
            $warden->assign('admin')->to($user1);
            $warden->assign('admin')->to($user2);

            // Assert - Before delete
            expect($this->db()->table('assigned_roles')->count())->toEqual(2);

            // Act
            $user1->delete();

            // Assert - After delete
            expect($this->db()->table('assigned_roles')->count())->toEqual(1);
        });

        test('soft deleting model persists assigned roles pivot table records', function (): void {
            // Arrange
            Models::setUsersModel(UserWithSoftDeletes::class);
            $warden = $this->bouncer();
            $user1 = UserWithSoftDeletes::query()->create();
            $user2 = UserWithSoftDeletes::query()->create();
            $warden->assign('admin')->to($user1);
            $warden->assign('admin')->to($user2);

            // Assert - Before soft delete
            expect($this->db()->table('assigned_roles')->count())->toEqual(2);

            // Act
            $user1->delete();

            // Assert - After soft delete
            expect($this->db()->table('assigned_roles')->count())->toEqual(2);
        });
    });
});
