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
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\TestsClipboards;
use Tests\Fixtures\Models\Account;
use Tests\Fixtures\Models\User;

uses(TestsClipboards::class);

describe('Syncing Roles and Abilities', function (): void {
    describe('Happy Paths', function (): void {
        test('syncs user roles by replacing existing with new set', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();
            $admin = role('admin');
            $editor = role('editor');
            $reviewer = role('reviewer');
            $subscriber = role('subscriber');

            $user->assign([$admin, $editor]);
            expect($warden->is($user)->all($admin, $editor))->toBeTrue();

            // Act
            $warden->sync($user)->roles([$editor->id, $reviewer->name, $subscriber]);

            // Assert
            expect($warden->is($user)->all($editor, $reviewer, $subscriber))->toBeTrue();
            expect($warden->is($user)->notAn($admin->name))->toBeTrue();
        })->with('bouncerProvider');

        test('syncs user abilities by replacing existing with new set', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            $editSite = Ability::query()->create(['name' => 'edit-site']);
            $banUsers = Ability::query()->create(['name' => 'ban-users']);
            Ability::query()->create(['name' => 'access-dashboard']);

            $warden->allow($user)->to([$editSite, $banUsers]);

            expect($warden->can('edit-site'))->toBeTrue();
            expect($warden->can('ban-users'))->toBeTrue();
            expect($warden->cannot('access-dashboard'))->toBeTrue();

            // Act
            $warden->sync($user)->abilities([$banUsers->id, 'access-dashboard']);

            // Assert
            expect($warden->cannot('edit-site'))->toBeTrue();
            expect($warden->can('ban-users'))->toBeTrue();
            expect($warden->can('access-dashboard'))->toBeTrue();
        })->with('bouncerProvider');

        test('syncs user abilities with ability-to-model map', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            $deleteUser = Ability::createForModel($user, 'delete');
            $createAccounts = Ability::createForModel(Account::class, 'create');

            $warden->allow($user)->to([$deleteUser, $createAccounts]);

            expect($warden->can('delete', $user))->toBeTrue();
            expect($warden->can('create', Account::class))->toBeTrue();

            // Act
            $warden->sync($user)->abilities([
                'access-dashboard',
                'create' => Account::class,
                'view' => $user,
            ]);

            // Assert
            expect($warden->cannot('delete', $user))->toBeTrue();
            expect($warden->cannot('view', User::class))->toBeTrue();
            expect($warden->can('create', Account::class))->toBeTrue();
            expect($warden->can('view', $user))->toBeTrue();
            expect($warden->can('access-dashboard'))->toBeTrue();
        })->with('bouncerProvider');

        test('syncs forbidden abilities by replacing existing forbidden set', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            $editSite = Ability::query()->create(['name' => 'edit-site']);
            $banUsers = Ability::query()->create(['name' => 'ban-users']);
            Ability::query()->create(['name' => 'access-dashboard']);

            $warden->allow($user)->everything();
            $warden->forbid($user)->to([$editSite, $banUsers->id]);

            expect($warden->cannot('edit-site'))->toBeTrue();
            expect($warden->cannot('ban-users'))->toBeTrue();
            expect($warden->can('access-dashboard'))->toBeTrue();

            // Act
            $warden->sync($user)->forbiddenAbilities([$banUsers->id, 'access-dashboard']);

            // Assert
            expect($warden->can('edit-site'))->toBeTrue();
            expect($warden->cannot('ban-users'))->toBeTrue();
            expect($warden->cannot('access-dashboard'))->toBeTrue();
        })->with('bouncerProvider');

        test('syncs role abilities by replacing existing ability set for role', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            $editSite = Ability::query()->create(['name' => 'edit-site']);
            $banUsers = Ability::query()->create(['name' => 'ban-users']);
            Ability::query()->create(['name' => 'access-dashboard']);

            $warden->assign('admin')->to($user);
            $warden->allow('admin')->to([$editSite, $banUsers]);

            expect($warden->can('edit-site'))->toBeTrue();
            expect($warden->can('ban-users'))->toBeTrue();
            expect($warden->cannot('access-dashboard'))->toBeTrue();

            // Act
            $warden->sync('admin')->abilities([$banUsers->id, 'access-dashboard']);

            // Assert
            expect($warden->cannot('edit-site'))->toBeTrue();
            expect($warden->can('ban-users'))->toBeTrue();
            expect($warden->can('access-dashboard'))->toBeTrue();
        })->with('bouncerProvider');

        test('skips delete queries when syncing roles that have not changed', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            $admin = role('admin');
            $editor = role('editor');

            // Initial sync to assign roles
            $warden->sync($user)->roles([$admin, $editor]);
            expect($warden->is($user)->all($admin, $editor))->toBeTrue();

            // Act - Sync again with same roles
            DB::enableQueryLog();
            $warden->sync($user)->roles([$admin, $editor]);
            $queries = DB::getQueryLog();
            DB::disableQueryLog();

            // Assert - Verify no DELETE queries were executed (detach returned early)
            $deleteQueries = array_filter($queries, fn (array $q): bool => str_contains(mb_strtolower((string) $q['query']), 'delete'));
            expect($deleteQueries)->toHaveCount(0, 'Expected no DELETE queries when detach array is empty');

            // Verify roles remain assigned
            expect($warden->is($user)->all($admin, $editor))->toBeTrue();
        })->with('bouncerProvider');

        test('sync creates non-existent roles automatically', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            // Ensure roles don't exist
            Role::query()->whereIn('name', ['sync-test-1', 'sync-test-2'])->delete();

            // Act - Sync with role names that don't exist yet
            $warden->sync($user)->roles(['sync-test-1', 'sync-test-2']);

            // Assert - Verify roles were created
            expect(Role::query()->where('name', 'sync-test-1')->exists())->toBeTrue();
            expect(Role::query()->where('name', 'sync-test-2')->exists())->toBeTrue();

            // Assert - Verify user has the roles
            expect($warden->is($user)->all('sync-test-1', 'sync-test-2'))->toBeTrue();
        })->with('bouncerProvider');
    });

    describe('Edge Cases', function (): void {
        test('syncing user abilities does not alter role abilities with same ID', function (): void {
            // Arrange
            $user = User::query()->create([]);
            $warden = $this->bouncer($user);
            $role = Role::query()->create(['name' => 'alcoholic']);

            $warden->allow($user)->to(['eat', 'drink']);
            $warden->allow($role)->to('drink');

            // Act
            $warden->sync($user)->abilities(['eat']);

            // Assert
            expect($user->can('eat'))->toBeTrue();
            expect($user->cannot('drink'))->toBeTrue();
            expect($role->can('drink'))->toBeTrue();
        });

        test('syncing abilities for one model type does not affect another type with same ID', function (): void {
            // Arrange
            $user = User::query()->create([]);
            $account = Account::query()->create([]);

            $warden = $this->bouncer();

            $warden->allow($user)->to('relax');
            $warden->allow($account)->to('relax');

            expect($user->can('relax'))->toBeTrue();
            expect($account->can('relax'))->toBeTrue();

            // Act
            $warden->sync($user)->abilities([]);

            // Assert
            expect($user->cannot('relax'))->toBeTrue();
            expect($account->can('relax'))->toBeTrue();
        });

        test('syncing roles for one model type does not affect another type with same ID', function (): void {
            // Arrange - Create user first, then account with matching ID
            $user = User::query()->create();
            $account = Account::query()->create();
            $account->forceFill(['id' => $user->id])->save();

            expect($user->id)->toBe($account->id);

            $warden = $this->bouncer();

            $warden->assign('admin')->to($user);
            $warden->assign('admin')->to($account);

            expect($user->isAn('admin'))->toBeTrue();
            expect($account->isAn('admin'))->toBeTrue();

            // Act
            $warden->sync($user)->roles([]);

            // Assert
            expect($user->isNotAn('admin'))->toBeTrue();
            expect($account->isAn('admin'))->toBeTrue();
        });
    });

    describe('Regression Tests - Keymap Support', function (): void {
        test('sync uses keymap value for pivot query preventing incorrect assignments', function (): void {
            // Arrange - Configure keymap
            Models::enforceMorphKeyMap([
                User::class => 'id',
                Role::class => 'id',
            ]);

            $user1 = User::query()->create(['name' => 'Alice']);
            $user2 = User::query()->create(['name' => 'Bob']);
            $user3 = User::query()->create(['name' => 'Charlie']);

            $warden = $this->bouncer($user1)->dontCache();

            // Initial role assignments (sync will create roles if they don't exist)
            $warden->assign(['admin', 'editor'])->to($user1);
            $warden->assign(['moderator'])->to($user2);
            $warden->assign(['subscriber'])->to($user3);

            // Act - Sync user1's roles (should replace with new set)
            $warden->sync($user1)->roles(['manager', 'viewer']);

            // Assert - User1 has only synced roles
            expect($warden->is($user1)->a('manager'))->toBeTrue();
            expect($warden->is($user1)->a('viewer'))->toBeTrue();
            expect($warden->is($user1)->notAn('admin'))->toBeTrue();
            expect($warden->is($user1)->notAn('editor'))->toBeTrue();

            // Assert - Other users unaffected (without fix, sync could affect wrong users)
            expect($warden->is($user2)->a('moderator'))->toBeTrue();
            expect($warden->is($user3)->a('subscriber'))->toBeTrue();

            Models::reset();
        });

        test('prevents role sync leakage between users with custom keymap', function (): void {
            // Arrange - This tests the critical bug where keymap values weren't used
            Models::enforceMorphKeyMap([
                User::class => 'id',
                Role::class => 'id',
            ]);

            $alice = User::query()->create(['name' => 'Alice']);
            $bob = User::query()->create(['name' => 'Bob']);
            $charlie = User::query()->create(['name' => 'Charlie']);

            $warden = $this->bouncer($alice)->dontCache();

            // Initial assignments (assign will create roles if they don't exist)
            $warden->assign(['admin', 'editor'])->to($alice);
            $warden->assign(['moderator', 'editor'])->to($bob);
            $warden->assign(['subscriber'])->to($charlie);

            // Act - Sync only Alice's roles
            $warden->sync($alice)->roles(['owner']);

            // Assert - Alice synced correctly
            expect($warden->is($alice)->an('owner'))->toBeTrue();
            expect($warden->is($alice)->notAn('admin'))->toBeTrue();
            expect($warden->is($alice)->notAn('editor'))->toBeTrue();

            // Assert - Bob and Charlie unchanged
            expect($warden->is($bob)->a('moderator'))->toBeTrue();
            expect($warden->is($bob)->an('editor'))->toBeTrue();
            expect($warden->is($charlie)->a('subscriber'))->toBeTrue();

            Models::reset();
        });
    });
});

/**
 * @author Brian Faust <brian@cline.sh>
 */
