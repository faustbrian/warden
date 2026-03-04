<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Database\Ability;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\TestsClipboards;
use Tests\Fixtures\Models\Account;
use Tests\Fixtures\Models\User;

uses(TestsClipboards::class);

describe('Abilities for Models', function (): void {
    describe('Happy Paths', function (): void {
        test('grants blanket ability for model class allowing all instances', function ($provider): void {
            // Arrange
            [$warden, $user1, $user2] = $provider(2);

            // Act
            $warden->allow($user1)->to('edit', User::class);

            // Assert
            expect($warden->cannot('edit'))->toBeTrue();
            expect($warden->can('edit', User::class))->toBeTrue();
            expect($warden->can('edit', $user2))->toBeTrue();

            // Act
            $warden->disallow($user1)->to('edit', User::class);

            // Assert
            expect($warden->cannot('edit'))->toBeTrue();
            expect($warden->cannot('edit', User::class))->toBeTrue();
            expect($warden->cannot('edit', $user2))->toBeTrue();

            // Act
            $warden->disallow($user1)->to('edit');

            // Assert
            expect($warden->cannot('edit'))->toBeTrue();
            expect($warden->cannot('edit', User::class))->toBeTrue();
            expect($warden->cannot('edit', $user2))->toBeTrue();
        })->with('bouncerProvider');

        test('grants individual model ability for specific instance only', function ($provider): void {
            // Arrange
            [$warden, $user1, $user2] = $provider(2);

            // Act
            $warden->allow($user1)->to('edit', $user2);

            // Assert
            expect($warden->cannot('edit'))->toBeTrue();
            expect($warden->cannot('edit', User::class))->toBeTrue();
            expect($warden->cannot('edit', $user1))->toBeTrue();
            expect($warden->can('edit', $user2))->toBeTrue();

            // Act
            $warden->disallow($user1)->to('edit', User::class);

            // Assert
            expect($warden->can('edit', $user2))->toBeTrue();

            // Act
            $warden->disallow($user1)->to('edit', $user1);

            // Assert
            expect($warden->can('edit', $user2))->toBeTrue();

            // Act
            $warden->disallow($user1)->to('edit', $user2);

            // Assert
            expect($warden->cannot('edit'))->toBeTrue();
            expect($warden->cannot('edit', User::class))->toBeTrue();
            expect($warden->cannot('edit', $user1))->toBeTrue();
            expect($warden->cannot('edit', $user2))->toBeTrue();
        })->with('bouncerProvider');

        test('maintains separation between blanket and individual model abilities', function ($provider): void {
            // Arrange
            [$warden, $user1, $user2] = $provider(2);

            // Act
            $warden->allow($user1)->to('edit', User::class);
            $warden->allow($user1)->to('edit', $user2);

            // Assert
            expect($warden->can('edit', User::class))->toBeTrue();
            expect($warden->can('edit', $user2))->toBeTrue();

            // Act
            $warden->disallow($user1)->to('edit', User::class);

            // Assert
            expect($warden->cannot('edit', User::class))->toBeTrue();
            expect($warden->can('edit', $user2))->toBeTrue();
        })->with('bouncerProvider');

        test('creates ability for model class with ability name', function (): void {
            // Act
            $ability = Ability::createForModel(Account::class, 'delete');

            // Assert
            expect($ability->subject_type)->toEqual(Account::class);
            expect($ability->name)->toEqual('delete');
            expect($ability->subject_id)->toBeNull();
        });

        test('creates ability for model class with name and extra attributes', function (): void {
            // Act
            $ability = Ability::createForModel(Account::class, [
                'name' => 'delete',
                'title' => 'Delete Accounts',
            ]);

            // Assert
            expect($ability->title)->toEqual('Delete Accounts');
            expect($ability->subject_type)->toEqual(Account::class);
            expect($ability->name)->toEqual('delete');
            expect($ability->subject_id)->toBeNull();
        });

        test('creates ability for specific model instance with ability name', function (): void {
            // Arrange
            $user = User::query()->create();

            // Act
            $ability = Ability::createForModel($user, 'delete');

            // Assert
            expect($ability->subject_id)->toEqual($user->id);
            expect($ability->subject_type)->toEqual(User::class);
            expect($ability->name)->toEqual('delete');
        });

        test('creates ability for specific model instance with name and extra attributes', function (): void {
            // Arrange
            $user = User::query()->create();

            // Act
            $ability = Ability::createForModel($user, [
                'name' => 'delete',
                'title' => 'Delete this user',
            ]);

            // Assert
            expect($ability->title)->toEqual('Delete this user');
            expect($ability->subject_id)->toEqual($user->id);
            expect($ability->subject_type)->toEqual(User::class);
            expect($ability->name)->toEqual('delete');
        });

        test('creates ability for all models using wildcard', function (): void {
            // Act
            $ability = Ability::createForModel('*', 'delete');

            // Assert
            expect($ability->subject_type)->toEqual('*');
            expect($ability->name)->toEqual('delete');
            expect($ability->subject_id)->toBeNull();
        });

        test('creates ability for all models with wildcard and extra attributes', function (): void {
            // Act
            $ability = Ability::createForModel('*', [
                'name' => 'delete',
                'title' => 'Delete everything',
            ]);

            // Assert
            expect($ability->title)->toEqual('Delete everything');
            expect($ability->subject_type)->toEqual('*');
            expect($ability->name)->toEqual('delete');
            expect($ability->subject_id)->toBeNull();
        });
    });

    describe('Sad Paths', function (): void {
        test('throws InvalidArgumentException when allowing ability on non-existent model', function ($provider): void {
            // Arrange
            $this->expectException('InvalidArgumentException');
            [$warden, $user] = $provider();

            // Act & Assert
            $warden->allow($user)->to('delete', new User());
        })->with('bouncerProvider');
    });
});
