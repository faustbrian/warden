<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Database\Models;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\TestsClipboards;
use Tests\Fixtures\Models\Account;
use Tests\Fixtures\Models\User;

uses(TestsClipboards::class);

describe('Ownership Abilities', function (): void {
    describe('Happy Paths', function (): void {
        test('grants abilities on owned model class instances', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();
            $warden->allow($user)->toOwn(Account::class);

            $account = Account::query()->create();
            setOwner($account, $user);

            // Act & Assert
            expect($warden->cannot('update', Account::class))->toBeTrue();
            expect($warden->can('update', $account))->toBeTrue();

            clearOwner($account);
            expect($warden->cannot('update', $account))->toBeTrue();

            $warden->allow($user)->to('update', $account);
            $warden->disallow($user)->toOwn(Account::class);

            expect($warden->can('update', $account))->toBeTrue();

            $warden->disallow($user)->to('update', $account);

            expect($warden->cannot('update', $account))->toBeTrue();
        })->with('bouncerProvider');

        test('grants abilities on specific owned model instance', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            $account1 = Account::query()->create();
            setOwner($account1, $user);
            $account2 = Account::query()->create();
            setOwner($account2, $user);

            // Act
            $warden->allow($user)->toOwn($account1);

            // Assert
            expect($warden->cannot('update', Account::class))->toBeTrue();
            expect($warden->cannot('update', $account2))->toBeTrue();
            expect($warden->can('update', $account1))->toBeTrue();

            clearOwner($account1);
            expect($warden->cannot('update', $account1))->toBeTrue();

            $warden->allow($user)->to('update', $account1);
            $warden->disallow($user)->toOwn($account1);

            expect($warden->can('update', $account1))->toBeTrue();

            $warden->disallow($user)->to('update', $account1);

            expect($warden->cannot('update', $account1))->toBeTrue();
        })->with('bouncerProvider');

        test('grants specific abilities on owned model instances', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            $account1 = Account::query()->create();
            setOwner($account1, $user);
            $account2 = Account::query()->create();
            setOwner($account2, $user);

            // Act
            $warden->allow($user)->toOwn($account1)->to('update');
            $warden->allow($user)->toOwn($account2)->to(['view', 'update']);

            // Assert
            expect($warden->cannot('update', Account::class))->toBeTrue();
            expect($warden->can('update', $account1))->toBeTrue();
            expect($warden->cannot('delete', $account1))->toBeTrue();
            expect($warden->can('view', $account2))->toBeTrue();
            expect($warden->can('update', $account2))->toBeTrue();
            expect($warden->cannot('delete', $account2))->toBeTrue();

            clearOwner($account1);

            expect($warden->cannot('update', $account1))->toBeTrue();

            $warden->allow($user)->to('update', $account1);
            $warden->disallow($user)->toOwn($account1)->to('update');

            expect($warden->can('update', $account1))->toBeTrue();

            $warden->disallow($user)->to('update', $account1);

            expect($warden->cannot('update', $account1))->toBeTrue();
        })->with('bouncerProvider');

        test('grants all abilities on all owned models with toOwnEverything', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();
            $warden->allow($user)->toOwnEverything();

            $account = Account::query()->create();
            setOwner($account, $user);

            // Act & Assert
            expect($warden->cannot('delete', Account::class))->toBeTrue();
            expect($warden->can('delete', $account))->toBeTrue();

            clearOwner($account);
            expect($warden->cannot('delete', $account))->toBeTrue();

            $account->user_id = $user->id;

            $warden->disallow($user)->toOwnEverything();

            expect($warden->cannot('delete', $account))->toBeTrue();
        })->with('bouncerProvider');

        test('grants specific abilities on all owned models with toOwnEverything', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();
            $warden->allow($user)->toOwnEverything()->to('view');

            $account = Account::query()->create();
            setOwner($account, $user);

            // Act & Assert
            expect($warden->cannot('delete', Account::class))->toBeTrue();
            expect($warden->cannot('delete', $account))->toBeTrue();
            expect($warden->can('view', $account))->toBeTrue();

            clearOwner($account);
            expect($warden->cannot('view', $account))->toBeTrue();

            $account->user_id = $user->id;

            $warden->disallow($user)->toOwnEverything()->to('view');

            expect($warden->cannot('view', $account))->toBeTrue();
        })->with('bouncerProvider');

        test('uses custom ownership attribute globally', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();
            $warden->ownedVia('userId');

            $account = Account::query()->create()->fill(['userId' => $user->id]);

            // Act
            $warden->allow($user)->toOwn(Account::class);

            // Assert
            expect($warden->can('view', $account))->toBeTrue();

            Models::reset();
        })->with('bouncerProvider');

        test('uses custom ownership attribute for specific model type', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();
            $warden->ownedVia(Account::class, 'userId');

            $account = Account::query()->create()->fill(['userId' => $user->id]);

            // Act
            $warden->allow($user)->toOwn(Account::class);

            // Assert
            expect($warden->can('view', $account))->toBeTrue();

            Models::reset();
        })->with('bouncerProvider');
    });

    describe('Sad Paths', function (): void {
        test('forbid overrides ownership abilities', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            $warden->allow($user)->toOwn(Account::class);
            $warden->forbid($user)->to('publish', Account::class);

            $account = Account::query()->create();
            setOwner($account, $user);

            // Act & Assert
            expect($warden->can('update', $account))->toBeTrue();
            expect($warden->cannot('publish', $account))->toBeTrue();
        })->with('bouncerProvider');
    });

    describe('Regression Tests - Keymap Support', function (): void {
        test('ownership check uses keymap value not primary key', function ($provider): void {
            // Arrange - Configure keymap to use 'id' column
            Models::enforceMorphKeyMap([
                User::class => 'id',
                Account::class => 'id',
            ]);

            [$warden, $user1] = $provider();
            $user2 = User::query()->create(['name' => 'Bob']);

            // Grant ownership abilities
            $warden->allow($user1)->toOwn(Account::class);

            // Create accounts owned by different users using keymap values
            $account1 = Account::query()->create(['name' => 'Account 1']);
            $account1->actor_id = $user1->id; // Keymap value
            $account1->actor_type = $user1->getMorphClass();
            $account1->save();

            $account2 = Account::query()->create(['name' => 'Account 2']);
            $account2->actor_id = $user2->id; // Keymap value
            $account2->actor_type = $user2->getMorphClass();
            $account2->save();

            // Act & Assert - User 1 can only access their own account
            expect($warden->can('edit', $account1))->toBeTrue();
            expect($warden->cannot('edit', $account2))->toBeTrue();

            Models::reset();
        })->with('bouncerProvider');

        test('prevents ownership leakage between users with custom keymap', function ($provider): void {
            // Arrange - This tests the critical bug where getKey() was used
            Models::enforceMorphKeyMap([
                User::class => 'id',
                Account::class => 'id',
            ]);

            [$warden, $alice] = $provider();
            $bob = User::query()->create(['name' => 'Bob']);
            $charlie = User::query()->create(['name' => 'Charlie']);

            // Grant ownership abilities to all users
            $warden->allow($alice)->toOwn(Account::class);
            $warden->allow($bob)->toOwn(Account::class);
            $warden->allow($charlie)->toOwn(Account::class);

            // Create accounts with specific owners using keymap values
            $aliceAccount = Account::query()->create(['name' => 'Alice Account']);
            $aliceAccount->actor_id = $alice->id; // Alice's keymap value
            $aliceAccount->actor_type = $alice->getMorphClass();
            $aliceAccount->save();

            $bobAccount = Account::query()->create(['name' => 'Bob Account']);
            $bobAccount->actor_id = $bob->id; // Bob's keymap value
            $bobAccount->actor_type = $bob->getMorphClass();
            $bobAccount->save();

            // Act & Assert - Without the fix, ownership checks would use primary keys incorrectly
            expect($warden->can('edit', $aliceAccount))->toBeTrue(); // Alice owns this
            expect($warden->cannot('edit', $bobAccount))->toBeTrue(); // Alice doesn't own this

            // Switch to Bob's context
            $wardenBob = $this->bouncer($bob);
            $wardenBob->allow($bob)->toOwn(Account::class);

            expect($wardenBob->cannot('edit', $aliceAccount))->toBeTrue(); // Bob doesn't own this
            expect($wardenBob->can('edit', $bobAccount))->toBeTrue(); // Bob owns this

            Models::reset();
        })->with('bouncerProvider');
    });
});

/**
 * Set the actor of a model using actor morph relationship.
 */
function setOwner(Model $model, Model $actor): void
{
    $model->actor_id = $actor->getKey();
    $model->actor_type = $actor->getMorphClass();
}

/**
 * Clear the actor of a model using actor morph relationship.
 */
function clearOwner(Model $model): void
{
    $model->actor_id = 99;
    $model->actor_type = 'App\\FakeModel';
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
