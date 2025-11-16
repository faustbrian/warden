<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Database\Models;
use Cline\Warden\Exceptions\InvalidConfigurationException;
use Cline\Warden\Exceptions\MorphKeyViolationException;
use Cline\Warden\WardenServiceProvider;
use Illuminate\Contracts\Config\Repository;
use Tests\Fixtures\Models\Organization;
use Tests\Fixtures\Models\User;

describe('MorphKey Configuration', function (): void {
    beforeEach(function (): void {
        Models::reset();
    });

    describe('Happy Paths', function (): void {
        test('applies morph key map from configuration for different models', function (): void {
            // Arrange
            $config = $this->app->make(Repository::class);
            $config->set('warden.morphKeyMap', [
                User::class => 'id',
                Organization::class => 'ulid',
            ]);

            // Act
            $provider = new WardenServiceProvider($this->app);
            $provider->register();
            $provider->boot();

            $user = User::query()->create(['name' => 'John']);
            $org = Organization::query()->create(['name' => 'Acme']);

            // Assert
            expect(Models::getModelKey($user))->toBe('id');
            expect(Models::getModelKey($org))->toBe('ulid');
        });

        test('uses model default key name when both configs are empty', function (): void {
            // Arrange
            $config = $this->app->make(Repository::class);
            $config->set('warden.morphKeyMap', []);
            $config->set('warden.enforceMorphKeyMap', []);

            // Act
            $provider = new WardenServiceProvider($this->app);
            $provider->register();
            $provider->boot();

            $user = User::query()->create(['name' => 'John']);

            // Assert
            expect(Models::getModelKey($user))->toBe('id');
        });

        test('allows one config empty while other is populated', function (): void {
            // Arrange
            $config = $this->app->make(Repository::class);
            $config->set('warden.morphKeyMap', [
                User::class => 'id',
            ]);
            $config->set('warden.enforceMorphKeyMap', []);

            // Act
            $provider = new WardenServiceProvider($this->app);
            $provider->register();
            $provider->boot();

            $user = User::query()->create(['name' => 'John']);

            // Assert
            expect(Models::getModelKey($user))->toBe('id');
        });
    });

    describe('Sad Paths', function (): void {
        test('enforces morph key map throwing exception for unmapped models', function (): void {
            // Arrange
            $config = $this->app->make(Repository::class);
            $config->set('warden.enforceMorphKeyMap', [
                User::class => 'id',
            ]);

            // Act
            $provider = new WardenServiceProvider($this->app);
            $provider->register();
            $provider->boot();

            $org = Organization::query()->create(['name' => 'Acme']);

            // Assert
            $this->expectException(MorphKeyViolationException::class);
            Models::getModelKey($org);
        });

        test('throws exception when both morphKeyMap and enforceMorphKeyMap are configured', function (): void {
            // Arrange
            $config = $this->app->make(Repository::class);
            $config->set('warden.morphKeyMap', [
                User::class => 'id',
            ]);
            $config->set('warden.enforceMorphKeyMap', [
                Organization::class => 'ulid',
            ]);

            // Act & Assert
            $this->expectException(InvalidConfigurationException::class);
            $this->expectExceptionMessage('Cannot configure both morphKeyMap and enforceMorphKeyMap simultaneously');

            $provider = new WardenServiceProvider($this->app);
            $provider->register();
            $provider->boot();
        });

        test('prioritizes enforcement when only enforceMorphKeyMap is set', function (): void {
            // Arrange
            $config = $this->app->make(Repository::class);
            $config->set('warden.morphKeyMap', []);
            $config->set('warden.enforceMorphKeyMap', [
                User::class => 'id',
            ]);

            // Act
            $provider = new WardenServiceProvider($this->app);
            $provider->register();
            $provider->boot();

            $org = Organization::query()->create(['name' => 'Acme']);

            // Assert
            $this->expectException(MorphKeyViolationException::class);
            Models::getModelKey($org);
        });
    });
});
