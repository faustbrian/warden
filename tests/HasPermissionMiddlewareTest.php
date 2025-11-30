<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Http\Middleware\HasPermission;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\TestsClipboards;
use Tests\Fixtures\Models\User;

uses(TestsClipboards::class);

describe('HasPermission Middleware', function (): void {
    describe('Happy Paths', function (): void {
        test('allows access when user has the required permission', function ($provider): void {
            // Arrange
            $provider();
            $user = User::query()->create(['name' => 'John Doe']);
            $user->allow('edit-posts');

            Route::get('/test', fn (): string => 'success')->middleware(HasPermission::to('edit-posts'));

            // Act
            $response = $this->actingAs($user)->get('/test');

            // Assert
            $response->assertOk();

            expect($response->getContent())->toBe('success');
        })->with('bouncerProvider');

        test('allows access when user has any of the required permissions', function ($provider): void {
            // Arrange
            $provider();
            $user = User::query()->create(['name' => 'John Doe']);
            $user->allow('edit-posts');

            Route::get('/test', fn (): string => 'success')->middleware(HasPermission::toAny('edit-posts', 'delete-posts'));

            // Act
            $response = $this->actingAs($user)->get('/test');

            // Assert
            $response->assertOk();

            expect($response->getContent())->toBe('success');
        })->with('bouncerProvider');
    });

    describe('Sad Paths', function (): void {
        test('denies access when user does not have the required permission', function ($provider): void {
            // Arrange
            $provider();
            $user = User::query()->create(['name' => 'John Doe']);
            $user->allow('view-posts');

            Route::get('/test', fn (): string => 'success')->middleware(HasPermission::to('edit-posts'));

            // Act
            $response = $this->actingAs($user)->get('/test');

            // Assert
            $response->assertForbidden();
        })->with('bouncerProvider');

        test('denies access when user has none of the required permissions', function ($provider): void {
            // Arrange
            $provider();
            $user = User::query()->create(['name' => 'John Doe']);
            $user->allow('view-posts');

            Route::get('/test', fn (): string => 'success')->middleware(HasPermission::toAny('edit-posts', 'delete-posts'));

            // Act
            $response = $this->actingAs($user)->get('/test');

            // Assert
            $response->assertForbidden();
        })->with('bouncerProvider');

        test('denies access when user is not authenticated', function (): void {
            // Arrange
            Route::get('/test', fn (): string => 'success')->middleware(HasPermission::to('edit-posts'));

            // Act
            $response = $this->get('/test');

            // Assert
            $response->assertForbidden();
        });
    });

    describe('Edge Cases', function (): void {
        test('generates correct middleware string for single permission', function (): void {
            // Act
            $middleware = HasPermission::to('edit-posts');

            // Assert
            expect($middleware)->toBe('permission:edit-posts');
        });

        test('generates correct middleware string for multiple permissions', function (): void {
            // Act
            $middleware = HasPermission::toAny('edit-posts', 'delete-posts', 'publish-posts');

            // Assert
            expect($middleware)->toBe('permission:edit-posts|delete-posts|publish-posts');
        });
    });
});
