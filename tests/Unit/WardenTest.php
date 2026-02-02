<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Clipboard\CachedClipboard;
use Cline\Warden\Clipboard\Clipboard;
use Cline\Warden\Conductors\AssignsRoles;
use Cline\Warden\Conductors\ChecksRoles;
use Cline\Warden\Conductors\ForbidsAbilities;
use Cline\Warden\Conductors\GivesAbilities;
use Cline\Warden\Conductors\RemovesAbilities;
use Cline\Warden\Conductors\RemovesRoles;
use Cline\Warden\Conductors\SyncsRolesAndAbilities;
use Cline\Warden\Conductors\UnforbidsAbilities;
use Cline\Warden\Contracts\ClipboardInterface;
use Cline\Warden\Contracts\ScopeInterface;
use Cline\Warden\Database\Ability;
use Cline\Warden\Database\Models;
use Cline\Warden\Database\Role;
use Cline\Warden\Database\Scope\Scope;
use Cline\Warden\Factory;
use Cline\Warden\Guard;
use Cline\Warden\Warden;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Access\Gate;
use Illuminate\Auth\Access\Response;
use Illuminate\Cache\ArrayStore;
use Illuminate\Container\Container;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Models\User;

beforeEach(function (): void {
    $this->user = User::query()->create();
    $gate = new Gate(Container::getInstance(), fn () => $this->user);
    $this->guard = new Guard(
        new Clipboard(),
    );
    $this->warden = new Warden($this->guard);
    $this->warden->setGate($gate);

    $this->guard->registerAt($gate);
});
afterEach(function (): void {
    Models::reset();
});
test('creates warden instance with default configuration', function (): void {
    // Arrange - N/A (static factory method)
    // Act
    $warden = Warden::create();

    // Assert
    expect($warden)->toBeInstanceOf(Warden::class);
    expect($warden->getGate())->toBeInstanceOf(Gate::class);
    expect($warden->usesCachedClipboard())->toBeTrue();
})->group('happy-path');
test('creates warden instance with user', function (): void {
    // Arrange
    $user = User::query()->create();

    // Act
    $warden = Warden::create($user);

    // Assert
    expect($warden)->toBeInstanceOf(Warden::class);
    expect($warden->getGate())->toBeInstanceOf(Gate::class);
})->group('happy-path');
test('creates factory instance for custom configuration', function (): void {
    // Arrange - N/A (static factory method)
    // Act
    $factory = Warden::make();

    // Assert
    expect($factory)->toBeInstanceOf(Factory::class);
})->group('happy-path');
test('creates factory with user', function (): void {
    // Arrange
    $user = User::query()->create();

    // Act
    $factory = Warden::make($user);

    // Assert
    expect($factory)->toBeInstanceOf(Factory::class);
})->group('happy-path');
test('returns gives abilities conductor for authority', function (): void {
    // Arrange
    $user = User::query()->create();

    // Act
    $conductor = $this->warden->allow($user);

    // Assert
    expect($conductor)->toBeInstanceOf(GivesAbilities::class);
})->group('happy-path');
test('returns gives abilities conductor for everyone', function (): void {
    // Arrange - N/A (no specific authority)
    // Act
    $conductor = $this->warden->allowEveryone();

    // Assert
    expect($conductor)->toBeInstanceOf(GivesAbilities::class);
})->group('happy-path');
test('returns removes abilities conductor for authority', function (): void {
    // Arrange
    $user = User::query()->create();

    // Act
    $conductor = $this->warden->disallow($user);

    // Assert
    expect($conductor)->toBeInstanceOf(RemovesAbilities::class);
})->group('happy-path');
test('returns removes abilities conductor for everyone', function (): void {
    // Arrange - N/A (no specific authority)
    // Act
    $conductor = $this->warden->disallowEveryone();

    // Assert
    expect($conductor)->toBeInstanceOf(RemovesAbilities::class);
})->group('happy-path');
test('returns forbids abilities conductor for authority', function (): void {
    // Arrange
    $user = User::query()->create();

    // Act
    $conductor = $this->warden->forbid($user);

    // Assert
    expect($conductor)->toBeInstanceOf(ForbidsAbilities::class);
})->group('happy-path');
test('returns forbids abilities conductor for everyone', function (): void {
    // Arrange - N/A (no specific authority)
    // Act
    $conductor = $this->warden->forbidEveryone();

    // Assert
    expect($conductor)->toBeInstanceOf(ForbidsAbilities::class);
})->group('happy-path');
test('returns unforbids abilities conductor for authority', function (): void {
    // Arrange
    $user = User::query()->create();

    // Act
    $conductor = $this->warden->unforbid($user);

    // Assert
    expect($conductor)->toBeInstanceOf(UnforbidsAbilities::class);
})->group('happy-path');
test('returns unforbids abilities conductor for everyone', function (): void {
    // Arrange - N/A (no specific authority)
    // Act
    $conductor = $this->warden->unforbidEveryone();

    // Assert
    expect($conductor)->toBeInstanceOf(UnforbidsAbilities::class);
})->group('happy-path');
test('returns assigns roles conductor', function (): void {
    // Arrange
    $role = Role::query()->create(['name' => 'admin']);

    // Act
    $conductor = $this->warden->assign($role);

    // Assert
    expect($conductor)->toBeInstanceOf(AssignsRoles::class);
})->group('happy-path');
test('returns removes roles conductor', function (): void {
    // Arrange
    $role = Role::query()->create(['name' => 'admin']);

    // Act
    $conductor = $this->warden->retract($role);

    // Assert
    expect($conductor)->toBeInstanceOf(RemovesRoles::class);
})->group('happy-path');
test('returns syncs roles and abilities conductor', function (): void {
    // Arrange
    $user = User::query()->create();

    // Act
    $conductor = $this->warden->sync($user);

    // Assert
    expect($conductor)->toBeInstanceOf(SyncsRolesAndAbilities::class);
})->group('happy-path');
test('returns checks roles conductor', function (): void {
    // Arrange
    $user = User::query()->create();

    // Act
    $conductor = $this->warden->is($user);

    // Assert
    expect($conductor)->toBeInstanceOf(ChecksRoles::class);
})->group('happy-path');
test('gets clipboard instance', function (): void {
    // Arrange - N/A (clipboard set in setUp)
    // Act
    $clipboard = $this->warden->getClipboard();

    // Assert
    expect($clipboard)->toBeInstanceOf(ClipboardInterface::class);
})->group('happy-path');
test('sets clipboard instance', function (): void {
    // Arrange
    $newClipboard = new Clipboard();

    // Act
    $result = $this->warden->setClipboard($newClipboard);

    // Assert
    expect($result)->toBe($this->warden);
    expect($this->warden->getClipboard())->toBe($newClipboard);
})->group('happy-path');
test('registers clipboard in container', function (): void {
    // Arrange
    $clipboard = new Clipboard();
    $this->warden->setClipboard($clipboard);

    // Act
    $result = $this->warden->registerClipboardAtContainer();

    // Assert
    expect($result)->toBe($this->warden);
    expect(Container::getInstance()->make(ClipboardInterface::class))->toBe($clipboard);
})->group('happy-path');
test('enables cached clipboard with default cache store', function (): void {
    // Arrange
    Container::getInstance()->make(CacheRepository::class);

    // Act
    $result = $this->warden->cache();

    // Assert
    expect($result)->toBe($this->warden);
    expect($this->warden->usesCachedClipboard())->toBeTrue();
})->group('happy-path');
test('updates cache store when cached clipboard exists', function (): void {
    // Arrange
    $this->warden->cache();
    $customStore = new ArrayStore();

    // Act
    $result = $this->warden->cache($customStore);

    // Assert
    expect($result)->toBe($this->warden);
    expect($this->warden->usesCachedClipboard())->toBeTrue();

    $clipboard = $this->warden->getClipboard();
    expect($clipboard)->toBeInstanceOf(CachedClipboard::class);

    // Cache may be wrapped in TaggedCache if store supports tags
    $cache = $clipboard->getCache();
    expect($cache)->not->toBeNull();
})->group('happy-path');
test('disables clipboard caching', function (): void {
    // Arrange
    $this->warden->cache();

    // Act
    $result = $this->warden->dontCache();

    // Assert
    expect($result)->toBe($this->warden);
    expect($this->warden->usesCachedClipboard())->toBeFalse();
})->group('happy-path');
test('refreshes cache for all authorities', function (): void {
    // Arrange
    $this->warden->cache();

    // Act
    $result = $this->warden->refresh();

    // Assert
    expect($result)->toBe($this->warden);
})->group('happy-path');
test('refreshes cache for specific authority', function (): void {
    // Arrange
    $this->warden->cache();
    $user = User::query()->create();

    // Act
    $result = $this->warden->refresh($user);

    // Assert
    expect($result)->toBe($this->warden);
})->group('happy-path');
test('refreshes cache using refresh for method', function (): void {
    // Arrange
    $this->warden->cache();
    $user = User::query()->create();

    // Act
    $result = $this->warden->refreshFor($user);

    // Assert
    expect($result)->toBe($this->warden);
})->group('happy-path');
test('does not refresh when not using cached clipboard', function (): void {
    // Arrange
    $this->warden->dontCache();
    $user = User::query()->create();

    // Act
    $result = $this->warden->refresh($user);

    // Assert
    expect($result)->toBe($this->warden);
})->group('edge-case');
test('sets gate instance', function (): void {
    // Arrange
    $gate = new Gate(Container::getInstance(), fn (): User => $this->user);

    // Act
    $result = $this->warden->setGate($gate);

    // Assert
    expect($result)->toBe($this->warden);
    expect($this->warden->getGate())->toBe($gate);
})->group('happy-path');
test('gets gate instance', function (): void {
    // Arrange
    $gate = new Gate(Container::getInstance(), fn (): User => $this->user);
    $this->warden->setGate($gate);

    // Act
    $result = $this->warden->getGate();

    // Assert
    expect($result)->toBe($gate);
})->group('happy-path');
test('returns gate instance with gate method', function (): void {
    // Arrange
    $gate = new Gate(Container::getInstance(), fn (): User => $this->user);
    $this->warden->setGate($gate);

    // Act
    $result = $this->warden->gate();

    // Assert
    expect($result)->toBe($gate);
})->group('happy-path');
test('throws exception when gate not set', function (): void {
    // Arrange
    $warden = new Warden($this->guard);

    // Act & Assert
    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('The gate instance has not been set.');
    $warden->gate();
})->group('sad-path');
test('checks if using cached clipboard', function (): void {
    // Arrange
    $this->warden->cache();

    // Act
    $result = $this->warden->usesCachedClipboard();

    // Assert
    expect($result)->toBeTrue();
})->group('happy-path');
test('defines custom ability at gate', function (): void {
    // Arrange
    $callback = fn (): true => true;

    // Act
    $result = $this->warden->define('custom-ability', $callback);

    // Assert
    expect($result)->toBe($this->warden);
})->group('happy-path');
test('authorizes ability successfully', function (): void {
    // Arrange
    $this->warden->allow($this->user)->to('test-ability');

    // Act
    $result = $this->warden->authorize('test-ability');

    // Assert
    expect($result)->toBeInstanceOf(Response::class);
    expect($result->allowed())->toBeTrue();
})->group('happy-path');
test('throws authorization exception when ability denied', function (): void {
    // Arrange - No ability granted
    // Act & Assert
    $this->expectException(AuthorizationException::class);
    $this->warden->authorize('non-existent-ability');
})->group('sad-path');
test('checks if ability is allowed', function (): void {
    // Arrange
    $this->warden->allow($this->user)->to('test-ability');

    // Act
    $result = $this->warden->can('test-ability');

    // Assert
    expect($result)->toBeTrue();
})->group('happy-path');
test('returns false when ability not allowed', function (): void {
    // Arrange - No ability granted
    // Act
    $result = $this->warden->can('non-existent-ability');

    // Assert
    expect($result)->toBeFalse();
})->group('happy-path');
test('checks if any abilities are allowed', function (): void {
    // Arrange
    $this->warden->allow($this->user)->to('ability-one');

    // Act
    $result = $this->warden->canAny(['ability-one', 'ability-two']);

    // Assert
    expect($result)->toBeTrue();
})->group('happy-path');
test('returns false when no abilities allowed', function (): void {
    // Arrange - No abilities granted
    // Act
    $result = $this->warden->canAny(['ability-one', 'ability-two']);

    // Assert
    expect($result)->toBeFalse();
})->group('happy-path');
test('checks if ability is denied', function (): void {
    // Arrange - No ability granted
    // Act
    $result = $this->warden->cannot('non-existent-ability');

    // Assert
    expect($result)->toBeTrue();
})->group('happy-path');
test('returns false when ability allowed', function (): void {
    // Arrange
    $this->warden->allow($this->user)->to('test-ability');

    // Act
    $result = $this->warden->cannot('test-ability');

    // Assert
    expect($result)->toBeFalse();
})->group('happy-path');
test('checks ability with deprecated allows method', function (): void {
    // Arrange
    $this->warden->allow($this->user)->to('test-ability');

    // Act
    $result = $this->warden->allows('test-ability');

    // Assert
    expect($result)->toBeTrue();
})->group('happy-path');
test('checks ability with deprecated denies method', function (): void {
    // Arrange - No ability granted
    // Act
    $result = $this->warden->denies('non-existent-ability');

    // Assert
    expect($result)->toBeTrue();
})->group('happy-path');
test('creates role instance', function (): void {
    // Arrange
    $attributes = ['name' => 'admin'];

    // Act
    $role = $this->warden->role($attributes);

    // Assert
    expect($role)->toBeInstanceOf(Role::class);
    expect($role->name)->toEqual('admin');
})->group('happy-path');
test('creates ability instance', function (): void {
    // Arrange
    $attributes = ['name' => 'edit-posts'];

    // Act
    $ability = $this->warden->ability($attributes);

    // Assert
    expect($ability)->toBeInstanceOf(Ability::class);
    expect($ability->name)->toEqual('edit-posts');
})->group('happy-path');
test('configures to run before policies', function (): void {
    // Arrange - N/A (default is 'after')
    // Act
    $result = $this->warden->runBeforePolicies(true);

    // Assert
    expect($result)->toBe($this->warden);
    expect($this->guard->slot())->toBe('before');
})->group('happy-path');
test('configures to run after policies', function (): void {
    // Arrange
    $this->warden->runBeforePolicies(true);

    // Act
    $result = $this->warden->runBeforePolicies(false);

    // Assert
    expect($result)->toBe($this->warden);
    expect($this->guard->slot())->toBe('after');
})->group('happy-path');
test('configures ownership via attribute', function (): void {
    // Arrange
    $model = User::class;
    $attribute = 'user_id';

    // Act
    $result = $this->warden->ownedVia($model, $attribute);

    // Assert
    expect($result)->toBe($this->warden);
})->group('happy-path');
test('sets custom ability model', function (): void {
    // Arrange
    $customModel = Ability::class;

    // Act
    $result = $this->warden->useAbilityModel($customModel);

    // Assert
    expect($result)->toBe($this->warden);
})->group('happy-path');
test('throws exception for non existent ability model', function (): void {
    // Arrange
    $invalidModel = 'App\\NonExistentAbilityModel';

    // Act & Assert
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Class App\\NonExistentAbilityModel does not exist');
    $this->warden->useAbilityModel($invalidModel);
})->group('sad-path');
test('sets custom role model', function (): void {
    // Arrange
    $customModel = Role::class;

    // Act
    $result = $this->warden->useRoleModel($customModel);

    // Assert
    expect($result)->toBe($this->warden);
})->group('happy-path');
test('throws exception for non existent role model', function (): void {
    // Arrange
    $invalidModel = 'App\\NonExistentRoleModel';

    // Act & Assert
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Class App\\NonExistentRoleModel does not exist');
    $this->warden->useRoleModel($invalidModel);
})->group('sad-path');
test('sets custom user model', function (): void {
    // Arrange
    $customModel = User::class;

    // Act
    $result = $this->warden->useUserModel($customModel);

    // Assert
    expect($result)->toBe($this->warden);
})->group('happy-path');
test('throws exception for non existent user model', function (): void {
    // Arrange
    $invalidModel = 'App\\NonExistentUserModel';

    // Act & Assert
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Class App\\NonExistentUserModel does not exist');
    $this->warden->useUserModel($invalidModel);
})->group('sad-path');
test('configures custom table names', function (): void {
    // Arrange
    $tableMap = [
        'abilities' => 'custom_abilities',
        'roles' => 'custom_roles',
    ];

    // Act
    $result = $this->warden->tables($tableMap);

    // Assert
    expect($result)->toBe($this->warden);
    expect(Models::table('abilities'))->toBe('custom_abilities');
    expect(Models::table('roles'))->toBe('custom_roles');
})->group('happy-path');
test('gets and sets scope instance', function (): void {
    // Arrange
    $scope = new Scope();

    // Act
    $result = $this->warden->scope($scope);

    // Assert
    expect($result)->toBe($scope);
})->group('happy-path');
test('gets current scope instance', function (): void {
    // Arrange - N/A (default scope)
    // Act
    $result = $this->warden->scope();

    // Assert
    expect($result)->toBeInstanceOf(ScopeInterface::class);
})->group('happy-path');
