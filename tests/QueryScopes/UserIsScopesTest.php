<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Models\User;

describe('User Is Query Scopes', function (): void {
    describe('Happy Paths', function (): void {
        test('filters users by single role assignment', function (): void {
            // Arrange
            $user1 = User::query()->create(['name' => 'Joseph']);
            $user2 = User::query()->create(['name' => 'Silber']);

            $user1->assign('reader');
            $user2->assign('subscriber');

            // Act
            $users = User::whereIs('reader')->get();

            // Assert
            expect($users)->toHaveCount(1);
            expect($users->first()->name)->toEqual('Joseph');
        });

        test('filters users having any of multiple roles', function (): void {
            // Arrange
            $user1 = User::query()->create(['name' => 'Joseph']);
            $user2 = User::query()->create(['name' => 'Silber']);

            $user1->assign('reader');
            $user2->assign('subscriber');

            // Act
            $users = User::whereIs('admin', 'subscriber')->get();

            // Assert
            expect($users)->toHaveCount(1);
            expect($users->first()->name)->toEqual('Silber');
        });

        test('filters users having all of multiple roles', function (): void {
            // Arrange
            $user1 = User::query()->create(['name' => 'Joseph']);
            $user2 = User::query()->create(['name' => 'Silber']);

            $user1->assign('reader')->assign('subscriber');
            $user2->assign('subscriber');

            // Act
            $users = User::whereIsAll('subscriber', 'reader')->get();

            // Assert
            expect($users)->toHaveCount(1);
            expect($users->first()->name)->toEqual('Joseph');
        });
    });

    describe('Sad Paths', function (): void {
        test('filters users not having specific role', function (): void {
            // Arrange
            $user1 = User::query()->create();
            $user2 = User::query()->create();
            $user3 = User::query()->create();

            $user1->assign('admin');
            $user2->assign('editor');
            $user3->assign('subscriber');

            // Act
            $users = User::whereIsNot('admin')->get();

            // Assert
            expect($users)->toHaveCount(2);
            expect($users->contains($user1))->toBeFalse();
            expect($users->contains($user2))->toBeTrue();
            expect($users->contains($user3))->toBeTrue();
        });

        test('filters users not having any of multiple roles', function (): void {
            // Arrange
            $user1 = User::query()->create();
            $user2 = User::query()->create();
            $user3 = User::query()->create();

            $user1->assign('admin');
            $user2->assign('editor');
            $user3->assign('subscriber');

            // Act
            $users = User::whereIsNot('superadmin', 'editor', 'subscriber')->get();

            // Assert
            expect($users)->toHaveCount(1);
            expect($users->contains($user1))->toBeTrue();
            expect($users->contains($user2))->toBeFalse();
            expect($users->contains($user3))->toBeFalse();
        });
    });
});

/**
 * @author Brian Faust <brian@cline.sh>
 */
