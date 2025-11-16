<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Database\Ability;
use Cline\Warden\Database\Role;
use Tests\Concerns\TestsClipboards;
use Tests\Fixtures\Models\Account;
use Tests\Fixtures\Models\User;

uses(TestsClipboards::class);

describe('Bouncer Simple API', function (): void {
    describe('Happy Paths', function (): void {
        test('allows and removes abilities using different ability formats', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();
            $editSite = Ability::query()->create(['name' => 'edit-site']);
            $banUsers = Ability::query()->create(['name' => 'ban-users']);
            $accessDashboard = Ability::query()->create(['name' => 'access-dashboard']);

            // Act
            $warden->allow($user)->to('edit-site');
            $warden->allow($user)->to([$banUsers, $accessDashboard->id]);

            // Assert
            expect($warden->can('edit-site'))->toBeTrue();
            expect($warden->can('ban-users'))->toBeTrue();
            expect($warden->can('access-dashboard'))->toBeTrue();

            // Act - Remove abilities
            $warden->disallow($user)->to($editSite);
            $warden->disallow($user)->to('ban-users');
            $warden->disallow($user)->to($accessDashboard->id);

            // Assert
            expect($warden->cannot('edit-site'))->toBeTrue();
            expect($warden->cannot('ban-users'))->toBeTrue();
            expect($warden->cannot('access-dashboard'))->toBeTrue();
        })->with('bouncerProvider');

        test('allows and removes abilities for everyone globally', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();
            $editSite = Ability::query()->create(['name' => 'edit-site']);
            $banUsers = Ability::query()->create(['name' => 'ban-users']);
            $accessDashboard = Ability::query()->create(['name' => 'access-dashboard']);

            // Act
            $warden->allowEveryone()->to('edit-site');
            $warden->allowEveryone()->to([$banUsers, $accessDashboard->id]);

            // Assert
            expect($warden->can('edit-site'))->toBeTrue();
            expect($warden->can('ban-users'))->toBeTrue();
            expect($warden->can('access-dashboard'))->toBeTrue();

            // Act - Remove abilities
            $warden->disallowEveryone()->to($editSite);
            $warden->disallowEveryone()->to('ban-users');
            $warden->disallowEveryone()->to($accessDashboard->id);

            // Assert
            expect($warden->cannot('edit-site'))->toBeTrue();
            expect($warden->cannot('ban-users'))->toBeTrue();
            expect($warden->cannot('access-dashboard'))->toBeTrue();
        })->with('bouncerProvider');

        test('allows and removes wildcard abilities granting all permissions', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            // Act
            $warden->allow($user)->to('*');

            // Assert
            expect($warden->can('edit-site'))->toBeTrue();
            expect($warden->can('ban-users'))->toBeTrue();
            expect($warden->can('*'))->toBeTrue();

            // Act - Remove wildcard
            $warden->disallow($user)->to('*');

            // Assert
            expect($warden->cannot('edit-site'))->toBeTrue();
        })->with('bouncerProvider');

        test('assigns and retracts roles from user', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();
            $warden->allow('admin')->to('ban-users');
            $editor = Role::query()->create(['name' => 'editor']);
            $warden->allow($editor)->to('ban-users');

            // Act
            $warden->assign('admin')->to($user);
            $warden->assign($editor)->to($user);

            // Assert
            expect($warden->can('ban-users'))->toBeTrue();

            // Act - Remove roles
            $warden->retract('admin')->from($user);
            $warden->retract($editor)->from($user);

            // Assert
            expect($warden->cannot('ban-users'))->toBeTrue();
        })->with('bouncerProvider');

        test('assigns and retracts multiple roles at once', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();
            $admin = role('admin');
            $editor = role('editor');
            $reviewer = role('reviewer');

            // Act
            $warden->assign(collect([$admin, 'editor', $reviewer->id]))->to($user);

            // Assert
            expect($warden->is($user)->all($admin->id, $editor, 'reviewer'))->toBeTrue();

            // Act - Remove some roles
            $warden->retract(['admin', $editor])->from($user);

            // Assert
            expect($warden->is($user)->notAn($admin->name, 'editor'))->toBeTrue();
        })->with('bouncerProvider');

        test('assigns and retracts roles for multiple users at once', function ($provider): void {
            // Arrange
            [$warden, $user1, $user2] = $provider(2);

            // Act
            $warden->assign(['admin', 'editor'])->to([$user1, $user2]);

            // Assert
            expect($warden->is($user1)->all('admin', 'editor'))->toBeTrue();
            expect($warden->is($user2)->an('admin', 'editor'))->toBeTrue();

            // Act - Remove roles
            $warden->retract('admin')->from($user1);
            $warden->retract(collect(['admin', 'editor']))->from($user2);

            // Assert
            expect($warden->is($user1)->notAn('admin'))->toBeTrue();
            expect($warden->is($user1)->an('editor'))->toBeTrue();
            expect($warden->is($user1)->an('admin', 'editor'))->toBeTrue();
        })->with('bouncerProvider');

        test('checks user has specific roles correctly', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            // Assert - No roles initially
            expect($warden->is($user)->notA('moderator'))->toBeTrue();
            expect($warden->is($user)->notAn('editor'))->toBeTrue();
            expect($warden->is($user)->an('admin'))->toBeFalse();

            // Arrange - New user with roles
            $warden = $this->bouncer($user = User::query()->create());
            $warden->assign('moderator')->to($user);
            $warden->assign('editor')->to($user);

            // Assert
            expect($warden->is($user)->a('moderator'))->toBeTrue();
            expect($warden->is($user)->an('editor'))->toBeTrue();
            expect($warden->is($user)->notAn('editor'))->toBeFalse();
            expect($warden->is($user)->an('admin'))->toBeFalse();
        })->with('bouncerProvider');

        test('checks user has multiple roles correctly', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            // Assert - No roles initially
            expect($warden->is($user)->notAn('editor', 'moderator'))->toBeTrue();
            expect($warden->is($user)->notAn('admin', 'moderator'))->toBeTrue();

            // Arrange - Assign roles
            $warden = $this->bouncer($user = User::query()->create());
            $warden->assign('moderator')->to($user);
            $warden->assign('editor')->to($user);

            // Assert
            expect($warden->is($user)->a('subscriber', 'moderator'))->toBeTrue();
            expect($warden->is($user)->an('admin', 'editor'))->toBeTrue();
            expect($warden->is($user)->all('editor', 'moderator'))->toBeTrue();
            expect($warden->is($user)->notAn('editor', 'moderator'))->toBeFalse();
            expect($warden->is($user)->all('admin', 'moderator'))->toBeFalse();
        })->with('bouncerProvider');

        test('creates an empty role model', function (): void {
            // Arrange
            $warden = $this->bouncer($user = User::query()->create());

            // Act & Assert
            expect(
                new Role(),
            )->toBeInstanceOf(Role::class);
        });

        test('fills a role model with attributes', function (): void {
            // Arrange
            $warden = $this->bouncer($user = User::query()->create());

            // Act
            $role = Role::query()->create(['name' => 'test-role']);

            // Assert
            expect($role)->toBeInstanceOf(Role::class);
            expect($role->name)->toEqual('test-role');
        });

        test('creates an empty ability model', function (): void {
            // Arrange
            $warden = $this->bouncer($user = User::query()->create());

            // Act & Assert
            expect($warden->ability())->toBeInstanceOf(Ability::class);
        });

        test('fills an ability model with attributes', function (): void {
            // Arrange
            $warden = $this->bouncer($user = User::query()->create());

            // Act
            $ability = $warden->ability(['name' => 'test-ability']);

            // Assert
            expect($ability)->toBeInstanceOf(Ability::class);
            expect($ability->name)->toEqual('test-ability');
        });

        test('allows abilities through defined callback returning true', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();
            $warden->define('edit', function ($user, $account): bool {
                if (!$account instanceof Account) {
                    return null;
                }

                return $user->id === $account->user_id;
            });

            // Act & Assert
            expect($warden->can('edit', new Account(['user_id' => $user->id])))->toBeTrue();
            expect($warden->can('edit', new Account(['user_id' => 99])))->toBeFalse();
        })->with('bouncerProvider');

        test('returns response with correct message from authorize method', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();
            $warden->allow($user)->to('have-fun');
            $warden->allow($user)->to('enjoy-life');

            // Act & Assert
            expect($warden->authorize('enjoy-life')->message())->toEqual('Bouncer granted permission via ability #2');
            expect($warden->authorize('have-fun')->message())->toEqual('Bouncer granted permission via ability #1');
        })->with('bouncerProvider');

        test('deletes pivot table records when role is deleted', function (): void {
            // Arrange
            $warden = $this->bouncer();
            $admin = Role::query()->create(['name' => 'admin']);
            $editor = Role::query()->create(['name' => 'editor']);
            $warden->allow($admin)->everything();
            $warden->allow($editor)->to('edit', User::class);

            // Assert initial state
            expect($this->db()->table('permissions')->count())->toEqual(2);

            // Act
            $admin->delete();

            // Assert
            expect($this->db()->table('permissions')->count())->toEqual(1);
        });
    });

    describe('Sad Paths', function (): void {
        test('throws exception when authorizing unauthorized abilities', function ($provider): void {
            // Arrange
            [$warden] = $provider();
            $threw = false;

            // Act
            try {
                $warden->authorize('be-miserable');
            } catch (Exception) {
                $threw = true;
            }

            // Assert
            expect($threw)->toBeTrue();
        })->with('bouncerProvider');

        test('disallows abilities on roles preventing access', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();
            $warden->allow('admin')->to('edit-site');

            // Act
            $warden->disallow('admin')->to('edit-site');
            $warden->assign('admin')->to($user);

            // Assert
            expect($warden->cannot('edit-site'))->toBeTrue();
        })->with('bouncerProvider');
    });

    describe('Edge Cases', function (): void {
        test('ignores duplicate ability allowances for users and roles', function ($provider): void {
            // Arrange
            [$warden, $user1, $user2] = $provider(2);

            // Act - Duplicate user abilities
            $warden->allow($user1)->to('ban-users');
            $warden->allow($user1)->to('ban-users');
            $warden->allow($user1)->to('ban', $user2);
            $warden->allow($user1)->to('ban', $user2);

            // Assert
            expect($user1->abilities)->toHaveCount(2);

            // Arrange - Role
            $admin = Role::query()->create(['name' => 'admin']);

            // Act - Duplicate role abilities
            $warden->allow($admin)->to('ban-users');
            $warden->allow($admin)->to('ban-users');
            $warden->allow($admin)->to('ban', $user1);
            $warden->allow($admin)->to('ban', $user1);

            // Assert
            expect($admin->abilities)->toHaveCount(2);
        })->with('bouncerProvider');

        test('ignores duplicate role assignments to user', function (): void {
            // Arrange
            $warden = $this->bouncer($user = User::query()->create());

            // Act
            $warden->assign('admin')->to($user);
            $warden->assign('admin')->to($user);

            // Assert
            expect($user->roles)->toHaveCount(1);
        });

        test('keeps user and role abilities separate when they have matching IDs', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();
            // Since the user is the first user created, its ID is 1.
            // Creating admin as the first role, it'll have its ID
            // set to 1. Let's test that they're kept separate.
            $warden->allow($user)->to('edit-site');
            $warden->allow('admin')->to('edit-site');

            // Act
            $warden->disallow('admin')->to('edit-site');

            // Assert
            expect($warden->can('edit-site'))->toBeTrue();
        })->with('bouncerProvider');
    });
});
