<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Clipboard\Clipboard;
use Cline\Warden\Guard;
use Cline\Warden\Http\Middleware\ScopeWarden;
use Cline\Warden\Warden;
use Illuminate\Auth\Access\Gate;
use Illuminate\Container\Container;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tests\Fixtures\Models\User;

/**
 * Helper function to create a properly configured Warden instance for testing.
 */
function createWardenWithScope(): Warden
{
    $user = User::query()->create(['name' => 'Test User']);
    $gate = new Gate(Container::getInstance(), fn () => $user);
    $clipboard = new Clipboard();
    $guard = new Guard($clipboard);
    $guard->registerAt($gate);

    $warden = new Warden($guard);
    $warden->setGate($gate);

    return $warden;
}

describe('ScopeWarden Middleware', function (): void {
    describe('Happy Path Tests', function (): void {
        test('sets scope from authenticated user account_id and calls next middleware', function (): void {
            // Arrange - Create user with account_id
            $user = User::query()->create([
                'name' => 'John Doe',
                'account_id' => 123,
            ]);

            $request = Request::create('/test', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $request->setUserResolver(fn () => $user);

            $warden = createWardenWithScope();

            $middleware = new ScopeWarden($warden);

            $nextCalled = false;
            $next = function ($req) use (&$nextCalled, $request): string {
                $nextCalled = true;
                expect($req)->toBe($request);

                return 'response';
            };

            // Act
            $response = $middleware->handle($request, $next);

            // Assert - Verify scope was set and next was called
            expect($nextCalled)->toBeTrue();
            expect($response)->toBe('response');
            expect($warden->scope()->get())->toBe(123);
        });

        test('sets scope to different account_id for different users', function (): void {
            // Arrange - Create multiple users with different account_ids
            $user1 = User::query()->create([
                'name' => 'Alice',
                'account_id' => 100,
            ]);

            $user2 = User::query()->create([
                'name' => 'Bob',
                'account_id' => 200,
            ]);

            $warden = createWardenWithScope();

            $middleware = new ScopeWarden($warden);

            $request1 = Request::create('/test', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $request1->setUserResolver(fn () => $user1);

            $request2 = Request::create('/test', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $request2->setUserResolver(fn () => $user2);

            $next = fn ($req): string => 'response';

            // Act - Process first user's request
            $middleware->handle($request1, $next);

            // Assert - Scope is set to user1's account_id
            expect($warden->scope()->get())->toBe(100);

            // Act - Process second user's request
            $middleware->handle($request2, $next);

            // Assert - Scope is updated to user2's account_id
            expect($warden->scope()->get())->toBe(200);
        });

        test('middleware returns response from next closure', function (): void {
            // Arrange
            $user = User::query()->create([
                'name' => 'Test User',
                'account_id' => 999,
            ]);

            $request = Request::create('/api/test', Symfony\Component\HttpFoundation\Request::METHOD_POST);
            $request->setUserResolver(fn () => $user);

            $warden = createWardenWithScope();

            $middleware = new ScopeWarden($warden);

            $expectedResponse = new JsonResponse(['success' => true]);
            $next = fn ($req): JsonResponse => $expectedResponse;

            // Act
            $response = $middleware->handle($request, $next);

            // Assert
            expect($response)->toBe($expectedResponse);
        });
    });

    describe('Sad Path Tests', function (): void {
        test('throws error when user is not authenticated', function (): void {
            // Arrange - Create request without authenticated user
            $request = Request::create('/test', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $request->setUserResolver(fn (): null => null);

            $warden = createWardenWithScope();

            $middleware = new ScopeWarden($warden);

            $next = fn ($req): string => 'response';

            // Act & Assert - Expect error when accessing null->account_id
            expect(fn () => $middleware->handle($request, $next))
                ->toThrow(ErrorException::class);
        });

        test('throws error when user object is missing', function (): void {
            // Arrange - Request with no user resolver set
            $request = Request::create('/test', Symfony\Component\HttpFoundation\Request::METHOD_GET);

            $warden = createWardenWithScope();

            $middleware = new ScopeWarden($warden);

            $next = fn ($req): string => 'response';

            // Act & Assert
            expect(fn () => $middleware->handle($request, $next))
                ->toThrow(ErrorException::class);
        });

        test('sets null scope when user account_id is null', function (): void {
            // Arrange - User without account_id
            $user = User::query()->create([
                'name' => 'No Account User',
                'account_id' => null,
            ]);

            $request = Request::create('/test', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $request->setUserResolver(fn () => $user);

            $warden = createWardenWithScope();

            $middleware = new ScopeWarden($warden);

            $next = fn ($req): string => 'response';

            // Act
            $middleware->handle($request, $next);

            // Assert - Scope should be set to null
            expect($warden->scope()->get())->toBeNull();
        });
    });

    describe('Edge Case Tests', function (): void {
        test('handles account_id of zero', function (): void {
            // Arrange - User with account_id = 0
            $user = User::query()->create([
                'name' => 'Zero Account User',
                'account_id' => 0,
            ]);

            $request = Request::create('/test', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $request->setUserResolver(fn () => $user);

            $warden = createWardenWithScope();

            $middleware = new ScopeWarden($warden);

            $next = fn ($req): string => 'response';

            // Act
            $middleware->handle($request, $next);

            // Assert - Should handle 0 as valid scope
            expect($warden->scope()->get())->toBe(0);
        });

        test('handles large account_id values', function (): void {
            // Arrange - User with large account_id
            $largeId = 2_147_483_647; // Max int32 value
            $user = User::query()->create([
                'name' => 'Large ID User',
                'account_id' => $largeId,
            ]);

            $request = Request::create('/test', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $request->setUserResolver(fn () => $user);

            $warden = createWardenWithScope();

            $middleware = new ScopeWarden($warden);

            $next = fn ($req): string => 'response';

            // Act
            $middleware->handle($request, $next);

            // Assert
            expect($warden->scope()->get())->toBe($largeId);
        });

        test('handles negative account_id values', function (): void {
            // Arrange - User with negative account_id (edge case)
            $user = User::query()->create([
                'name' => 'Negative Account User',
                'account_id' => -1,
            ]);

            $request = Request::create('/test', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $request->setUserResolver(fn () => $user);

            $warden = createWardenWithScope();

            $middleware = new ScopeWarden($warden);

            $next = fn ($req): string => 'response';

            // Act
            $middleware->handle($request, $next);

            // Assert
            expect($warden->scope()->get())->toBe(-1);
        });

        test('preserves request data when passing to next middleware', function (): void {
            // Arrange
            $user = User::query()->create([
                'name' => 'Test User',
                'account_id' => 555,
            ]);

            $request = Request::create('/test', Symfony\Component\HttpFoundation\Request::METHOD_POST, ['key' => 'value']);
            $request->setUserResolver(fn () => $user);
            $request->headers->set('X-Custom-Header', 'test-value');

            $warden = createWardenWithScope();

            $middleware = new ScopeWarden($warden);

            $next = function ($req) use ($request): string {
                // Verify request is unchanged
                expect($req)->toBe($request);
                expect($req->input('key'))->toBe('value');
                expect($req->header('X-Custom-Header'))->toBe('test-value');

                return 'response';
            };

            // Act
            $middleware->handle($request, $next);

            // Assert - Handled by assertions in next closure
        });

        test('middleware can be invoked multiple times with same warden instance', function (): void {
            // Arrange - Reusable warden instance
            $warden = createWardenWithScope();

            $middleware = new ScopeWarden($warden);

            $user1 = User::query()->create(['name' => 'User 1', 'account_id' => 111]);
            $user2 = User::query()->create(['name' => 'User 2', 'account_id' => 222]);
            $user3 = User::query()->create(['name' => 'User 3', 'account_id' => 333]);

            $next = fn ($req): string => 'response';

            // Act & Assert - Process multiple requests
            $request1 = Request::create('/test', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $request1->setUserResolver(fn () => $user1);

            $middleware->handle($request1, $next);
            expect($warden->scope()->get())->toBe(111);

            $request2 = Request::create('/test', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $request2->setUserResolver(fn () => $user2);

            $middleware->handle($request2, $next);
            expect($warden->scope()->get())->toBe(222);

            $request3 = Request::create('/test', Symfony\Component\HttpFoundation\Request::METHOD_GET);
            $request3->setUserResolver(fn () => $user3);

            $middleware->handle($request3, $next);
            expect($warden->scope()->get())->toBe(333);
        });
    });
});
