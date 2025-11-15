<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Database\Ability;
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
    });

    describe('Edge Cases', function (): void {
        test('syncing user abilities does not alter role abilities with same ID', function (): void {
            // Arrange
            $user = User::query()->create(['id' => 1]);
            $warden = $this->bouncer($user);
            $role = Role::query()->create(['id' => 1, 'name' => 'alcoholic']);

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
            $user = User::query()->create(['id' => 1]);
            $account = Account::query()->create(['id' => 1]);

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
            // Arrange
            $user = User::query()->create(['id' => 1]);
            $account = Account::query()->create(['id' => 1]);

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
});

/**
 * @author Brian Faust <brian@cline.sh>
 */
