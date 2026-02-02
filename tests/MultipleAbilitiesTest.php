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
use Tests\Fixtures\Models\Account;
use Tests\Fixtures\Models\User;

uses(TestsClipboards::class);

describe('Multiple Abilities', function (): void {
    describe('Happy Paths', function (): void {
        test('allows multiple abilities simultaneously', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            // Act
            $warden->allow($user)->to(['edit', 'delete']);

            // Assert
            expect($warden->can('edit'))->toBeTrue();
            expect($warden->can('delete'))->toBeTrue();
        })->with('bouncerProvider');

        test('allows multiple abilities on specific model instance', function ($provider): void {
            // Arrange
            [$warden, $user1, $user2] = $provider(2);

            // Act
            $warden->allow($user1)->to(['edit', 'delete'], $user1);

            // Assert
            expect($warden->can('edit', $user1))->toBeTrue();
            expect($warden->can('delete', $user1))->toBeTrue();
            expect($warden->cannot('edit', $user2))->toBeTrue();
            expect($warden->cannot('delete', $user2))->toBeTrue();
        })->with('bouncerProvider');

        test('allows multiple abilities on blanket model class', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            // Act
            $warden->allow($user)->to(['edit', 'delete'], User::class);

            // Assert
            expect($warden->can('edit', User::class))->toBeTrue();
            expect($warden->can('delete', User::class))->toBeTrue();
        })->with('bouncerProvider');

        test('allows single ability on multiple model types', function ($provider): void {
            // Arrange
            [$warden, $user1, $user2] = $provider(2);

            // Act
            $warden->allow($user1)->to('delete', [Account::class, $user1]);

            // Assert
            expect($warden->can('delete', Account::class))->toBeTrue();
            expect($warden->can('delete', $user1))->toBeTrue();
            expect($warden->cannot('delete', $user2))->toBeTrue();
            expect($warden->cannot('delete', User::class))->toBeTrue();
        })->with('bouncerProvider');

        test('allows multiple abilities on multiple model types', function ($provider): void {
            // Arrange
            [$warden, $user1, $user2] = $provider(2);

            // Act
            $warden->allow($user1)->to(['update', 'delete'], [Account::class, $user1]);

            // Assert
            expect($warden->can('update', Account::class))->toBeTrue();
            expect($warden->can('delete', Account::class))->toBeTrue();
            expect($warden->can('update', $user1))->toBeTrue();
            expect($warden->cannot('update', $user2))->toBeTrue();
            expect($warden->cannot('update', User::class))->toBeTrue();
        })->with('bouncerProvider');

        test('allows multiple abilities via map of ability-to-model pairs', function ($provider): void {
            // Arrange
            [$warden, $user1, $user2] = $provider(2);

            $account1 = Account::query()->create();
            $account2 = Account::query()->create();

            // Act
            $warden->allow($user1)->to([
                'edit' => User::class,
                'delete' => $user1,
                'view' => Account::class,
                'update' => $account1,
                'access-dashboard',
            ]);

            // Assert
            expect($warden->can('edit', User::class))->toBeTrue();
            expect($warden->cannot('view', User::class))->toBeTrue();
            expect($warden->can('delete', $user1))->toBeTrue();
            expect($warden->cannot('delete', $user2))->toBeTrue();

            expect($warden->can('view', Account::class))->toBeTrue();
            expect($warden->cannot('update', Account::class))->toBeTrue();
            expect($warden->can('update', $account1))->toBeTrue();
            expect($warden->cannot('update', $account2))->toBeTrue();

            expect($warden->can('access-dashboard'))->toBeTrue();
        })->with('bouncerProvider');

        test('unforbids multiple abilities simultaneously', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            $warden->allow($user)->to(['view', 'edit', 'delete']);
            $warden->forbid($user)->to(['view', 'edit', 'delete']);

            // Act
            $warden->unforbid($user)->to(['edit', 'delete']);

            // Assert
            expect($warden->cannot('view'))->toBeTrue();
            expect($warden->can('edit'))->toBeTrue();
            expect($warden->can('delete'))->toBeTrue();
        })->with('bouncerProvider');

        test('unforbids multiple abilities on specific model instance', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            $warden->allow($user)->to(['view', 'edit', 'delete'], $user);
            $warden->forbid($user)->to(['view', 'edit', 'delete'], $user);

            // Act
            $warden->unforbid($user)->to(['edit', 'delete'], $user);

            // Assert
            expect($warden->cannot('view', $user))->toBeTrue();
            expect($warden->can('edit', $user))->toBeTrue();
            expect($warden->can('delete', $user))->toBeTrue();
        })->with('bouncerProvider');

        test('unforbids multiple abilities on blanket model class', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            $warden->allow($user)->to(['view', 'edit', 'delete'], User::class);
            $warden->forbid($user)->to(['view', 'edit', 'delete'], User::class);

            // Act
            $warden->unforbid($user)->to(['edit', 'delete'], User::class);

            // Assert
            expect($warden->cannot('view', User::class))->toBeTrue();
            expect($warden->can('edit', User::class))->toBeTrue();
            expect($warden->can('delete', User::class))->toBeTrue();
        })->with('bouncerProvider');

        test('unforbids multiple abilities via map of ability-to-model pairs', function ($provider): void {
            // Arrange
            [$warden, $user1, $user2] = $provider(2);

            $account1 = Account::query()->create();
            Account::query()->create();

            $warden->allow($user1)->to([
                'edit' => User::class,
                'delete' => $user1,
                'view' => Account::class,
                'update' => $account1,
            ]);

            $warden->forbid($user1)->to([
                'edit' => User::class,
                'delete' => $user1,
                'view' => Account::class,
                'update' => $account1,
            ]);

            // Act
            $warden->unforbid($user1)->to([
                'edit' => User::class,
                'update' => $account1,
            ]);

            // Assert
            expect($warden->can('edit', User::class))->toBeTrue();
            expect($warden->cannot('delete', $user1))->toBeTrue();
            expect($warden->cannot('view', $account1))->toBeTrue();
            expect($warden->can('update', $account1))->toBeTrue();
        })->with('bouncerProvider');

        test('checks if user can perform any of multiple abilities with canAny', function ($provider): void {
            // Arrange
            [$warden, $user1] = $provider();

            $user1->allow('create', User::class);

            // Act & Assert
            expect($warden->canAny(['create', 'delete'], User::class))->toBeTrue();
            expect($warden->canAny(['update', 'delete'], User::class))->toBeFalse();
        })->with('bouncerProvider');
    });

    describe('Sad Paths', function (): void {
        test('disallows multiple abilities simultaneously', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            $warden->allow($user)->to(['edit', 'delete']);

            // Act
            $warden->disallow($user)->to(['edit', 'delete']);

            // Assert
            expect($warden->cannot('edit'))->toBeTrue();
            expect($warden->cannot('delete'))->toBeTrue();
        })->with('bouncerProvider');

        test('disallows multiple abilities on specific model instance', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            $warden->allow($user)->to(['view', 'edit', 'delete'], $user);

            // Act
            $warden->disallow($user)->to(['edit', 'delete'], $user);

            // Assert
            expect($warden->can('view', $user))->toBeTrue();
            expect($warden->cannot('edit', $user))->toBeTrue();
            expect($warden->cannot('delete', $user))->toBeTrue();
        })->with('bouncerProvider');

        test('disallows multiple abilities on blanket model class', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            $warden->allow($user)->to(['edit', 'delete'], User::class);

            // Act
            $warden->disallow($user)->to(['edit', 'delete'], User::class);

            // Assert
            expect($warden->cannot('edit', User::class))->toBeTrue();
            expect($warden->cannot('delete', User::class))->toBeTrue();
        })->with('bouncerProvider');

        test('disallows multiple abilities via map of ability-to-model pairs', function ($provider): void {
            // Arrange
            [$warden, $user1, $user2] = $provider(2);

            $account1 = Account::query()->create();
            Account::query()->create();

            $warden->allow($user1)->to([
                'edit' => User::class,
                'delete' => $user1,
                'view' => Account::class,
                'update' => $account1,
            ]);

            // Act
            $warden->disallow($user1)->to([
                'edit' => User::class,
                'update' => $account1,
            ]);

            // Assert
            expect($warden->cannot('edit', User::class))->toBeTrue();
            expect($warden->can('delete', $user1))->toBeTrue();
            expect($warden->can('view', $account1))->toBeTrue();
            expect($warden->cannot('update', $account1))->toBeTrue();
        })->with('bouncerProvider');

        test('forbids multiple abilities simultaneously', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            $warden->allow($user)->to(['edit', 'delete']);

            // Act
            $warden->forbid($user)->to(['edit', 'delete']);

            // Assert
            expect($warden->cannot('edit'))->toBeTrue();
            expect($warden->cannot('delete'))->toBeTrue();
        })->with('bouncerProvider');

        test('forbids multiple abilities on specific model instance', function ($provider): void {
            // Arrange
            [$warden, $user1, $user2] = $provider(2);

            $warden->allow($user1)->to(['view', 'edit', 'delete']);
            $warden->allow($user1)->to(['view', 'edit', 'delete'], $user1);
            $warden->allow($user1)->to(['view', 'edit', 'delete'], $user2);

            // Act
            $warden->forbid($user1)->to(['edit', 'delete'], $user1);

            // Assert
            expect($warden->can('view'))->toBeTrue();
            expect($warden->can('edit'))->toBeTrue();

            expect($warden->can('view', $user1))->toBeTrue();
            expect($warden->cannot('edit', $user1))->toBeTrue();
            expect($warden->cannot('delete', $user1))->toBeTrue();
            expect($warden->can('edit', $user2))->toBeTrue();
            expect($warden->can('delete', $user2))->toBeTrue();
        })->with('bouncerProvider');

        test('forbids multiple abilities on blanket model class', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            $warden->allow($user)->to(['edit', 'delete']);
            $warden->allow($user)->to(['edit', 'delete'], Account::class);
            $warden->allow($user)->to(['view', 'edit', 'delete'], User::class);

            // Act
            $warden->forbid($user)->to(['edit', 'delete'], User::class);

            // Assert
            expect($warden->can('edit'))->toBeTrue();
            expect($warden->can('delete'))->toBeTrue();

            expect($warden->can('edit', Account::class))->toBeTrue();
            expect($warden->can('delete', Account::class))->toBeTrue();

            expect($warden->can('view', User::class))->toBeTrue();
            expect($warden->cannot('edit', User::class))->toBeTrue();
            expect($warden->cannot('delete', User::class))->toBeTrue();
        })->with('bouncerProvider');

        test('forbids multiple abilities via map of ability-to-model pairs', function ($provider): void {
            // Arrange
            [$warden, $user1, $user2] = $provider(2);

            $account1 = Account::query()->create();
            Account::query()->create();

            $warden->allow($user1)->to([
                'edit' => User::class,
                'delete' => $user1,
                'view' => Account::class,
                'update' => $account1,
            ]);

            // Act
            $warden->forbid($user1)->to([
                'edit' => User::class,
                'update' => $account1,
            ]);

            // Assert
            expect($warden->cannot('edit', User::class))->toBeTrue();
            expect($warden->can('delete', $user1))->toBeTrue();
            expect($warden->can('view', $account1))->toBeTrue();
            expect($warden->cannot('update', $account1))->toBeTrue();
        })->with('bouncerProvider');
    });

    describe('Regression Tests - Keymap Support', function (): void {
        test('disallow uses keymap value for pivot query preventing incorrect removals', function ($provider): void {
            // Arrange - Configure keymap
            Models::enforceMorphKeyMap([
                User::class => 'id',
            ]);

            [$warden, $user1] = $provider();
            $user2 = User::query()->create(['name' => 'Bob']);
            $user3 = User::query()->create(['name' => 'Charlie']);

            // Grant abilities to all users
            $warden->allow($user1)->to(['edit-posts', 'delete-posts', 'publish-posts']);
            $warden->allow($user2)->to(['edit-posts', 'delete-posts']);
            $warden->allow($user3)->to(['edit-posts']);

            // Act - Remove specific abilities from user1 only
            $warden->disallow($user1)->to(['delete-posts', 'publish-posts']);

            // Assert - User1's abilities removed correctly (not affecting other users)
            expect($warden->can('edit-posts'))->toBeTrue();
            expect($warden->cannot('delete-posts'))->toBeTrue();
            expect($warden->cannot('publish-posts'))->toBeTrue();

            // Assert - User2 still has their abilities
            $wardenUser2 = $this->bouncer($user2);
            expect($wardenUser2->can('edit-posts'))->toBeTrue();
            expect($wardenUser2->can('delete-posts'))->toBeTrue();

            // Assert - User3 still has their ability
            $wardenUser3 = $this->bouncer($user3);
            expect($wardenUser3->can('edit-posts'))->toBeTrue();

            Models::reset();
        })->with('bouncerProvider');
    });
});

/**
 * @author Brian Faust <brian@cline.sh>
 */
