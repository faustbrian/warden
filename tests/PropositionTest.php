<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Ruler\Core\Context;
use Cline\Ruler\Core\Proposition;
use Cline\Warden\Database\Ability;
use Cline\Warden\Support\PropositionBuilder;
use Tests\Fixtures\Models\Post;
use Tests\Fixtures\Models\User;

describe('Proposition-based Conditional Permissions', function (): void {
    describe('Happy Paths', function (): void {
        test('grants access when proposition evaluates to true', function (): void {
            $user = User::query()->create(['name' => 'Alice']);
            $post = Post::query()->create(['user_id' => $user->id, 'title' => 'Test Post']);

            // Create ability with proposition config
            $ability = Ability::query()->create([
                'name' => 'edit',
                'guard_name' => 'web',
                'subject_type' => Post::class,
                'proposition' => [
                    'method' => 'resourceOwnedBy',
                    'params' => [],
                ],
            ]);

            // Grant ability to user
            $user->allow('edit', Post::class);

            // User owns the post, should have access
            expect($user->can('edit', $post))->toBeTrue();
        });

        test('denies access when proposition evaluates to false', function (): void {
            $user = User::query()->create(['name' => 'Alice']);
            $otherUser = User::query()->create(['name' => 'Bob']);
            $post = Post::query()->create(['user_id' => $otherUser->id, 'title' => "Bob's Post"]);

            // Create proposition: user owns the resource
            $proposition = [
                'method' => 'resourceOwnedBy',
                'params' => [],
            ];

            // Create ability with proposition
            $ability = Ability::query()->create([
                'name' => 'edit',
                'guard_name' => 'web',
                'subject_type' => Post::class,
                'proposition' => $proposition,
            ]);

            // Grant ability to user
            $user->allow('edit', Post::class);

            // User doesn't own the post, should be denied
            expect($user->can('edit', $post))->toBeFalse();
        });

        test('works without proposition for backward compatibility', function (): void {
            $user = User::query()->create(['name' => 'Alice']);
            $otherUser = User::query()->create(['name' => 'Bob']);
            $post = Post::query()->create(['user_id' => $otherUser->id, 'title' => 'Test Post']);

            // Create ability WITHOUT proposition
            $ability = Ability::query()->create([
                'name' => 'view',
                'guard_name' => 'web',
                'subject_type' => Post::class,
            ]);

            // Grant ability to user
            $user->allow('view', Post::class);

            // No proposition, so only assignment matters
            expect($user->can('view', $post))->toBeTrue();
        });

        test('PropositionBuilder resourceOwnedBy helper works', function (): void {
            $user = User::query()->create(['name' => 'Alice']);
            $otherUser = User::query()->create(['name' => 'Bob']);
            $ownPost = Post::query()->create(['user_id' => $user->id, 'title' => 'My Post']);
            $otherPost = Post::query()->create(['user_id' => $otherUser->id, 'title' => 'Other Post']);

            $proposition = [
                'method' => 'resourceOwnedBy',
                'params' => [],
            ];

            $ability = Ability::query()->create([
                'name' => 'delete',
                'guard_name' => 'web',
                'subject_type' => Post::class,
                'proposition' => $proposition,
            ]);

            $user->allow('delete', Post::class);

            expect($user->can('delete', $ownPost))->toBeTrue();
            expect($user->can('delete', $otherPost))->toBeFalse();
        });

        test('PropositionBuilder withinLimit helper works', function (): void {
            $builder = new PropositionBuilder();

            // Create proposition: value must be within limit
            $proposition = $builder->withinLimit('amount', 'limit');

            // Test with different contexts
            $context1 = new Context(['amount' => 100, 'limit' => 500]);
            expect($proposition->evaluate($context1))->toBeTrue();

            $context2 = new Context(['amount' => 600, 'limit' => 500]);
            expect($proposition->evaluate($context2))->toBeFalse();
        });

        test('PropositionBuilder matches helper works', function (): void {
            $builder = new PropositionBuilder();

            // Create proposition: department must match
            $proposition = $builder->matches('user_dept', 'resource_dept');

            // Test with matching departments
            $context1 = new Context([
                'user_dept' => 'engineering',
                'resource_dept' => 'engineering',
            ]);
            expect($proposition->evaluate($context1))->toBeTrue();

            // Test with non-matching departments
            $context2 = new Context([
                'user_dept' => 'engineering',
                'resource_dept' => 'marketing',
            ]);
            expect($proposition->evaluate($context2))->toBeFalse();
        });

        test('PropositionBuilder allOf helper combines conditions with AND', function (): void {
            $builder = new PropositionBuilder();

            $cond1 = $builder['age']->greaterThan(18);
            $cond2 = $builder['verified']->equalTo(true);
            $proposition = $builder->allOf($cond1, $cond2);

            // Both conditions true
            $context1 = new Context(['age' => 25, 'verified' => true]);
            expect($proposition->evaluate($context1))->toBeTrue();

            // One condition false
            $context2 = new Context(['age' => 25, 'verified' => false]);
            expect($proposition->evaluate($context2))->toBeFalse();

            // Both conditions false
            $context3 = new Context(['age' => 16, 'verified' => false]);
            expect($proposition->evaluate($context3))->toBeFalse();
        });

        test('PropositionBuilder anyOf helper combines conditions with OR', function (): void {
            $builder = new PropositionBuilder();

            $cond1 = $builder['is_admin']->equalTo(true);
            $cond2 = $builder['is_owner']->equalTo(true);
            $proposition = $builder->anyOf($cond1, $cond2);

            // First condition true
            $context1 = new Context(['is_admin' => true, 'is_owner' => false]);
            expect($proposition->evaluate($context1))->toBeTrue();

            // Second condition true
            $context2 = new Context(['is_admin' => false, 'is_owner' => true]);
            expect($proposition->evaluate($context2))->toBeTrue();

            // Both conditions true
            $context3 = new Context(['is_admin' => true, 'is_owner' => true]);
            expect($proposition->evaluate($context3))->toBeTrue();

            // Both conditions false
            $context4 = new Context(['is_admin' => false, 'is_owner' => false]);
            expect($proposition->evaluate($context4))->toBeFalse();
        });

        test('simple proposition works end-to-end', function (): void {
            $user = User::query()->create(['name' => 'Alice']);
            $otherUser = User::query()->create(['name' => 'Bob']);
            $post = Post::query()->create(['user_id' => $user->id, 'title' => 'My Post']);

            // Simple proposition: user owns resource
            $proposition = [
                'method' => 'resourceOwnedBy',
                'params' => [],
            ];

            $ability = Ability::query()->create([
                'name' => 'view',
                'guard_name' => 'web',
                'subject_type' => Post::class,
                'proposition' => $proposition,
            ]);

            $user->allow('view', Post::class);

            // User owns the post
            expect($user->can('view', $post))->toBeTrue();

            // Change ownership
            $post->update(['user_id' => $otherUser->id]);
            expect($user->can('view', $post))->toBeFalse();
        });

        test('proposition serialization and deserialization', function (): void {
            $proposition = [
                'method' => 'resourceOwnedBy',
                'params' => [],
            ];

            // Create ability with proposition
            $ability = Ability::query()->create([
                'name' => 'test',
                'guard_name' => 'web',
                'proposition' => $proposition,
            ]);

            // Refresh from database to test serialization round-trip
            $ability->refresh();

            expect($ability->proposition)->toBeInstanceOf(Proposition::class);

            // Test that deserialized proposition still works
            $context = new Context([
                'authority' => (object) ['id' => 1],
                'resource' => (object) ['user_id' => 1],
            ]);

            expect($ability->proposition->evaluate($context))->toBeTrue();
        });
    });

    describe('Sad Paths', function (): void {
        test('null proposition behaves like traditional permission', function (): void {
            $user = User::query()->create(['name' => 'Alice']);

            $ability = Ability::query()->create([
                'name' => 'test',
                'guard_name' => 'web',
                'proposition' => null,
            ]);

            $user->allow('test');

            expect($user->can('test'))->toBeTrue();
        });

        test('proposition denial takes precedence over assignment', function (): void {
            $user = User::query()->create(['name' => 'Alice']);
            $otherUser = User::query()->create(['name' => 'Bob']);
            $post = Post::query()->create(['user_id' => $otherUser->id, 'title' => 'Test']);

            $proposition = [
                'method' => 'resourceOwnedBy',
                'params' => [],
            ];

            $ability = Ability::query()->create([
                'name' => 'edit',
                'guard_name' => 'web',
                'subject_type' => Post::class,
                'proposition' => $proposition,
            ]);

            // User has the ability assigned
            $user->allow('edit', Post::class);

            // But proposition evaluates to false (doesn't own resource)
            expect($user->can('edit', $post))->toBeFalse();
        });
    });

    describe('Edge Cases', function (): void {
        test('proposition with null resource', function (): void {
            $user = User::query()->create(['name' => 'Alice']);

            // This proposition expects a resource
            $proposition = [
                'method' => 'resourceOwnedBy',
                'params' => [],
            ];

            $ability = Ability::query()->create([
                'name' => 'test',
                'guard_name' => 'web',
                'proposition' => $proposition,
            ]);

            $user->allow('test');

            // Calling can() without a resource when proposition expects one
            // Should handle gracefully (likely false due to missing resource)
            expect($user->can('test'))->toBeFalse();
        });

        test('PropositionBuilder allOf throws exception with no arguments', function (): void {
            $builder = new PropositionBuilder();

            expect(fn (): Proposition => $builder->allOf())->toThrow(InvalidArgumentException::class);
        });

        test('PropositionBuilder anyOf throws exception with no arguments', function (): void {
            $builder = new PropositionBuilder();

            expect(fn (): Proposition => $builder->anyOf())->toThrow(InvalidArgumentException::class);
        });
    });
});
