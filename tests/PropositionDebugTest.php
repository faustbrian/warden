<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Ruler\Builder\RuleBuilder;
use Cline\Ruler\Core\Context;
use Cline\Ruler\Core\Proposition;
use Cline\Warden\Database\Ability;
use Cline\Warden\Support\PropositionBuilder;
use Tests\Fixtures\Models\Post;
use Tests\Fixtures\Models\User;

test('ruler can access model properties via ArrayAccess', function (): void {
    $user = User::query()->create(['id' => 1, 'name' => 'Alice']);
    $post = Post::query()->create(['id' => 1, 'user_id' => 2, 'title' => 'Test']);

    // Test that models support array access
    expect($user['id'])->toBe(1);
    expect($post['user_id'])->toBe(2);

    // Test ruler with model objects
    $builder = new RuleBuilder();
    $proposition = $builder['resource']['user_id']->equalTo($builder['authority']['id']);

    $context = new Context([
        'authority' => $user,
        'resource' => $post,
    ]);

    // User id=1, post user_id=2, should be false
    expect($proposition->evaluate($context))->toBeFalse();

    // Now test with matching IDs
    $ownPost = Post::query()->create(['id' => 2, 'user_id' => 1, 'title' => 'Own Post']);
    $context2 = new Context([
        'authority' => $user,
        'resource' => $ownPost,
    ]);

    expect($proposition->evaluate($context2))->toBeTrue();
});

test('ability proposition is saved and loaded correctly', function (): void {
    $builder = new PropositionBuilder();
    $proposition = $builder->resourceOwnedBy();

    // Create ability with proposition
    $ability = Ability::query()->create([
        'name' => 'test-ability',
        'guard_name' => 'web',
        'subject_type' => Post::class,
        'proposition' => $proposition,
    ]);

    expect($ability->proposition)->not->toBeNull();
    expect($ability->proposition)->toBeInstanceOf(Proposition::class);

    // Refresh from database
    $ability->refresh();

    expect($ability->proposition)->not->toBeNull();
    expect($ability->proposition)->toBeInstanceOf(Proposition::class);

    // Test that the reloaded proposition works
    $user = User::query()->create(['id' => 1, 'name' => 'Alice']);
    $post = Post::query()->create(['id' => 1, 'user_id' => 1, 'title' => 'Test']);

    $context = new Context([
        'authority' => $user,
        'resource' => $post,
    ]);

    expect($ability->proposition->evaluate($context))->toBeTrue();
});

test('debug ability lookup during permission check', function (): void {
    $user = User::query()->create(['id' => 1, 'name' => 'Alice']);
    $post = Post::query()->create(['id' => 1, 'user_id' => 2, 'title' => 'Test']);

    $builder = new PropositionBuilder();
    $proposition = $builder->resourceOwnedBy();

    // Create ability with proposition
    $ability = Ability::query()->create([
        'name' => 'edit',
        'guard_name' => 'web',
        'subject_type' => Post::class,
        'proposition' => $proposition,
    ]);

    // Grant ability to user
    $user->allow('edit', Post::class);

    // Check what abilities the user has
    $userAbilities = $user->getAbilities();
    expect($userAbilities)->toHaveCount(1);

    $userAbility = $userAbilities->first();
    expect($userAbility->name)->toBe('edit');
    expect($userAbility->subject_type)->toBe(Post::class);

    // Verify the ability has the proposition
    expect($userAbility->id)->toBe($ability->id);
    expect($userAbility->proposition)->not->toBeNull();
});
