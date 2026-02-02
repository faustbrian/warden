<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Database\Role;
use Cline\Warden\Http\Middleware\HasRole;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\TestsClipboards;
use Tests\Fixtures\Models\User;

uses(TestsClipboards::class);

describe('HasRole Middleware', function (): void {
    describe('Happy Paths', function (): void {
        test('allows access when user has the required role', function ($provider): void {
            // Arrange
            $provider();
            $user = User::query()->create(['name' => 'John Doe']);
            $role = Role::query()->create(['name' => 'admin']);
            $user->assign('admin');

            Route::get('/test', fn (): string => 'success')->middleware(HasRole::to('admin'));

            // Act
            $response = $this->actingAs($user)->get('/test');

            // Assert
            $response->assertOk();

            expect($response->getContent())->toBe('success');
        })->with('bouncerProvider');

        test('allows access when user has any of the required roles', function ($provider): void {
            // Arrange
            $provider();
            $user = User::query()->create(['name' => 'John Doe']);
            Role::query()->create(['name' => 'admin']);
            Role::query()->create(['name' => 'moderator']);
            $user->assign('moderator');

            Route::get('/test', fn (): string => 'success')->middleware(HasRole::toAny('admin', 'moderator'));

            // Act
            $response = $this->actingAs($user)->get('/test');

            // Assert
            $response->assertOk();

            expect($response->getContent())->toBe('success');
        })->with('bouncerProvider');
    });

    describe('Sad Paths', function (): void {
        test('denies access when user does not have the required role', function ($provider): void {
            // Arrange
            $provider();
            $user = User::query()->create(['name' => 'John Doe']);
            Role::query()->create(['name' => 'admin']);
            Role::query()->create(['name' => 'user']);
            $user->assign('user');

            Route::get('/test', fn (): string => 'success')->middleware(HasRole::to('admin'));

            // Act
            $response = $this->actingAs($user)->get('/test');

            // Assert
            $response->assertForbidden();
        })->with('bouncerProvider');

        test('denies access when user has none of the required roles', function ($provider): void {
            // Arrange
            $provider();
            $user = User::query()->create(['name' => 'John Doe']);
            Role::query()->create(['name' => 'admin']);
            Role::query()->create(['name' => 'moderator']);
            Role::query()->create(['name' => 'user']);
            $user->assign('user');

            Route::get('/test', fn (): string => 'success')->middleware(HasRole::toAny('admin', 'moderator'));

            // Act
            $response = $this->actingAs($user)->get('/test');

            // Assert
            $response->assertForbidden();
        })->with('bouncerProvider');

        test('denies access when user is not authenticated', function (): void {
            // Arrange
            Route::get('/test', fn (): string => 'success')->middleware(HasRole::to('admin'));

            // Act
            $response = $this->get('/test');

            // Assert
            $response->assertForbidden();
        });
    });

    describe('Edge Cases', function (): void {
        test('generates correct middleware string for single role', function (): void {
            // Act
            $middleware = HasRole::to('admin');

            // Assert
            expect($middleware)->toBe('role:admin');
        });

        test('generates correct middleware string for multiple roles', function (): void {
            // Act
            $middleware = HasRole::toAny('admin', 'moderator', 'user');

            // Assert
            expect($middleware)->toBe('role:admin|moderator|user');
        });
    });
});
