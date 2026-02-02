<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Database\Ability;
use Cline\Warden\Database\Queries\AbilitiesForModel;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\Models\Account;
use Tests\Fixtures\Models\User;

describe('AbilitiesForModel Query', function (): void {
    beforeEach(function (): void {
        $this->query = new AbilitiesForModel();
    });

    describe('Happy Paths', function (): void {
        test('constrains query to wildcard abilities when model is asterisk', function (): void {
            // Arrange
            Ability::query()->forceCreate(['name' => 'global-ability', 'subject_type' => '*']);
            Ability::query()->forceCreate(['name' => 'user-ability', 'subject_type' => User::class]);
            Ability::query()->forceCreate(['name' => 'account-ability', 'subject_type' => Account::class]);

            $query = Ability::query();

            // Act
            $this->query->constrain($query, '*');

            $results = $query->get();

            // Assert
            expect($results)->toHaveCount(1);
            expect($results->first()->name)->toEqual('global-ability');
            expect($results->first()->subject_type)->toEqual('*');
        });

        test('constrains query to model class abilities in non strict mode', function (): void {
            // Arrange
            Ability::query()->forceCreate(['name' => 'global', 'subject_type' => '*', 'subject_id' => null]);
            Ability::query()->forceCreate(['name' => 'user-blanket', 'subject_type' => User::class, 'subject_id' => null]);
            Ability::query()->forceCreate(['name' => 'account-blanket', 'subject_type' => Account::class, 'subject_id' => null]);

            $query = Ability::query();

            // Act
            $this->query->constrain($query, User::class);

            $results = $query->get();

            // Assert - Should include wildcard (*) AND User class blanket abilities
            expect($results)->toHaveCount(2);
            expect($results->contains('name', 'global'))->toBeTrue();
            expect($results->contains('name', 'user-blanket'))->toBeTrue();
        });

        test('constrains query to model class abilities in strict mode', function (): void {
            // Arrange
            Ability::query()->forceCreate(['name' => 'global', 'subject_type' => '*', 'subject_id' => null]);
            Ability::query()->forceCreate(['name' => 'user-blanket', 'subject_type' => User::class, 'subject_id' => null]);
            Ability::query()->forceCreate(['name' => 'account-blanket', 'subject_type' => Account::class, 'subject_id' => null]);

            $query = Ability::query();

            // Act
            $this->query->constrain($query, User::class, strict: true);

            $results = $query->get();

            // Assert - Should exclude wildcard (*) abilities in strict mode
            expect($results)->toHaveCount(1);
            expect($results->first()->name)->toEqual('user-blanket');
            expect($results->first()->subject_type)->toEqual(User::class);
        });

        test('includes blanket abilities for non existing model instances', function (): void {
            // Arrange
            $user = new User();
            $nonExistentId = new User()->newUniqueId() ?? 999;

            // Non-existing model (exists = false)
            Ability::query()->forceCreate(['name' => 'user-blanket', 'subject_type' => User::class, 'subject_id' => null]);
            Ability::query()->forceCreate(['name' => 'user-specific', 'subject_type' => User::class, 'subject_id' => $nonExistentId]);

            $query = Ability::query();

            // Act
            $this->query->constrain($query, $user);

            $results = $query->get();

            // Assert - Non-existing models should only get blanket + wildcard abilities
            expect($results->contains('name', 'user-blanket'))->toBeTrue();
            expect($results->contains('name', 'user-specific'))->toBeFalse();
        });

        test('includes instance specific abilities for existing models in non strict mode', function (): void {
            // Arrange
            $user = User::query()->create();
            $otherUserId = new User()->newUniqueId() ?? 998;

            Ability::query()->forceCreate(['name' => 'global', 'subject_type' => '*', 'subject_id' => null]);
            Ability::query()->forceCreate(['name' => 'user-blanket', 'subject_type' => User::class, 'subject_id' => null]);
            Ability::query()->forceCreate(['name' => 'user-specific', 'subject_type' => User::class, 'subject_id' => $user->id]);
            Ability::query()->forceCreate(['name' => 'other-user-specific', 'subject_type' => User::class, 'subject_id' => $otherUserId]);

            $query = Ability::query();

            // Act
            $this->query->constrain($query, $user);

            $results = $query->get();

            // Assert - Should include: wildcard, blanket, and instance-specific
            expect($results)->toHaveCount(3);
            expect($results->contains('name', 'global'))->toBeTrue();
            expect($results->contains('name', 'user-blanket'))->toBeTrue();
            expect($results->contains('name', 'user-specific'))->toBeTrue();
            expect($results->contains('name', 'other-user-specific'))->toBeFalse();
        });

        test('excludes blanket abilities for existing models in strict mode', function (): void {
            // Arrange
            $user = User::query()->create();

            Ability::query()->forceCreate(['name' => 'global', 'subject_type' => '*', 'subject_id' => null]);
            Ability::query()->forceCreate(['name' => 'user-blanket', 'subject_type' => User::class, 'subject_id' => null]);
            Ability::query()->forceCreate(['name' => 'user-specific', 'subject_type' => User::class, 'subject_id' => $user->id]);

            $query = Ability::query();

            // Act
            $this->query->constrain($query, $user, strict: true);

            $results = $query->get();

            // Assert - Strict mode should exclude wildcards AND blanket abilities
            expect($results)->toHaveCount(1);
            expect($results->first()->name)->toEqual('user-specific');
            expect($results->first()->subject_id)->toEqual($user->id);
        });

        test('handles multiple model types correctly', function (): void {
            // Arrange
            $user = User::query()->create();
            $account = Account::query()->create();

            Ability::query()->forceCreate(['name' => 'user-ability', 'subject_type' => User::class, 'subject_id' => $user->id]);
            Ability::query()->forceCreate(['name' => 'account-ability', 'subject_type' => Account::class, 'subject_id' => $account->id]);

            $userQuery = Ability::query();
            $accountQuery = Ability::query();

            // Act
            $this->query->constrain($userQuery, $user, strict: true);
            $this->query->constrain($accountQuery, $account, strict: true);

            // Assert
            expect($userQuery->first()->name)->toEqual('user-ability');
            expect($accountQuery->first()->name)->toEqual('account-ability');
        });

        test('handles model with custom morph class', function (): void {
            // Arrange - User models use default morph class (FQCN)
            $user = User::query()->create();
            $morphClass = $user->getMorphClass();

            Ability::query()->forceCreate(['name' => 'user-ability', 'subject_type' => $morphClass, 'subject_id' => $user->id]);

            $query = Ability::query();

            // Act
            $this->query->constrain($query, $user, strict: true);

            $results = $query->get();

            // Assert
            expect($results)->toHaveCount(1);
            expect($results->first()->name)->toEqual('user-ability');
        });

        test('works with base query builder not just eloquent', function (): void {
            // Arrange
            Ability::query()->forceCreate(['name' => 'global', 'subject_type' => '*', 'subject_id' => null]);
            Ability::query()->forceCreate(['name' => 'user-ability', 'subject_type' => User::class, 'subject_id' => null]);

            $query = DB::table('abilities');

            // Act
            $this->query->constrain($query, '*');

            $results = $query->get();

            // Assert
            expect($results)->toHaveCount(1);
            expect($results[0]->name)->toEqual('global');
        });

        test('generates correct sql for wildcard constraint', function (): void {
            // Arrange
            $query = Ability::query();

            // Act
            $this->query->constrain($query, '*');

            $sql = str_replace('`', '"', $query->toSql());

            // Assert
            $this->assertStringContainsString('where "abilities"."subject_type" = ?', $sql);
        });

        test('generates correct sql for non strict model constraint', function (): void {
            // Arrange
            $user = User::query()->create();
            $query = Ability::query();

            // Act
            $this->query->constrain($query, $user);

            $sql = str_replace('`', '"', $query->toSql());

            // Assert - Should have OR condition for wildcard
            $this->assertStringContainsString('where ("abilities"."subject_type" = ? or', $sql);
            $this->assertStringContainsString('("abilities"."subject_type" = ?', $sql);
        });

        test('generates correct sql for strict model constraint', function (): void {
            // Arrange
            $user = User::query()->create();
            $query = Ability::query();

            // Act
            $this->query->constrain($query, $user, strict: true);

            $sql = str_replace('`', '"', $query->toSql());

            // Assert - Should NOT have OR condition for wildcard in strict mode
            $this->assertStringNotContainsString('"abilities"."subject_type" = ? or', $sql);
            $this->assertStringContainsString('where ("abilities"."subject_type" = ?', $sql);
        });

        test('includes only blanket abilities for new model instance', function (): void {
            // Arrange - Create a fresh model instance that hasn't been saved
            $newUser = new User(['name' => 'Test User']);
            $userId1 = new User()->newUniqueId() ?? 997;
            $userId2 = new User()->newUniqueId() ?? 996;

            Ability::query()->forceCreate(['name' => 'user-blanket', 'subject_type' => User::class, 'subject_id' => null]);
            Ability::query()->forceCreate(['name' => 'user-specific-1', 'subject_type' => User::class, 'subject_id' => $userId1]);
            Ability::query()->forceCreate(['name' => 'user-specific-2', 'subject_type' => User::class, 'subject_id' => $userId2]);

            $query = Ability::query();

            // Act
            $this->query->constrain($query, $newUser, strict: true);

            $results = $query->get();

            // Assert - New models (exists=false) should only match blanket abilities
            expect($results)->toHaveCount(1);
            expect($results->first()->name)->toEqual('user-blanket');
            expect($results->first()->subject_id)->toBeNull();
        });

        test('distinguishes between class string and instance correctly', function (): void {
            // Arrange - Same model type, one as class string, one as instance
            $user = User::query()->create();

            Ability::query()->forceCreate(['name' => 'blanket', 'subject_type' => User::class, 'subject_id' => null]);
            Ability::query()->forceCreate(['name' => 'specific', 'subject_type' => User::class, 'subject_id' => $user->id]);

            $classQuery = Ability::query();
            $instanceQuery = Ability::query();

            // Act
            $this->query->constrain($classQuery, User::class);
            // Class string = new model
            $this->query->constrain($instanceQuery, $user);

            // Instance = existing model
            $classResults = $classQuery->get();
            $instanceResults = $instanceQuery->get();

            // Assert - Class string should only get blanket (like new model)
            expect($classResults)->toHaveCount(1);
            // Only blanket in non-strict mode for class string
            expect($classResults->contains('name', 'blanket'))->toBeTrue();
            expect($classResults->contains('name', 'specific'))->toBeFalse();

            // Instance should get both blanket and specific
            expect($instanceResults)->toHaveCount(2);
            expect($instanceResults->contains('name', 'blanket'))->toBeTrue();
            expect($instanceResults->contains('name', 'specific'))->toBeTrue();
        });

        test('correctly filters by subject id using model key', function (): void {
            // Arrange - Test that it uses Models::getModelKey() correctly
            $user1 = User::query()->create();
            $user2 = User::query()->create();

            Ability::query()->forceCreate([
                'name' => 'edit-user-1',
                'subject_type' => User::class,
                'subject_id' => $user1->id,
            ]);

            Ability::query()->forceCreate([
                'name' => 'edit-user-2',
                'subject_type' => User::class,
                'subject_id' => $user2->id,
            ]);

            $query1 = Ability::query();
            $query2 = Ability::query();

            // Act
            $this->query->constrain($query1, $user1, strict: true);
            $this->query->constrain($query2, $user2, strict: true);

            // Assert - Each should only get their own instance-specific ability
            expect($query1->first()->name)->toEqual('edit-user-1');
            expect($query1->first()->subject_id)->toEqual($user1->id);

            expect($query2->first()->name)->toEqual('edit-user-2');
            expect($query2->first()->subject_id)->toEqual($user2->id);
        });

        test('applies or where logic correctly for existing model in non strict mode', function (): void {
            // Arrange - Verify the OR condition works for (blanket OR specific)
            $user = User::query()->create();

            // Only blanket ability exists
            Ability::query()->forceCreate(['name' => 'blanket-only', 'subject_type' => User::class, 'subject_id' => null]);

            $query1 = Ability::query();
            $this->query->constrain($query1, $user);

            // Assert - Should find blanket ability via OR clause
            expect($query1->get())->toHaveCount(1);
            expect($query1->first()->name)->toEqual('blanket-only');

            // Now add specific ability
            Ability::query()->forceCreate(['name' => 'specific-only', 'subject_type' => User::class, 'subject_id' => $user->id]);

            $query2 = Ability::query();
            $this->query->constrain($query2, $user);

            // Assert - Should find both via OR clause
            $results = $query2->get();
            expect($results)->toHaveCount(2);
            expect($results->contains('name', 'blanket-only'))->toBeTrue();
            expect($results->contains('name', 'specific-only'))->toBeTrue();
        });
    });

    describe('Edge Cases', function (): void {
        test('returns empty result when no abilities match', function (): void {
            // Arrange
            $user = User::query()->create();
            $accountId = new Account()->newUniqueId() ?? 995;
            Ability::query()->forceCreate(['name' => 'account-ability', 'subject_type' => Account::class, 'subject_id' => $accountId]);

            $query = Ability::query();

            // Act
            $this->query->constrain($query, $user, strict: true);

            $results = $query->get();

            // Assert
            expect($results)->toHaveCount(0);
        });

        test('handles empty abilities table gracefully', function (): void {
            // Arrange - No abilities in database
            $user = User::query()->create();
            $query = Ability::query();

            // Act
            $this->query->constrain($query, $user);

            $results = $query->get();

            // Assert
            expect($results)->toHaveCount(0);
        });

        test('excludes other model types even with matching ids', function (): void {
            // Arrange - Same ID for different model types
            $user = User::query()->create([]);
            $account = Account::query()->create([]);

            Ability::query()->forceCreate(['name' => 'user-1', 'subject_type' => User::class, 'subject_id' => $user->id]);
            Ability::query()->forceCreate(['name' => 'account-1', 'subject_type' => Account::class, 'subject_id' => $account->id]);

            $userQuery = Ability::query();
            $accountQuery = Ability::query();

            // Act
            $this->query->constrain($userQuery, $user, strict: true);
            $this->query->constrain($accountQuery, $account, strict: true);

            // Assert - Each should only match their own type
            expect($userQuery->first()->name)->toEqual('user-1');
            expect($userQuery->first()->subject_type)->toEqual(User::class);

            expect($accountQuery->first()->name)->toEqual('account-1');
            expect($accountQuery->first()->subject_type)->toEqual(Account::class);
        });
    });
});
