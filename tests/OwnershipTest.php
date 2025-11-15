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
