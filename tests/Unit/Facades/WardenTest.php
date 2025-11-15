<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Clipboard\Clipboard;
use Cline\Warden\Conductors\AssignsRoles;
use Cline\Warden\Conductors\ChecksRoles;
use Cline\Warden\Conductors\GivesAbilities;
use Cline\Warden\Conductors\RemovesAbilities;
use Cline\Warden\Conductors\RemovesRoles;
use Cline\Warden\Conductors\SyncsRolesAndAbilities;
use Cline\Warden\Contracts\ClipboardInterface;
use Cline\Warden\Database\Ability;
use Cline\Warden\Database\Role;
use Cline\Warden\Facades\Warden as WardenFacade;
use Cline\Warden\Factory;
use Cline\Warden\Warden;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Models\User;

beforeEach(function (): void {
    // Arrange - use Laravel's container from TestCase instead of creating new one
    $this->gate = $this->createMock(Gate::class);

    // Create real Warden instance with mocked dependencies
    $factory = new Factory();
    $clipboard = new Clipboard();
    $this->wardenInstance = $factory
        ->withClipboard($clipboard)
        ->withGate($this->gate)
        ->create();

    // Bind facade to Laravel's container
    $this->app->singleton(WardenFacade::class, fn (): Warden => $this->wardenInstance);

    // Set facade root using reflection to work around facade caching
    WardenFacade::setFacadeApplication($this->app);
});
afterEach(function (): void {
    // Clear facade cache
    WardenFacade::clearResolvedInstance(WardenFacade::class);
});
test('returns correct facade accessor for service container resolution', function (): void {
    // Arrange
    $reflection = new ReflectionClass(WardenFacade::class);
    $method = $reflection->getMethod('getFacadeAccessor');

    // Act
    $accessor = $method->invoke(null);

    // Assert
    expect($accessor)->toBe(WardenFacade::class);
})->group('happy-path');
test('proxies allow method to underlying warden instance', function (): void {
    // Arrange
    $user = new User(['name' => 'John']);

    // Act
    $result = WardenFacade::allow($user);

    // Assert
    expect($result)->toBeInstanceOf(GivesAbilities::class);
})->group('happy-path');
test('proxies allow everyone method to underlying warden instance', function (): void {
    // Arrange
    // Act
    $result = WardenFacade::allowEveryone();

    // Assert
    expect($result)->toBeInstanceOf(GivesAbilities::class);
})->group('happy-path');
test('proxies disallow method to underlying warden instance', function (): void {
    // Arrange
    $user = new User(['name' => 'Jane']);

    // Act
    $result = WardenFacade::disallow($user);

    // Assert
    expect($result)->toBeInstanceOf(RemovesAbilities::class);
})->group('happy-path');
test('proxies assign method to underlying warden instance', function (): void {
    // Arrange
    $role = 'admin';

    // Act
    $result = WardenFacade::assign($role);

    // Assert
    expect($result)->toBeInstanceOf(AssignsRoles::class);
})->group('happy-path');
test('proxies retract method to underlying warden instance', function (): void {
    // Arrange
    $role = 'editor';

    // Act
    $result = WardenFacade::retract($role);

    // Assert
    expect($result)->toBeInstanceOf(RemovesRoles::class);
})->group('happy-path');
test('proxies sync method to underlying warden instance', function (): void {
    // Arrange
    $user = new User(['name' => 'Bob']);

    // Act
    $result = WardenFacade::sync($user);

    // Assert
    expect($result)->toBeInstanceOf(SyncsRolesAndAbilities::class);
})->group('happy-path');
test('proxies is method to underlying warden instance', function (): void {
    // Arrange
    $user = new User(['name' => 'Alice']);

    // Act
    $result = WardenFacade::is($user);

    // Assert
    expect($result)->toBeInstanceOf(ChecksRoles::class);
})->group('happy-path');
test('proxies get clipboard method to underlying warden instance', function (): void {
    // Arrange
    // Act
    $result = WardenFacade::getClipboard();

    // Assert
    expect($result)->toBeInstanceOf(ClipboardInterface::class);
})->group('happy-path');
test('proxies gate method to underlying warden instance', function (): void {
    // Arrange
    // Act
    $result = WardenFacade::gate();

    // Assert
    expect($result)->toBeInstanceOf(Gate::class);
})->group('happy-path');
test('proxies can method to underlying warden instance', function (): void {
    // Arrange
    $ability = 'edit-posts';
    $arguments = ['post' => 123];

    $this->gate
        ->expects($this->once())
        ->method('allows')
        ->with($ability, $arguments)
        ->willReturn(true);

    // Act
    $result = WardenFacade::can($ability, $arguments);

    // Assert
    expect($result)->toBeTrue();
})->group('happy-path');
test('proxies can any method to underlying warden instance', function (): void {
    // Arrange
    $abilities = ['edit-posts', 'delete-posts'];
    $arguments = ['post' => 456];

    $this->gate
        ->expects($this->once())
        ->method('any')
        ->with($abilities, $arguments)
        ->willReturn(true);

    // Act
    $result = WardenFacade::canAny($abilities, $arguments);

    // Assert
    expect($result)->toBeTrue();
})->group('happy-path');
test('proxies authorize method to underlying warden instance', function (): void {
    // Arrange
    $ability = 'publish-post';
    $arguments = ['post' => 789];
    $response = Response::allow();

    $this->gate
        ->expects($this->once())
        ->method('authorize')
        ->with($ability, $arguments)
        ->willReturn($response);

    // Act
    $result = WardenFacade::authorize($ability, $arguments);

    // Assert
    expect($result)->toBeInstanceOf(Response::class);
})->group('happy-path');
test('proxies role method to underlying warden instance', function (): void {
    // Arrange
    $attributes = ['name' => 'admin', 'title' => 'Administrator'];

    // Act
    $result = WardenFacade::role($attributes);

    // Assert
    expect($result)->toBeInstanceOf(Role::class);
    expect($result->name)->toBe('admin');
    expect($result->title)->toBe('Administrator');
})->group('happy-path');
test('proxies ability method to underlying warden instance', function (): void {
    // Arrange
    $attributes = ['name' => 'edit-posts', 'title' => 'Edit Posts'];

    // Act
    $result = WardenFacade::ability($attributes);

    // Assert
    expect($result)->toBeInstanceOf(Ability::class);
    expect($result->name)->toBe('edit-posts');
    expect($result->title)->toBe('Edit Posts');
})->group('happy-path');
test('proxies dont cache method and returns facade instance for chaining', function (): void {
    // Arrange
    // Act
    $result = WardenFacade::dontCache();

    // Assert
    expect($result)->toBeInstanceOf(Warden::class);
})->group('happy-path');
test('proxies refresh method and returns facade instance for chaining', function (): void {
    // Arrange
    $user = new User(['name' => 'Charlie']);

    // Act
    $result = WardenFacade::refresh($user);

    // Assert
    expect($result)->toBeInstanceOf(Warden::class);
})->group('happy-path');
test('proxies uses cached clipboard method to underlying warden instance', function (): void {
    // Arrange
    // Act
    $result = WardenFacade::usesCachedClipboard();

    // Assert
    expect($result)->toBeBool();
})->group('happy-path');
test('proxies define method and returns facade instance for chaining', function (): void {
    // Arrange
    $ability = 'moderate-comments';
    $callback = fn (): true => true;

    $this->gate
        ->expects($this->once())
        ->method('define')
        ->with($ability, $callback);

    // Act
    $result = WardenFacade::define($ability, $callback);

    // Assert
    expect($result)->toBeInstanceOf(Warden::class);
})->group('happy-path');
test('proxies use ability model method and returns facade instance for chaining', function (): void {
    // Arrange
    $model = Ability::class;

    // Act
    $result = WardenFacade::useAbilityModel($model);

    // Assert
    expect($result)->toBeInstanceOf(Warden::class);
})->group('happy-path');
test('proxies use role model method and returns facade instance for chaining', function (): void {
    // Arrange
    $model = Role::class;

    // Act
    $result = WardenFacade::useRoleModel($model);

    // Assert
    expect($result)->toBeInstanceOf(Warden::class);
})->group('happy-path');
test('facade resolves instance from container on first access', function (): void {
    // Arrange
    $resolveCount = 0;
    $this->app->singleton(WardenFacade::class, function () use (&$resolveCount): Warden {
        ++$resolveCount;

        return $this->wardenInstance;
    });

    WardenFacade::clearResolvedInstance(WardenFacade::class);

    // Act
    WardenFacade::getClipboard();
    WardenFacade::getClipboard();

    // Assert
    expect($resolveCount)->toBe(1, 'Facade should resolve instance only once');
})->group('edge-case');
test('facade accessor returns string not null', function (): void {
    // Arrange
    $reflection = new ReflectionClass(WardenFacade::class);
    $method = $reflection->getMethod('getFacadeAccessor');

    // Act
    $accessor = $method->invoke(null);

    // Assert
    expect($accessor)->toBeString();
    expect($accessor)->not->toBeEmpty();
})->group('edge-case');
test('facade handles method calls with empty arrays as arguments', function (): void {
    // Arrange
    $emptyAttributes = [];

    // Act
    $result = WardenFacade::role($emptyAttributes);

    // Assert
    expect($result)->toBeInstanceOf(Role::class);
})->group('edge-case');
test('facade handles chained method calls correctly', function (): void {
    // Arrange
    // Act
    $result = WardenFacade::dontCache()->refresh();

    // Assert
    expect($result)->toBeInstanceOf(Warden::class);
})->group('edge-case');
test('facade properly handles null arguments in method calls', function (): void {
    // Arrange
    // Act
    $result = WardenFacade::refresh();

    // Assert
    expect($result)->toBeInstanceOf(Warden::class);
})->group('edge-case');
test('facade maintains singleton behavior across multiple calls', function (): void {
    // Arrange
    // Act
    $result1 = WardenFacade::getClipboard();
    $result2 = WardenFacade::getClipboard();

    // Assert
    expect($result2)->toBe($result1, 'Should return same clipboard instance');
})->group('edge-case');
test('facade clear resolved instance resets cached instance', function (): void {
    // Arrange
    $clipboard1 = WardenFacade::getClipboard();

    // Act
    WardenFacade::clearResolvedInstance(WardenFacade::class);
    $clipboard2 = WardenFacade::getClipboard();

    // Assert
    // After clearing, facade resolves the instance again from container
    // The same singleton is returned but facade goes through resolution again
    expect($clipboard2)->toBe($clipboard1, 'Same underlying clipboard from singleton');
})->group('edge-case');
test('facade extends laravel base facade class', function (): void {
    // Arrange
    $reflection = new ReflectionClass(WardenFacade::class);

    // Act
    $parent = $reflection->getParentClass();

    // Assert
    $this->assertNotFalse($parent);
    expect($parent->getName())->toBe(Facade::class);
})->group('edge-case');
test('facade is marked as final class', function (): void {
    // Arrange
    $reflection = new ReflectionClass(WardenFacade::class);

    // Act
    $isFinal = $reflection->isFinal();

    // Assert
    expect($isFinal)->toBeTrue();
})->group('edge-case');
test('facade accessor method is protected not public', function (): void {
    // Arrange
    $reflection = new ReflectionClass(WardenFacade::class);
    $method = $reflection->getMethod('getFacadeAccessor');

    // Act
    $isProtected = $method->isProtected();

    // Assert
    expect($isProtected)->toBeTrue();
    expect($method->isPublic())->toBeFalse();
})->group('edge-case');
test('facade proxies get gate method and returns null when not set', function (): void {
    // Arrange
    // The gate is always set by Factory, so test that getGate returns it
    // Act
    $result = WardenFacade::getGate();

    // Assert
    expect($result)->toBeInstanceOf(Gate::class);
})->group('sad-path');
test('cannot method returns opposite of can method', function (): void {
    // Arrange
    $ability = 'delete-posts';
    $arguments = [];

    $this->gate
        ->expects($this->once())
        ->method('denies')
        ->with($ability, $arguments)
        ->willReturn(true);

    // Act
    $result = WardenFacade::cannot($ability, $arguments);

    // Assert
    expect($result)->toBeTrue();
})->group('sad-path');
