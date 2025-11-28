<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Clipboard\CachedClipboard;
use Cline\Warden\Contracts\ClipboardInterface;
use Cline\Warden\Database\Models;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\FileStore;
use Illuminate\Cache\TaggedCache;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Tests\Fixtures\Models\Account;
use Tests\Fixtures\Models\User;

describe('CachedClipboard', function (): void {
    beforeEach(function (): void {
        // Override with CachedClipboard for these tests
        Container::getInstance()->instance(
            ClipboardInterface::class,
            makeClipboard(),
        );
    });

    describe('Happy Paths', function (): void {
        test('caches user abilities preventing duplicate database queries', function (): void {
            // Arrange
            $warden = $this->bouncer($user = User::query()->create());
            $warden->allow($user)->to('ban-users');

            // Assert - Initial cache
            expect(getAbilities($this, $user))->toEqual(['ban-users']);

            // Act - Add new ability
            $warden->allow($user)->to('create-users');

            // Assert - Still returns cached value
            expect(getAbilities($this, $user))->toEqual(['ban-users']);
        });

        test('caches empty abilities collection for users without permissions', function (): void {
            // Arrange
            $this->bouncer($user = User::query()->create());

            // Act & Assert
            expect($this->clipboard()->getAbilities($user))->toBeInstanceOf(Collection::class);
            expect($this->clipboard()->getAbilities($user))->toBeInstanceOf(Collection::class);
        });

        test('caches user roles preventing database lookups', function (): void {
            // Arrange
            $warden = $this->bouncer($user = User::query()->create());
            $warden->assign('editor')->to($user);

            // Assert - Initial role cached
            expect($warden->is($user)->an('editor'))->toBeTrue();

            // Act - Assign new role
            $warden->assign('moderator')->to($user);

            // Assert - Still uses cached roles
            expect($warden->is($user)->a('moderator'))->toBeFalse();
        });

        test('always checks roles in cache without database queries', function (): void {
            // Arrange
            $warden = $this->bouncer($user = User::query()->create());
            $admin = $warden->role()->create(['name' => 'admin']);
            $warden->assign($admin)->to($user);

            // Assert initial check
            expect($warden->is($user)->an('admin'))->toBeTrue();

            // Act - Enable query logging
            $this->db()->connection()->enableQueryLog();

            // Assert - Multiple checks with no queries
            expect($warden->is($user)->an($admin->name))->toBeTrue();
            expect($warden->is($user)->an('admin'))->toBeTrue();
            expect($warden->is($user)->an($admin->name))->toBeTrue();
            expect($this->db()->connection()->getQueryLog())->toBeEmpty();

            // Cleanup
            $this->db()->connection()->disableQueryLog();
        });

        test('refreshes cache globally updating all cached abilities', function (): void {
            // Arrange
            new ArrayStore();
            $warden = $this->bouncer($user = User::query()->create());
            $warden->allow($user)->to('create-posts');
            $warden->assign('editor')->to($user);
            $warden->allow('editor')->to('delete-posts');

            // Assert - Initial cached state
            expect(getAbilities($this, $user))->toEqual(['create-posts', 'delete-posts']);

            // Act - Change permissions
            $warden->disallow('editor')->to('delete-posts');
            $warden->allow('editor')->to('edit-posts');

            // Assert - Still cached
            expect(getAbilities($this, $user))->toEqual(['create-posts', 'delete-posts']);

            // Act - Refresh cache
            $warden->refresh();

            // Assert
            expect(getAbilities($this, $user))->toEqual(['create-posts', 'edit-posts']);
        });

        test('refreshes cache for specific user only', function (): void {
            // Arrange
            $user1 = User::query()->create();
            $user2 = User::query()->create();
            $warden = $this->bouncer($user = User::query()->create());
            $warden->allow('admin')->to('ban-users');
            $warden->assign('admin')->to($user1);
            $warden->assign('admin')->to($user2);

            // Assert - Both users cached
            expect(getAbilities($this, $user1))->toEqual(['ban-users']);
            expect(getAbilities($this, $user2))->toEqual(['ban-users']);

            // Act - Change permissions and refresh one user
            $warden->disallow('admin')->to('ban-users');
            $warden->refreshFor($user1);

            // Assert
            expect(getAbilities($this, $user1))->toEqual([]);
            expect(getAbilities($this, $user2))->toEqual(['ban-users']);
        });

        test('returns cache instance from clipboard', function (): void {
            // Arrange
            $cache = new ArrayStore();
            $clipboard = new CachedClipboard($cache);

            // Act
            $result = $clipboard->getCache();

            // Assert
            expect($result)->toBeInstanceOf(TaggedCache::class);
        });

        test('compiles model ability identifiers for wildcard permissions', function (): void {
            // Arrange
            $cache = new ArrayStore();
            new CachedClipboard($cache);
            $user = User::query()->create();
            $warden = $this->bouncer($user);

            // Act
            $warden->allow($user)->to('delete', '*');
            $result = $warden->can('delete', '*');

            // Assert
            expect($result)->toBeTrue();
        });

        test('compiles model ability identifiers for class string', function (): void {
            // Arrange
            $cache = new ArrayStore();
            new CachedClipboard($cache);
            $user = User::query()->create();
            $warden = $this->bouncer($user);

            // Act
            $warden->allow($user)->to('view', User::class);
            $result = $warden->can('view', User::class);

            // Assert
            expect($result)->toBeTrue();
        });

        test('compiles model ability identifiers for model instance', function (): void {
            // Arrange
            $cache = new ArrayStore();
            new CachedClipboard($cache);
            $user = User::query()->create();
            $targetUser = User::query()->create(['name' => 'Target']);
            $warden = $this->bouncer($user);

            // Act
            $warden->allow($user)->to('edit', $targetUser);
            $result = $warden->can('edit', $targetUser);

            // Assert
            expect($result)->toBeTrue();
        });

        test('refreshes all users iteratively when tagged cache is not supported', function (): void {
            // Arrange - FileStore doesn't support tags, so refresh() will use refreshAllIteratively()
            $tempDir = sys_get_temp_dir().'/warden-cache-test-'.uniqid();
            mkdir($tempDir, 0o777, true);
            $cache = new FileStore(
                new Filesystem(),
                $tempDir,
            );
            $clipboard = new CachedClipboard($cache);

            $user1 = User::query()->create();
            $user2 = User::query()->create();
            $warden = $this->bouncer($user1);
            $warden->setClipboard($clipboard);

            // Create roles to ensure both users and roles are iterated
            $warden->role()->create(['name' => 'editor']);
            $warden->role()->create(['name' => 'admin']);

            $warden->allow($user1)->to('create-posts');
            $warden->allow($user2)->to('delete-posts');
            $warden->assign('editor')->to($user1);

            // Populate cache
            expect(getAbilities($this, $user1))->toEqual(['create-posts']);
            expect(getAbilities($this, $user2))->toEqual(['delete-posts']);

            // Act - Change permissions
            $warden->disallow($user1)->to('create-posts');
            $warden->allow($user1)->to('edit-posts');

            // Act - Refresh (calls refreshAllIteratively)
            $warden->refresh();

            // Assert
            expect(getAbilities($this, $user1))->toEqual(['edit-posts']);

            // Cleanup
            exec('rm -rf '.$tempDir);
        });

        test('finds matching ability with ownership constraints', function (): void {
            // Arrange
            $owner = User::query()->create(['name' => 'Owner']);
            $warden = $this->bouncer($owner);

            $post = new Account(['name' => 'Post']);
            $post->actor_id = $owner->id;
            $post->actor_type = $owner->getMorphClass();
            $post->save();

            // Act
            $warden->allow($owner)->toOwn(Account::class);
            $result = $warden->can('edit', $post);

            // Assert
            expect($result)->toBeTrue();
        });

        test('returns false when forbidden ability matches allowed ability', function (): void {
            // Arrange
            $user = User::query()->create();
            $warden = $this->bouncer($user);

            // Act
            $warden->allow($user)->to('edit-posts');
            $warden->forbid($user)->to('edit-posts');

            // Assert
            expect($warden->can('edit-posts'))->toBeFalse();
        });
    });

    describe('Sad Paths', function (): void {
        test('throws exception for invalid role type when checking roles', function (): void {
            // Arrange
            $user = User::query()->create();
            $clipboard = $this->clipboard();

            // Act & Assert
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('Invalid model identifier');
            $clipboard->checkRole($user, [new stdClass()], 'or');
        });
    });

    describe('Regression Tests - Keymap Support', function (): void {
        test('generates cache keys using keymap values not primary keys', function (): void {
            // Arrange - Configure keymap to use 'id' column
            Models::enforceMorphKeyMap([
                User::class => 'id',
            ]);

            $user1 = User::query()->create(['name' => 'Alice']);
            $user2 = User::query()->create(['name' => 'Bob']);

            $warden = $this->bouncer($user1);
            $warden->allow($user1)->to('edit-posts');
            $warden->allow($user2)->to('delete-posts');

            // Act - Cache abilities for each user
            $user1Abilities = getAbilities($this, $user1);
            $user2Abilities = getAbilities($this, $user2);

            // Assert - Each user gets their own abilities (no cache key collision)
            expect($user1Abilities)->toEqual(['edit-posts']);
            expect($user2Abilities)->toEqual(['delete-posts']);
        });

        test('prevents ability cache leakage between users with custom keymap', function (): void {
            // Arrange - This tests the critical bug where cache keys used primary keys
            Models::enforceMorphKeyMap([
                User::class => 'id',
            ]);

            $alice = User::query()->create(['name' => 'Alice']);
            $bob = User::query()->create(['name' => 'Bob']);
            $charlie = User::query()->create(['name' => 'Charlie']);

            $warden = $this->bouncer($alice);
            $warden->allow($alice)->to('view-secrets');
            $warden->allow($bob)->to('view-public');
            $warden->allow($charlie)->to('view-basic');

            // Act - Get abilities for all users
            $aliceAbilities = getAbilities($this, $alice);
            $bobAbilities = getAbilities($this, $bob);
            $charlieAbilities = getAbilities($this, $charlie);

            // Assert - Without the fix, cache keys would collide using primary keys
            expect($aliceAbilities)->toEqual(['view-secrets']);
            expect($bobAbilities)->toEqual(['view-public']);
            expect($charlieAbilities)->toEqual(['view-basic']);
        });

        test('cache refresh uses keymap values for cache key generation', function (): void {
            // Arrange - Configure keymap
            Models::enforceMorphKeyMap([
                User::class => 'id',
            ]);

            $user1 = User::query()->create(['name' => 'User 1']);
            $user2 = User::query()->create(['name' => 'User 2']);

            $warden = $this->bouncer($user1);
            $warden->allow($user1)->to('create-posts');
            $warden->allow($user2)->to('delete-posts');

            // Populate cache
            expect(getAbilities($this, $user1))->toEqual(['create-posts']);
            expect(getAbilities($this, $user2))->toEqual(['delete-posts']);

            // Act - Change permissions and refresh
            $warden->disallow($user1)->to('create-posts');
            $warden->allow($user1)->to('edit-posts');
            $warden->refreshFor($user1);

            // Assert - User 1's cache updated with correct keymap-based key
            expect(getAbilities($this, $user1))->toEqual(['edit-posts']);
            // User 2's cache unchanged
            expect(getAbilities($this, $user2))->toEqual(['delete-posts']);
        });
    });
});

/**
 * Make a new clipboard with the container.
 */
function makeClipboard(): ClipboardInterface
{
    return new CachedClipboard(
        new ArrayStore(),
    );
}

/**
 * Get the name of all of the user's abilities.
 *
 * @param  mixed $test
 * @return array
 */
function getAbilities($test, Model $user)
{
    return $test->clipboard()->getAbilities($user)->pluck('name')->sort()->values()->all();
}
