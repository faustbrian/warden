<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Database\Role;
use Cline\Warden\Http\Middleware\HasRoleOrPermission;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\TestsClipboards;
use Tests\Fixtures\Models\User;

uses(TestsClipboards::class);

describe('HasRoleOrPermission Middleware', function (): void {
    describe('Happy Paths', function (): void {
        test('allows access when user has the required role', function ($provider): void {
            // Arrange
            $provider();
            $user = User::query()->create(['name' => 'John Doe']);
            Role::query()->create(['name' => 'admin']);
            $user->assign('admin');

            Route::get('/test', fn (): string => 'success')->middleware(HasRoleOrPermission::to('admin'));

            // Act
            $response = $this->actingAs($user)->get('/test');

            // Assert
            $response->assertOk();

            expect($response->getContent())->toBe('success');
        })->with('bouncerProvider');

        test('allows access when user has the required permission', function ($provider): void {
            // Arrange
            $provider();
            $user = User::query()->create(['name' => 'John Doe']);
            $user->allow('edit-posts');

            Route::get('/test', fn (): string => 'success')->middleware(HasRoleOrPermission::to('edit-posts'));

            // Act
            $response = $this->actingAs($user)->get('/test');

            // Assert
            $response->assertOk();

            expect($response->getContent())->toBe('success');
        })->with('bouncerProvider');

        test('allows access when user has any of the required roles or permissions', function ($provider): void {
            // Arrange
            $provider();
            $user = User::query()->create(['name' => 'John Doe']);
            Role::query()->create(['name' => 'admin']);
            $user->assign('admin');

            Route::get('/test', fn (): string => 'success')->middleware(HasRoleOrPermission::toAny('admin', 'edit-posts'));

            // Act
            $response = $this->actingAs($user)->get('/test');

            // Assert
            $response->assertOk();

            expect($response->getContent())->toBe('success');
        })->with('bouncerProvider');

        test('allows access when user has permission but not role from mixed list', function ($provider): void {
            // Arrange
            $provider();
            $user = User::query()->create(['name' => 'John Doe']);
            Role::query()->create(['name' => 'admin']);
            $user->allow('edit-posts');

            Route::get('/test', fn (): string => 'success')->middleware(HasRoleOrPermission::toAny('admin', 'edit-posts'));

            // Act
            $response = $this->actingAs($user)->get('/test');

            // Assert
            $response->assertOk();

            expect($response->getContent())->toBe('success');
        })->with('bouncerProvider');
    });

    describe('Sad Paths', function (): void {
        test('denies access when user has neither role nor permission', function ($provider): void {
            // Arrange
            $provider();
            $user = User::query()->create(['name' => 'John Doe']);
            Role::query()->create(['name' => 'admin']);
            Role::query()->create(['name' => 'user']);
            $user->assign('user');
            $user->allow('view-posts');

            Route::get('/test', fn (): string => 'success')->middleware(HasRoleOrPermission::to('admin'));

            // Act
            $response = $this->actingAs($user)->get('/test');

            // Assert
            $response->assertForbidden();
        })->with('bouncerProvider');

        test('denies access when user has none of the required roles or permissions', function ($provider): void {
            // Arrange
            $provider();
            $user = User::query()->create(['name' => 'John Doe']);
            Role::query()->create(['name' => 'admin']);
            Role::query()->create(['name' => 'moderator']);
            Role::query()->create(['name' => 'user']);
            $user->assign('user');
            $user->allow('view-posts');

            Route::get('/test', fn (): string => 'success')->middleware(HasRoleOrPermission::toAny('admin', 'moderator', 'edit-posts'));

            // Act
            $response = $this->actingAs($user)->get('/test');

            // Assert
            $response->assertForbidden();
        })->with('bouncerProvider');

        test('denies access when user is not authenticated', function (): void {
            // Arrange
            Route::get('/test', fn (): string => 'success')->middleware(HasRoleOrPermission::to('admin'));

            // Act
            $response = $this->get('/test');

            // Assert
            $response->assertForbidden();
        });
    });

    describe('Edge Cases', function (): void {
        test('generates correct middleware string for single role or permission', function (): void {
            // Act
            $middleware = HasRoleOrPermission::to('admin');

            // Assert
            expect($middleware)->toBe('role_or_permission:admin');
        });

        test('generates correct middleware string for multiple roles and permissions', function (): void {
            // Act
            $middleware = HasRoleOrPermission::toAny('admin', 'moderator', 'edit-posts');

            // Assert
            expect($middleware)->toBe('role_or_permission:admin|moderator|edit-posts');
        });
    });
});
