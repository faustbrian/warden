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

describe('Forbid Abilities', function (): void {
    describe('Happy Paths', function (): void {
        test('forbids and unforbids simple ability successfully', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();
            $warden->allow($user)->to('edit-site');

            // Act
            $warden->forbid($user)->to('edit-site');

            // Assert
            expect($warden->cannot('edit-site'))->toBeTrue();

            // Act - Unforbid
            $warden->unforbid($user)->to('edit-site');

            // Assert
            expect($warden->can('edit-site'))->toBeTrue();
        })->with('bouncerProvider');

        test('forbids and unforbids model ability successfully', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();
            $warden->allow($user)->to('delete', $user);

            // Act
            $warden->forbid($user)->to('delete', $user);

            // Assert
            expect($warden->cannot('delete', $user))->toBeTrue();

            // Act - Unforbid
            $warden->unforbid($user)->to('delete', $user);

            // Assert
            expect($warden->can('delete', $user))->toBeTrue();
        })->with('bouncerProvider');

        test('forbids and unforbids model class ability successfully', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();
            $warden->allow($user)->to('delete', User::class);

            // Act
            $warden->forbid($user)->to('delete', User::class);

            // Assert
            expect($warden->cannot('delete', User::class))->toBeTrue();

            // Act - Unforbid
            $warden->unforbid($user)->to('delete', User::class);

            // Assert
            expect($warden->can('delete', User::class))->toBeTrue();
        })->with('bouncerProvider');

        test('forbids and unforbids ability for everyone successfully', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();
            $warden->allow($user)->everything();

            // Act
            $warden->forbidEveryone()->to('delete', Account::class);

            // Assert
            expect($warden->can('delete', User::class))->toBeTrue();
            expect($warden->cannot('delete', Account::class))->toBeTrue();

            // Act - Unforbid
            $warden->unforbidEveryone()->to('delete', Account::class);

            // Assert
            expect($warden->can('delete', Account::class))->toBeTrue();
        })->with('bouncerProvider');
    });

    describe('Sad Paths', function (): void {
        test('forbidding single model overrides allowed model class ability', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();
            $warden->allow($user)->to('delete', User::class);

            // Act
            $warden->forbid($user)->to('delete', $user);

            // Assert
            expect($warden->cannot('delete', $user))->toBeTrue();

            // Act - Unforbid
            $warden->unforbid($user)->to('delete', $user);

            // Assert
            expect($warden->can('delete', $user))->toBeTrue();
        })->with('bouncerProvider');

        test('forbidding model class overrides allowed individual model', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();
            $warden->allow($user)->to('delete', $user);

            // Act
            $warden->forbid($user)->to('delete', User::class);

            // Assert
            expect($warden->cannot('delete', $user))->toBeTrue();

            // Act - Unforbid single model (class forbid still active)
            $warden->unforbid($user)->to('delete', $user);

            // Assert
            expect($warden->cannot('delete', $user))->toBeTrue();
        })->with('bouncerProvider');

        test('forbidding ability through role overrides direct user ability', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();
            $warden->forbid('admin')->to('delete', User::class);
            $warden->allow($user)->to('delete', User::class);
            $warden->assign('admin')->to($user);

            // Assert
            expect($warden->cannot('delete', User::class))->toBeTrue();
            expect($warden->cannot('delete', $user))->toBeTrue();

            // Act - Unforbid role
            $warden->unforbid('admin')->to('delete', User::class);

            // Assert
            expect($warden->can('delete', User::class))->toBeTrue();
            expect($warden->can('delete', $user))->toBeTrue();

            // Act - Forbid specific model through role
            $warden->forbid('admin')->to('delete', $user);

            // Assert
            expect($warden->can('delete', User::class))->toBeTrue();
            expect($warden->cannot('delete', $user))->toBeTrue();
        })->with('bouncerProvider');

        test('forbidding user ability overrides role allowed ability', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();
            $warden->allow('admin')->to('delete', User::class);
            $warden->forbid($user)->to('delete', User::class);
            $warden->assign('admin')->to($user);

            // Assert
            expect($warden->cannot('delete', User::class))->toBeTrue();
            expect($warden->cannot('delete', $user))->toBeTrue();
        })->with('bouncerProvider');

        test('forbidding ability when everything is allowed restricts specific ability', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();
            $warden->allow($user)->everything();

            // Act
            $warden->forbid($user)->toManage(User::class);

            // Assert
            expect($warden->can('create', Account::class))->toBeTrue();
            expect($warden->cannot('create', User::class))->toBeTrue();
        })->with('bouncerProvider');

        test('forbidding ability on everything blocks all model checks', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();
            $warden->allow($user)->everything();

            // Act
            $warden->forbid($user)->to('delete')->everything();

            // Assert
            expect($warden->can('create', Account::class))->toBeTrue();
            expect($warden->cannot('delete', User::class))->toBeTrue();
            expect($warden->cannot('delete', $user))->toBeTrue();
        })->with('bouncerProvider');

        test('forbidding ability stops all further checks including gate callbacks', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();
            $warden->define('sleep', fn (): true => true);

            // Assert - Gate callback works before forbid
            expect($warden->can('sleep'))->toBeTrue();

            // Act
            $warden->forbid($user)->to('sleep');
            $warden->runBeforePolicies();

            // Assert - Forbid overrides gate callback
            expect($warden->cannot('sleep'))->toBeTrue();
        })->with('bouncerProvider');
    });

    describe('Edge Cases', function (): void {
        test('forbidding single model does not affect other models of same class', function ($provider): void {
            // Arrange
            [$warden, $user1, $user2] = $provider(2);
            $warden->allow($user1)->to('delete', User::class);

            // Act
            $warden->forbid($user1)->to('delete', $user2);

            // Assert
            expect($warden->can('delete', $user1))->toBeTrue();
        })->with('bouncerProvider');
    });
});
