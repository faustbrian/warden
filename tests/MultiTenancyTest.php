<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Contracts\ScopeInterface;
use Cline\Warden\Database\Models;
use Cline\Warden\Database\Scope\Scope;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\TestsClipboards;
use Tests\Fixtures\Models\User;
use Tests\Fixtures\MultiTenancyNullScopeStub;

uses(TestsClipboards::class);

/**
 * Reset any scopes that have been applied in a test.
 */
afterEach(function (): void {
    Models::scope(
        new Scope(),
    );
});

describe('Multi-Tenancy Scoping', function (): void {
    describe('Happy Paths', function (): void {
        test('sets and retrieves current scope value successfully', function (): void {
            // Arrange
            $warden = $this->bouncer();

            // Act & Assert
            expect($warden->scope()->get())->toBeNull();

            $warden->scope()->to(1);
            expect($warden->scope()->get())->toEqual(1);
        });

        test('removes current scope and resets to null', function (): void {
            // Arrange
            $warden = $this->bouncer();
            $warden->scope()->to(1);
            expect($warden->scope()->get())->toEqual(1);

            // Act
            $warden->scope()->remove();

            // Assert
            expect($warden->scope()->get())->toBeNull();
        });

        test('automatically scopes newly created roles and abilities', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();
            $warden->scope()->to(1);

            // Act
            $warden->allow('admin')->to('create', User::class);
            $warden->assign('admin')->to($user);

            // Assert
            expect($warden->ability()->query()->value('scope'))->toEqual(1);
            expect($warden->role()->query()->value('scope'))->toEqual(1);
            expect($this->db()->table('permissions')->value('scope'))->toEqual(1);
            expect($this->db()->table('assigned_roles')->value('scope'))->toEqual(1);
        })->with('bouncerProvider');

        test('syncs roles within current scope without affecting other scopes', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            $warden->scope()->to(1);
            $warden->assign(['writer', 'reader'])->to($user);

            $warden->scope()->to(2);
            $warden->assign(['eraser', 'thinker'])->to($user);

            // Act
            $warden->scope()->to(1);
            $warden->sync($user)->roles(['writer']);

            // Assert
            expect($warden->is($user)->a('writer'))->toBeTrue();
            expect($user->roles()->count())->toEqual(1);

            $warden->scope()->to(2);
            expect($warden->is($user)->all('eraser', 'thinker'))->toBeTrue();
            expect($warden->is($user)->a('writer', 'reader'))->toBeFalse();

            $warden->sync($user)->roles(['thinker']);

            expect($warden->is($user)->a('thinker'))->toBeTrue();
            expect($user->roles()->count())->toEqual(1);
        })->with('bouncerProvider');

        test('syncs abilities within current scope without affecting other scopes', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            $warden->scope()->to(1);
            $warden->allow($user)->to(['write', 'read']);

            $warden->scope()->to(2);
            $warden->allow($user)->to(['erase', 'think']);

            // Act
            $warden->scope()->to(1);
            $warden->sync($user)->abilities(['write', 'color']);

            // Assert - "read" is not deleted
            expect($warden->can('write'))->toBeTrue();
            expect($user->abilities()->count())->toEqual(2);

            $warden->scope()->to(2);
            expect($warden->can('erase'))->toBeTrue();
            expect($warden->can('think'))->toBeTrue();
            expect($warden->can('write'))->toBeFalse();
            expect($warden->can('read'))->toBeFalse();

            $warden->sync($user)->abilities(['think']);

            expect($warden->can('think'))->toBeTrue();
            expect($user->abilities()->count())->toEqual(1);
        })->with('bouncerProvider');

        test('filters relation queries to only return scoped records', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            $warden->scope()->to(1);
            $warden->allow($user)->to('create', User::class);

            $warden->scope()->to(2);
            $warden->allow($user)->to('delete', User::class);

            // Act & Assert
            $warden->scope()->to(1);
            $abilities = $user->abilities()->get();

            expect($abilities)->toHaveCount(1);
            expect($abilities->first()->scope)->toEqual(1);
            expect($abilities->first()->name)->toEqual('create');
            expect($warden->can('create', User::class))->toBeTrue();
            expect($warden->cannot('delete', User::class))->toBeTrue();

            $warden->scope()->to(2);
            $abilities = $user->abilities()->get();

            expect($abilities)->toHaveCount(1);
            expect($abilities->first()->scope)->toEqual(2);
            expect($abilities->first()->name)->toEqual('delete');
            expect($warden->can('delete', User::class))->toBeTrue();
            expect($warden->cannot('create', User::class))->toBeTrue();
        })->with('bouncerProvider');

        test('scopes relation queries exclusively without affecting authorization', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            $warden->scope()->to(1)->onlyRelations();
            $warden->allow($user)->to('create', User::class);

            $warden->scope()->to(2);
            $warden->allow($user)->to('delete', User::class);

            // Act & Assert
            $warden->scope()->to(1);
            $abilities = $user->abilities()->get();

            expect($abilities)->toHaveCount(1);
            expect($abilities->first()->scope)->toBeNull();
            expect($abilities->first()->name)->toEqual('create');
            expect($warden->can('create', User::class))->toBeTrue();
            expect($warden->cannot('delete', User::class))->toBeTrue();

            $warden->scope()->to(2);
            $abilities = $user->abilities()->get();

            expect($abilities)->toHaveCount(1);
            expect($abilities->first()->scope)->toBeNull();
            expect($abilities->first()->name)->toEqual('delete');
            expect($warden->can('delete', User::class))->toBeTrue();
            expect($warden->cannot('create', User::class))->toBeTrue();
        })->with('bouncerProvider');

        test('includes global abilities when using onlyRelations scoping', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            $warden->allow($user)->to('create', User::class);

            $warden->scope()->to(1)->onlyRelations();
            $warden->allow($user)->to('delete', User::class);

            // Act
            $abilities = $user->abilities()->orderBy('created_at')->orderBy('name')->get();

            // Assert
            expect($abilities)->toHaveCount(2);
            expect($abilities->first()->scope)->toBeNull();
            expect($abilities->first()->name)->toEqual('create');
            expect($warden->can('create', User::class))->toBeTrue();
            expect($warden->can('delete', User::class))->toBeTrue();
        })->with('bouncerProvider');

        test('forbids abilities only within current scope without affecting other scopes', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            $warden->scope()->to(1);
            $warden->allow($user)->to('create', User::class);

            $warden->scope()->to(2);
            $warden->allow($user)->to('create', User::class);
            $warden->forbid($user)->to('create', User::class);

            // Act & Assert
            $warden->scope()->to(1);
            expect($warden->can('create', User::class))->toBeTrue();

            $warden->unforbid($user)->to('create', User::class);

            $warden->scope()->to(2);
            expect($warden->cannot('create', User::class))->toBeTrue();
        })->with('bouncerProvider');

        test('disallows abilities only within current scope without affecting other scopes', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            $admin = $warden->role()->create(['name' => 'admin']);
            $user->assign($admin);

            $warden->scope()->to(1)->onlyRelations();
            $admin->allow('create', User::class);

            $warden->scope()->to(2);
            $admin->allow('create', User::class);
            $admin->disallow('create', User::class);

            // Act & Assert
            $warden->scope()->to(1);
            expect($warden->can('create', User::class))->toBeTrue();

            $warden->scope()->to(2);
            expect($warden->cannot('create', User::class))->toBeTrue();
        })->with('bouncerProvider');

        test('unforbids abilities only within current scope without affecting other scopes', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            $admin = $warden->role()->create(['name' => 'admin']);
            $user->assign($admin);

            $warden->scope()->to(1)->onlyRelations();
            $admin->allow()->everything();
            $admin->forbid()->to('create', User::class);

            $warden->scope()->to(2);
            $admin->allow()->everything();
            $admin->forbid()->to('create', User::class);
            $admin->unforbid()->to('create', User::class);

            // Act & Assert
            $warden->scope()->to(1);
            expect($warden->cannot('create', User::class))->toBeTrue();

            $warden->scope()->to(2);
            expect($warden->can('create', User::class))->toBeTrue();
        })->with('bouncerProvider');

        test('assigns and retracts roles within proper scope boundaries', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            $warden->scope()->to(1)->onlyRelations();
            $warden->assign('admin')->to($user);

            $warden->scope()->to(2);
            $warden->assign('admin')->to($user);
            $warden->retract('admin')->from($user);

            // Act & Assert
            $warden->scope()->to(1);
            expect($warden->is($user)->an('admin'))->toBeTrue();

            $warden->scope()->to(2);
            expect($warden->is($user)->an('admin'))->toBeFalse();

            $warden->scope()->to(null);
            expect($warden->is($user)->an('admin'))->toBeFalse();
        })->with('bouncerProvider');

        test('excludes role abilities from scope constraints when configured', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            $warden->scope()->to(1)->onlyRelations()->dontScopeRoleAbilities();
            $warden->allow('admin')->to('delete', User::class);

            $warden->scope()->to(2);
            $warden->assign('admin')->to($user);

            // Act & Assert
            expect($warden->can('delete', User::class))->toBeTrue();
        })->with('bouncerProvider');

        test('applies custom scope implementation correctly', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            $warden->scope(
                new MultiTenancyNullScopeStub(),
            )->to(1);

            $warden->allow($user)->to('delete', User::class);

            // Act
            $warden->scope()->to(2);

            // Assert
            expect($warden->can('delete', User::class))->toBeTrue();
        })->with('bouncerProvider');

        test('sets scope temporarily and restores previous value after callback', function (): void {
            // Arrange
            $warden = $this->bouncer();
            expect($warden->scope()->get())->toBeNull();

            // Act
            $result = $warden->scope()->onceTo(1, function () use ($warden): string {
                expect($warden->scope()->get())->toEqual(1);

                return 'result';
            });

            // Assert
            expect($result)->toEqual('result');
            expect($warden->scope()->get())->toBeNull();
        });

        test('onceTo works through interface type', function (): void {
            // Arrange
            $warden = $this->bouncer();
            $scope = $warden->scope();

            // Helper function to force interface type usage
            $callOnceTo = fn (ScopeInterface $s, int $value, callable $callback): mixed => $s->onceTo($value, $callback);

            // Act & Assert
            $result = $callOnceTo($scope, 2, function () use ($warden): string {
                expect($warden->scope()->get())->toEqual(2);

                return 'result';
            });
            expect($result)->toEqual('result');
        });

        test('removes scope temporarily and restores previous value after callback', function (): void {
            // Arrange
            $warden = $this->bouncer();
            $warden->scope()->to(1);

            // Act
            $result = $warden->scope()->removeOnce(function () use ($warden): string {
                expect($warden->scope()->get())->toEqual(null);

                return 'result';
            });

            // Assert
            expect($result)->toEqual('result');
            expect($warden->scope()->get())->toEqual(1);
        });
    });

    describe('Edge Cases', function (): void {
        test('prevents scoped abilities from working when scope is removed', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            $warden->scope()->to(1);
            $warden->allow($user)->to(['write', 'read']);

            expect($warden->can('write'))->toBeTrue();
            expect($warden->can('read'))->toBeTrue();
            expect($user->abilities()->count())->toEqual(2);

            // Act
            $warden->scope()->to(null);

            // Assert
            expect($warden->can('write'))->toBeFalse();
            expect($warden->can('read'))->toBeFalse();
        })->with('bouncerProvider');
    });
});

/**
 * @author Brian Faust <brian@cline.sh>
 */
