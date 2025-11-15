<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Database\Queries\Abilities;
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
});
