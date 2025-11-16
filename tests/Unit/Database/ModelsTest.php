<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Database\Ability;
use Cline\Warden\Database\Models;
use Cline\Warden\Database\Role;
use Cline\Warden\Exceptions\MorphKeyViolationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Models\Account;
use Tests\Fixtures\Models\User;

describe('Models', function (): void {
    beforeEach(function (): void {
        Models::reset();
    });

    afterEach(function (): void {
        Models::reset();
    });

    describe('Happy Paths', function (): void {
        test('sets custom abilities model', function (): void {
            // Arrange
            $customModel = Ability::class;

            // Act
            Models::setAbilitiesModel($customModel);
            $result = Models::classname(Ability::class);

            // Assert
            expect($result)->toBe($customModel);
        });

        test('sets custom roles model', function (): void {
            // Arrange
            $customModel = Role::class;

            // Act
            Models::setRolesModel($customModel);
            $result = Models::classname(Role::class);

            // Assert
            expect($result)->toBe($customModel);
        });

        test('sets custom table names', function (): void {
            // Arrange
            $customTables = [
                'abilities' => 'custom_abilities',
                'roles' => 'custom_roles',
            ];

            // Act
            Models::setTables($customTables);

            // Assert
            expect(Models::table('abilities'))->toBe('custom_abilities');
            expect(Models::table('roles'))->toBe('custom_roles');
        });

        test('returns default table name when not customized', function (): void {
            // Arrange
            $defaultTable = 'some_table';

            // Act
            $result = Models::table($defaultTable);

            // Assert
            expect($result)->toBe($defaultTable);
        });

        test('checks ownership via closure strategy', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'Owner']);
            $account = Account::query()->create(['name' => 'Account']);
            $account->actor_id = $user->id;
            $account->actor_type = $user->getMorphClass();
            $account->save();

            Models::ownedVia(Account::class, fn ($model, $authority): bool => $model->actor_id === $authority->id);

            // Act
            $result = Models::isOwnedBy($user, $account);

            // Assert
            expect($result)->toBeTrue();
        });

        test('checks ownership via string attribute strategy', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'Owner']);
            $account = Account::query()->create(['name' => 'Account']);
            $account->actor_id = $user->id;
            $account->save();

            Models::ownedVia(Account::class, 'actor_id');

            // Act
            $result = Models::isOwnedBy($user, $account);

            // Assert
            expect($result)->toBeTrue();
        });

        test('uses global ownership strategy as fallback', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'Owner']);
            $account = Account::query()->create(['name' => 'Account']);

            // Use existing actor_id column
            $account->actor_id = $user->id;
            $account->save();

            // Set global strategy to use actor_id
            Models::ownedVia('actor_id');

            // Act
            $result = Models::isOwnedBy($user, $account);

            // Assert
            expect($result)->toBeTrue();
        });

        test('uses default actor morph ownership check', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'Owner']);
            $account = Account::query()->create(['name' => 'Account']);
            $account->actor_id = $user->id;
            $account->actor_type = $user->getMorphClass();
            $account->save();

            // Act (no ownership strategy configured)
            $result = Models::isOwnedBy($user, $account);

            // Assert
            expect($result)->toBeTrue();
        });

        test('returns model key from key map', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'Test User']);
            Models::morphKeyMap([User::class => 'uuid']);

            // Act
            $result = Models::getModelKey($user);

            // Assert
            expect($result)->toBe('uuid');
        });

        test('returns default key name when not in key map', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'Test User']);

            // Act
            $result = Models::getModelKey($user);

            // Assert
            expect($result)->toEqual($user->getKeyName());
        });

        test('enforces morph key map requirement', function (): void {
            // Arrange
            $account = Account::query()->create(['name' => 'Test Account']);
            Models::enforceMorphKeyMap([Account::class => 'id']);

            // Act
            $result = Models::getModelKey($account);

            // Assert
            expect($result)->toBe('id');
        });

        test('registers morph key map without enforcement', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'Test User']);
            Models::morphKeyMap([User::class => 'custom_id']);

            // Act
            $result = Models::getModelKey($user);

            // Assert
            expect($result)->toBe('custom_id');
        });
    });

    describe('Sad Paths', function (): void {
        test('returns false when ownership check fails', function (): void {
            // Arrange
            $user1 = User::query()->create(['name' => 'User 1']);
            $user2 = User::query()->create(['name' => 'User 2']);
            $account = Account::query()->create(['name' => 'Account']);
            $account->actor_id = $user2->id;
            $account->save();

            Models::ownedVia(Account::class, 'actor_id');

            // Act
            $result = Models::isOwnedBy($user1, $account);

            // Assert
            expect($result)->toBeFalse();
        });

        test('throws exception when enforcement enabled and model not in key map', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'Test User']);

            // Enforce key map with only Account, but not User
            Models::enforceMorphKeyMap([Account::class => 'id']);

            // Act & Assert
            $this->expectException(MorphKeyViolationException::class);
            $this->expectExceptionMessage(User::class);
            Models::getModelKey($user);
        });

        test('throws exception when user model not configured', function (): void {
            // Arrange
            // Models::reset() already called in beforeEach, so user model is not set

            // Act & Assert
            expect(fn (): mixed => Models::user())->toThrow(
                RuntimeException::class,
                'User model not configured',
            );
        });
    });

    describe('Edge Cases', function (): void {
        test('requires key map without providing map', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'Test User']);
            Models::requireKeyMap();

            // Act & Assert
            $this->expectException(MorphKeyViolationException::class);
            Models::getModelKey($user);
        });

        test('getModelKeyFromClass returns mapped key for class string', function (): void {
            // Arrange
            Models::morphKeyMap([User::class => 'uuid']);

            // Act
            $result = Models::getModelKeyFromClass(User::class);

            // Assert
            expect($result)->toBe('uuid');
        });

        test('getModelKeyFromClass returns default key when not mapped', function (): void {
            // Arrange
            // No mapping configured

            // Act
            $result = Models::getModelKeyFromClass(User::class);

            // Assert
            expect($result)->toBe(
                new User()->getKeyName(),
            );
        });

        test('getModelKeyFromClass throws exception when enforcement enabled and not mapped', function (): void {
            // Arrange
            Models::enforceMorphKeyMap([Account::class => 'id']);

            // Act & Assert
            $this->expectException(MorphKeyViolationException::class);
            $this->expectExceptionMessage(User::class);
            Models::getModelKeyFromClass(User::class);
        });
    });
});
