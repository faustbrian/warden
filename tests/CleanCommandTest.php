<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Console\CleanCommand;
use Cline\Warden\Database\Ability;
use Illuminate\Console\Application as Artisan;
use Tests\Fixtures\Models\Account;
use Tests\Fixtures\Models\User;

describe('CleanCommand', function (): void {
    beforeEach(function (): void {
        Artisan::starting(
            fn ($artisan) => $artisan->resolveCommands(CleanCommand::class),
        );
    });

    describe('Happy Paths', function (): void {
        test('removes unassigned abilities when using unassigned flag', function (): void {
            // Arrange
            $warden = $this->bouncer($user = User::query()->create())->dontCache();
            $warden->allow($user)->to(['access-dashboard', 'ban-users', 'throw-dishes']);
            $warden->disallow($user)->to(['access-dashboard', 'ban-users']);

            // Assert initial state
            expect(Ability::query()->count())->toEqual(3);

            // Act
            $this
                ->artisan('warden:clean --unassigned')
                ->expectsOutput('Deleted 2 unassigned abilities.');

            // Assert
            expect(Ability::query()->count())->toEqual(1);
            expect($warden->can('throw-dishes'))->toBeTrue();
        });

        test('displays message when no unassigned abilities exist', function (): void {
            // Arrange
            $warden = $this->bouncer($user = User::query()->create())->dontCache();
            $warden->allow($user)->to(['access-dashboard', 'ban-users', 'throw-dishes']);

            // Assert initial state
            expect(Ability::query()->count())->toEqual(3);

            // Act & Assert
            $this
                ->artisan('warden:clean --unassigned')
                ->expectsOutput('No unassigned abilities.');

            expect(Ability::query()->count())->toEqual(3);
        });

        test('removes orphaned abilities for deleted models when using orphaned flag', function (): void {
            // Arrange
            $warden = $this->bouncer($user1 = User::query()->create())->dontCache();
            $account1 = Account::query()->create();
            $account2 = Account::query()->create();
            $user2 = User::query()->create();

            $warden->allow($user1)->to('create', Account::class);
            $warden->allow($user1)->to('create', User::class);
            $warden->allow($user1)->to('update', $user1);
            $warden->allow($user1)->to('update', $user2);
            $warden->allow($user1)->to('update', $account1);
            $warden->allow($user1)->to('update', $account2);

            // Act - Delete some models
            $account1->delete();
            $user2->delete();

            // Assert initial state
            expect(Ability::query()->count())->toEqual(6);

            // Act
            $this
                ->artisan('warden:clean --orphaned')
                ->expectsOutput('Deleted 2 orphaned abilities.');

            // Assert
            expect(Ability::query()->count())->toEqual(4);
            expect($warden->can('create', Account::class))->toBeTrue();
            expect($warden->can('create', User::class))->toBeTrue();
            expect($warden->can('update', $user1))->toBeTrue();
            expect($warden->can('update', $account2))->toBeTrue();
        });

        test('displays message when no orphaned abilities exist', function (): void {
            // Arrange
            $warden = $this->bouncer($user = User::query()->create())->dontCache();
            $account = Account::query()->create();
            $warden->allow($user)->to('update', $user);
            $warden->allow($user)->to('update', $account);

            // Assert initial state
            expect(Ability::query()->count())->toEqual(2);

            // Act & Assert
            $this
                ->artisan('warden:clean --orphaned')
                ->expectsOutput('No orphaned abilities.');

            expect(Ability::query()->count())->toEqual(2);
        });

        test('removes both unassigned and orphaned abilities when no flags provided', function (): void {
            // Arrange
            $warden = $this->bouncer($user1 = User::query()->create())->dontCache();
            $account1 = Account::query()->create();
            $account2 = Account::query()->create();
            $user2 = User::query()->create();

            $warden->allow($user1)->to('update', $user1);
            $warden->allow($user1)->to('update', $user2);
            $warden->allow($user1)->to('update', $account1);
            $warden->allow($user1)->to('update', $account2);

            // Act - Create unassigned and orphaned abilities
            $warden->disallow($user1)->to('update', $user1);
            $account1->delete();

            // Assert initial state
            expect(Ability::query()->count())->toEqual(4);

            // Act
            $this
                ->artisan('warden:clean')
                ->expectsOutput('Deleted 1 unassigned ability.')
                ->expectsOutput('Deleted 1 orphaned ability.');

            // Assert
            expect(Ability::query()->count())->toEqual(2);
        });
    });
});
