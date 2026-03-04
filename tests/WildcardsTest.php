<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\TestsClipboards;
use Tests\Fixtures\Models\Account;
use Tests\Fixtures\Models\User;

uses(TestsClipboards::class);

describe('Wildcard Abilities', function (): void {
    describe('Happy Paths', function (): void {
        test('grants all abilities when wildcard ability is assigned', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            // Act
            $warden->allow($user)->to('*');

            // Assert
            expect($warden->can('edit-site'))->toBeTrue();
            expect($warden->can('*'))->toBeTrue();

            // Act
            $warden->disallow($user)->to('*');

            // Assert
            expect($warden->cannot('edit-site'))->toBeTrue();
            expect($warden->cannot('*'))->toBeTrue();
        })->with('bouncerProvider');

        test('allows all actions on specific model instance with toManage', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            // Act
            $warden->allow($user)->toManage($user);

            // Assert
            expect($warden->can('*', $user))->toBeTrue();
            expect($warden->can('edit', $user))->toBeTrue();
            expect($warden->cannot('*', User::class))->toBeTrue();
            expect($warden->cannot('edit', User::class))->toBeTrue();

            // Act
            $warden->disallow($user)->toManage($user);

            // Assert
            expect($warden->cannot('*', $user))->toBeTrue();
            expect($warden->cannot('edit', $user))->toBeTrue();
        })->with('bouncerProvider');

        test('allows all actions on model class and its instances with toManage', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            // Act
            $warden->allow($user)->toManage(User::class);

            // Assert
            expect($warden->can('*', $user))->toBeTrue();
            expect($warden->can('edit', $user))->toBeTrue();
            expect($warden->can('*', User::class))->toBeTrue();
            expect($warden->can('edit', User::class))->toBeTrue();
            expect($warden->cannot('edit', Account::class))->toBeTrue();
            expect($warden->cannot('edit', Account::class))->toBeTrue();

            // Act
            $warden->disallow($user)->toManage(User::class);

            // Assert
            expect($warden->cannot('*', $user))->toBeTrue();
            expect($warden->cannot('edit', $user))->toBeTrue();
            expect($warden->cannot('*', User::class))->toBeTrue();
            expect($warden->cannot('edit', User::class))->toBeTrue();
        })->with('bouncerProvider');

        test('allows specific action on all models with everything() method', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            // Act
            $warden->allow($user)->to('delete')->everything();

            // Assert
            expect($warden->can('delete', $user))->toBeTrue();
            expect($warden->cannot('update', $user))->toBeTrue();
            expect($warden->can('delete', User::class))->toBeTrue();
            expect($warden->can('delete', '*'))->toBeTrue();

            // Act
            $warden->disallow($user)->to('delete')->everything();

            // Assert
            expect($warden->cannot('delete', $user))->toBeTrue();
            expect($warden->cannot('delete', User::class))->toBeTrue();
            expect($warden->cannot('delete', '*'))->toBeTrue();
        })->with('bouncerProvider');

        test('grants all abilities on all models with everything() method', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            // Act
            $warden->allow($user)->everything();

            // Assert
            expect($warden->can('*'))->toBeTrue();
            expect($warden->can('*', '*'))->toBeTrue();
            expect($warden->can('*', $user))->toBeTrue();
            expect($warden->can('*', User::class))->toBeTrue();
            expect($warden->can('ban', '*'))->toBeTrue();
            expect($warden->can('ban-users'))->toBeTrue();
            expect($warden->can('ban', $user))->toBeTrue();
            expect($warden->can('ban', User::class))->toBeTrue();

            // Act
            $warden->disallow($user)->everything();

            // Assert
            expect($warden->cannot('*'))->toBeTrue();
            expect($warden->cannot('*', '*'))->toBeTrue();
            expect($warden->cannot('*', $user))->toBeTrue();
            expect($warden->cannot('*', User::class))->toBeTrue();
            expect($warden->cannot('ban', '*'))->toBeTrue();
            expect($warden->cannot('ban-users'))->toBeTrue();
            expect($warden->cannot('ban', $user))->toBeTrue();
            expect($warden->cannot('ban', User::class))->toBeTrue();
        })->with('bouncerProvider');

        test('allows all actions on multiple model classes with toManage array', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            // Act
            $warden->allow($user)->toManage([User::class, Account::class]);

            // Assert
            expect($warden->can('*', User::class))->toBeTrue();
            expect($warden->can('edit', User::class))->toBeTrue();
            expect($warden->can('*', Account::class))->toBeTrue();
            expect($warden->can('edit', Account::class))->toBeTrue();
            expect($warden->can('*', $user))->toBeTrue();
            expect($warden->can('edit', $user))->toBeTrue();

            // Act
            $warden->disallow($user)->toManage([User::class, Account::class]);

            // Assert
            expect($warden->cannot('*', User::class))->toBeTrue();
            expect($warden->cannot('edit', User::class))->toBeTrue();
            expect($warden->cannot('*', Account::class))->toBeTrue();
            expect($warden->cannot('edit', Account::class))->toBeTrue();
            expect($warden->cannot('*', $user))->toBeTrue();
            expect($warden->cannot('edit', $user))->toBeTrue();
        })->with('bouncerProvider');
    });

    describe('Edge Cases', function (): void {
        test('simple wildcard ability does not grant model-specific abilities', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            // Act
            $warden->allow($user)->to('*');

            // Assert
            expect($warden->cannot('edit', $user))->toBeTrue();
            expect($warden->cannot('edit', User::class))->toBeTrue();
            expect($warden->cannot('*', $user))->toBeTrue();
            expect($warden->cannot('*', User::class))->toBeTrue();
        })->with('bouncerProvider');

        test('toManage on model instance does not grant simple abilities', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            // Act
            $warden->allow($user)->toManage($user);

            // Assert
            expect($warden->cannot('edit'))->toBeTrue();
            expect($warden->cannot('*'))->toBeTrue();
        })->with('bouncerProvider');

        test('toManage on model class does not grant simple abilities', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            // Act
            $warden->allow($user)->toManage(User::class);

            // Assert
            expect($warden->cannot('*'))->toBeTrue();
            expect($warden->cannot('edit'))->toBeTrue();
        })->with('bouncerProvider');

        test('everything() with specific action does not grant simple abilities', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            // Act
            $warden->allow($user)->to('delete')->everything();

            // Assert
            expect($warden->cannot('delete'))->toBeTrue();
        })->with('bouncerProvider');
    });
});

/**
 * @author Brian Faust <brian@cline.sh>
 */
