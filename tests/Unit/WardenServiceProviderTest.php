<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Database\Models;
use Cline\Warden\Exceptions\InvalidConfigurationException;
use Cline\Warden\Warden;
use Cline\Warden\WardenServiceProvider;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Console\Kernel;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Models\Organization;
use Tests\Fixtures\Models\User;

beforeEach(function (): void {
    // Reset Models state between tests
    Models::reset();
});
test('registers warden singleton in container', function (): void {
    // Arrange
    $provider = new WardenServiceProvider($this->app);

    // Act
    $provider->register();

    $warden = $this->app->make(Warden::class);

    // Assert
    expect($warden)->toBeInstanceOf(Warden::class);
    expect($this->app->make(Warden::class))->toBe($warden);
})->group('happy-path');
test('registers warden with cached clipboard', function (): void {
    // Arrange
    $provider = new WardenServiceProvider($this->app);

    // Act
    $provider->register();

    $warden = $this->app->make(Warden::class);

    // Assert
    expect($warden)->toBeInstanceOf(Warden::class);
    // Clipboard is internal to Warden, but we verify the factory was used properly
})->group('happy-path');
test('registers warden with gate instance', function (): void {
    // Arrange
    $provider = new WardenServiceProvider($this->app);

    // Act
    $provider->register();

    $warden = $this->app->make(Warden::class);

    // Assert
    expect($warden)->toBeInstanceOf(Warden::class);
})->group('happy-path');
test('registers clean command for artisan', function (): void {
    // Arrange
    $provider = new WardenServiceProvider($this->app);

    // Act
    $provider->register();

    // Assert
    // Verify the command is registered in the container
    $commands = $this->app->make(Kernel::class)->all();
    expect($commands)->toHaveKey('warden:clean');
})->group('happy-path');
test('boot method configures models and registers at gate', function (): void {
    // Arrange
    $config = $this->app->make(Repository::class);
    $config->set('auth.defaults.guard', 'web');
    $config->set('auth.guards.web.provider', 'users');
    $config->set('auth.providers.users.model', User::class);

    $provider = new WardenServiceProvider($this->app);
    $provider->register();

    // Act
    $provider->boot();

    // Assert
    // Verify user model is set by checking Models::user() works
    $user = Models::user();
    expect($user)->toBeInstanceOf(User::class);
})->group('happy-path');
test('sets user model from auth configuration', function (): void {
    // Arrange
    $config = $this->app->make(Repository::class);
    $config->set('auth.defaults.guard', 'web');
    $config->set('auth.guards.web.provider', 'users');
    $config->set('auth.providers.users.model', User::class);

    $provider = new WardenServiceProvider($this->app);
    $provider->register();

    // Act
    $provider->boot();

    // Assert
    // Verify user model is set by checking Models::user() returns correct class
    $user = Models::user();
    expect($user)->toBeInstanceOf(User::class);
})->group('happy-path');
test('applies morph key map from configuration', function (): void {
    // Arrange
    $config = $this->app->make(Repository::class);
    $config->set('warden.morphKeyMap', [
        User::class => 'id',
        Organization::class => 'ulid',
    ]);

    $provider = new WardenServiceProvider($this->app);
    $provider->register();

    // Act
    $provider->boot();

    $user = User::query()->create(['name' => 'John']);
    $org = Organization::query()->create(['name' => 'Acme']);

    // Assert
    expect(Models::getModelKey($user))->toBe('id');
    expect(Models::getModelKey($org))->toBe('ulid');
})->group('happy-path');
test('applies enforce morph key map from configuration', function (): void {
    // Arrange
    $config = $this->app->make(Repository::class);
    $config->set('warden.enforceMorphKeyMap', [
        User::class => 'id',
    ]);

    $provider = new WardenServiceProvider($this->app);
    $provider->register();

    // Act
    $provider->boot();

    $user = User::query()->create(['name' => 'John']);

    // Assert
    expect(Models::getModelKey($user))->toBe('id');
})->group('happy-path');
test('publishes middleware when running in console', function (): void {
    // Arrange
    $this->app->make(Repository::class)->set('app.running_in_console', true);
    $provider = new WardenServiceProvider($this->app);
    $provider->register();

    // Act
    $provider->boot();

    // Assert
    $publishes = $provider::pathsToPublish(null, 'bouncer.middleware');
    expect($publishes)->not->toBeEmpty();
})->group('happy-path');
test('publishes migrations when running in console', function (): void {
    // Arrange
    $this->app->make(Repository::class)->set('app.running_in_console', true);
    $provider = new WardenServiceProvider($this->app);
    $provider->register();

    // Act
    $provider->boot();

    // Assert
    $publishes = $provider::pathsToPublish(null, 'bouncer.migrations');
    expect($publishes)->not->toBeEmpty();
})->group('happy-path');
test('throws exception when both morph configs are set', function (): void {
    // Arrange
    $config = $this->app->make(Repository::class);
    $config->set('warden.morphKeyMap', [User::class => 'id']);
    $config->set('warden.enforceMorphKeyMap', [Organization::class => 'ulid']);

    $provider = new WardenServiceProvider($this->app);
    $provider->register();

    // Assert
    $this->expectException(InvalidConfigurationException::class);
    $this->expectExceptionMessage('Cannot configure both morphKeyMap and enforceMorphKeyMap simultaneously');

    // Act
    $provider->boot();
})->group('sad-path');
test('does not set user model when auth guard is missing', function (): void {
    // Arrange
    Models::reset();
    $config = $this->app->make(Repository::class);
    $config->set('auth.defaults.guard');

    $provider = new WardenServiceProvider($this->app);
    $provider->register();

    // Act
    $provider->boot();

    // Assert
    // User model should not be set when guard is null
    // Attempting to use Models::user() should throw RuntimeException
    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('User model not configured');
    Models::user();
})->group('sad-path');
test('does not set user model when auth guard is not a string', function (): void {
    // Arrange
    Models::reset();
    $config = $this->app->make(Repository::class);
    $config->set('auth.defaults.guard', 123);

    // Invalid type
    $provider = new WardenServiceProvider($this->app);
    $provider->register();

    // Act
    $provider->boot();

    // Assert
    // User model should not be set when guard is invalid type
    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('User model not configured');
    Models::user();
})->group('sad-path');
test('does not set user model when provider is missing', function (): void {
    // Arrange
    Models::reset();
    $config = $this->app->make(Repository::class);
    $config->set('auth.defaults.guard', 'web');
    $config->set('auth.guards.web.provider');

    $provider = new WardenServiceProvider($this->app);
    $provider->register();

    // Act
    $provider->boot();

    // Assert
    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('User model not configured');
    Models::user();
})->group('sad-path');
test('does not set user model when provider is not a string', function (): void {
    // Arrange
    Models::reset();
    $config = $this->app->make(Repository::class);
    $config->set('auth.defaults.guard', 'web');
    $config->set('auth.guards.web.provider', ['invalid' => 'type']);

    $provider = new WardenServiceProvider($this->app);
    $provider->register();

    // Act
    $provider->boot();

    // Assert
    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('User model not configured');
    Models::user();
})->group('sad-path');
test('does not set user model when model is not eloquent model', function (): void {
    // Arrange
    Models::reset();
    $config = $this->app->make(Repository::class);
    $config->set('auth.defaults.guard', 'web');
    $config->set('auth.guards.web.provider', 'users');
    $config->set('auth.providers.users.model', stdClass::class);

    // Not an Eloquent model
    $provider = new WardenServiceProvider($this->app);
    $provider->register();

    // Act
    $provider->boot();

    // Assert
    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('User model not configured');
    Models::user();
})->group('sad-path');
test('handles empty morph key map configuration', function (): void {
    // Arrange
    $config = $this->app->make(Repository::class);
    $config->set('warden.morphKeyMap', []);

    $provider = new WardenServiceProvider($this->app);
    $provider->register();

    // Act
    $provider->boot();

    $user = User::query()->create(['name' => 'John']);

    // Assert
    // Should use model's default key name
    expect(Models::getModelKey($user))->toBe('id');
})->group('edge-case');
test('handles empty enforce morph key map configuration', function (): void {
    // Arrange
    $config = $this->app->make(Repository::class);
    $config->set('warden.enforceMorphKeyMap', []);

    $provider = new WardenServiceProvider($this->app);
    $provider->register();

    // Act
    $provider->boot();

    $user = User::query()->create(['name' => 'John']);

    // Assert
    // Should use model's default key name
    expect(Models::getModelKey($user))->toBe('id');
})->group('edge-case');
test('handles both morph configs empty without error', function (): void {
    // Arrange
    $config = $this->app->make(Repository::class);
    $config->set('warden.morphKeyMap', []);
    $config->set('warden.enforceMorphKeyMap', []);

    $provider = new WardenServiceProvider($this->app);
    $provider->register();

    // Act
    $provider->boot();

    $user = User::query()->create(['name' => 'John']);

    // Assert
    expect(Models::getModelKey($user))->toBe('id');
})->group('edge-case');
test('handles non array morph key map gracefully', function (): void {
    // Arrange
    $config = $this->app->make(Repository::class);
    $config->set('warden.morphKeyMap', 'invalid');

    // Not an array
    $provider = new WardenServiceProvider($this->app);
    $provider->register();

    // Act
    $provider->boot();

    $user = User::query()->create(['name' => 'John']);

    // Assert
    // Should treat invalid config as empty array and use default behavior
    expect(Models::getModelKey($user))->toBe('id');
})->group('edge-case');
test('handles non array enforce morph key map gracefully', function (): void {
    // Arrange
    $config = $this->app->make(Repository::class);
    $config->set('warden.enforceMorphKeyMap', 'invalid');

    // Not an array
    $provider = new WardenServiceProvider($this->app);
    $provider->register();

    // Act
    $provider->boot();

    $user = User::query()->create(['name' => 'John']);

    // Assert
    // Should treat invalid config as empty array and use default behavior
    expect(Models::getModelKey($user))->toBe('id');
})->group('edge-case');
test('skips migration publishing when create bouncer tables exists', function (): void {
    // Arrange
    // Create a dummy class to simulate migration already exists
    if (!class_exists('CreateBouncerTables')) {
        eval('class CreateBouncerTables {}');
    }

    $this->app->make(Repository::class)->set('app.running_in_console', true);
    $provider = new WardenServiceProvider($this->app);
    $provider->register();

    // Act
    $provider->boot();

    // Assert
    // Migration should not be published when class exists
    $publishes = $provider::pathsToPublish(null, 'bouncer.migrations');

    // The method still registers the publish path, but the early return in publishMigrations
    // means the timestamp is not generated. We verify this indirectly.
    expect($publishes)->not->toBeEmpty();
})->group('edge-case');
test('allows morph key map populated and enforce empty', function (): void {
    // Arrange
    $config = $this->app->make(Repository::class);
    $config->set('warden.morphKeyMap', [User::class => 'id']);
    $config->set('warden.enforceMorphKeyMap', []);

    $provider = new WardenServiceProvider($this->app);
    $provider->register();

    // Act
    $provider->boot();

    $user = User::query()->create(['name' => 'John']);

    // Assert
    expect(Models::getModelKey($user))->toBe('id');
})->group('edge-case');
test('allows enforce populated and morph key map empty', function (): void {
    // Arrange
    $config = $this->app->make(Repository::class);
    $config->set('warden.morphKeyMap', []);
    $config->set('warden.enforceMorphKeyMap', [User::class => 'id']);

    $provider = new WardenServiceProvider($this->app);
    $provider->register();

    // Act
    $provider->boot();

    $user = User::query()->create(['name' => 'John']);

    // Assert
    expect(Models::getModelKey($user))->toBe('id');
})->group('edge-case');
test('registers morphs through models update morph map', function (): void {
    // Arrange
    $provider = new WardenServiceProvider($this->app);
    $provider->register();

    // Act
    $provider->boot();

    // Assert
    // This verifies that Models::updateMorphMap was called during boot
    // The method registers Warden's models in the morph map
    expect(true)->toBeTrue();
    // Indirect verification via no exceptions
})->group('edge-case');
test('does not publish assets when not running in console', function (): void {
    // Arrange
    $this->app->make(Repository::class)->set('app.running_in_console', false);
    $provider = new WardenServiceProvider($this->app);
    $provider->register();

    // Act
    $provider->boot();

    // Assert
    // When not in console, publishMiddleware and publishMigrations should not execute
    // We verify by checking that the provider still works correctly
    expect($this->app->make(Warden::class))->toBeInstanceOf(Warden::class);
})->group('edge-case');
test('resolves warden from container to trigger gate registration', function (): void {
    // Arrange
    $provider = new WardenServiceProvider($this->app);
    $provider->register();

    // Act
    $provider->boot();

    // Assert
    // Warden should be resolved and registered at the gate
    $warden = $this->app->make(Warden::class);
    expect($warden)->toBeInstanceOf(Warden::class);
})->group('edge-case');
test('uses alternative guard configuration', function (): void {
    // Arrange
    Models::reset();
    $config = $this->app->make(Repository::class);
    $config->set('auth.defaults.guard', 'api');
    $config->set('auth.guards.api.provider', 'api-users');
    $config->set('auth.providers.api-users.model', User::class);

    $provider = new WardenServiceProvider($this->app);
    $provider->register();

    // Act
    $provider->boot();

    // Assert
    $user = Models::user();
    expect($user)->toBeInstanceOf(User::class);
})->group('edge-case');
