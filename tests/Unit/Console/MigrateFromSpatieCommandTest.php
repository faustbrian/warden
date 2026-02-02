<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Console\MigrateFromSpatieCommand;
use Illuminate\Console\Application as Artisan;
use Illuminate\Contracts\Config\Repository;
use Tests\Fixtures\Models\User;

describe('MigrateFromSpatieCommand', function (): void {
    beforeEach(function (): void {
        Artisan::starting(
            fn ($artisan) => $artisan->resolveCommands(MigrateFromSpatieCommand::class),
        );
    });

    describe('Sad Paths', function (): void {
        test('fails when default guard is null', function (): void {
            // Arrange
            $config = Mockery::mock(Repository::class);
            $config->shouldReceive('get')
                ->with('auth.defaults.guard')
                ->once()
                ->andReturnNull();

            $this->app->instance(Repository::class, $config);

            // Act & Assert
            $this
                ->artisan('warden:spatie')
                ->expectsOutput('Starting migration from Spatie Laravel Permission to Warden...')
                ->expectsOutput('Unable to determine default authentication guard.')
                ->assertExitCode(1);
        });

        test('fails when default guard is not a string', function (): void {
            // Arrange
            $config = Mockery::mock(Repository::class);
            $config->shouldReceive('get')
                ->with('auth.defaults.guard')
                ->once()
                ->andReturn(['invalid']);

            $this->app->instance(Repository::class, $config);

            // Act & Assert
            $this
                ->artisan('warden:spatie')
                ->expectsOutput('Starting migration from Spatie Laravel Permission to Warden...')
                ->expectsOutput('Unable to determine default authentication guard.')
                ->assertExitCode(1);
        });

        test('fails when provider is null', function (): void {
            // Arrange
            $config = Mockery::mock(Repository::class);
            $config->shouldReceive('get')
                ->with('auth.defaults.guard')
                ->once()
                ->andReturn('web');
            $config->shouldReceive('get')
                ->with('auth.guards.web.provider')
                ->once()
                ->andReturnNull();

            $this->app->instance(Repository::class, $config);

            // Act & Assert
            $this
                ->artisan('warden:spatie')
                ->expectsOutput('Starting migration from Spatie Laravel Permission to Warden...')
                ->expectsOutput('Unable to determine authentication provider.')
                ->assertExitCode(1);
        });

        test('fails when provider is not a string', function (): void {
            // Arrange
            $config = Mockery::mock(Repository::class);
            $config->shouldReceive('get')
                ->with('auth.defaults.guard')
                ->once()
                ->andReturn('web');
            $config->shouldReceive('get')
                ->with('auth.guards.web.provider')
                ->once()
                ->andReturn(123);

            $this->app->instance(Repository::class, $config);

            // Act & Assert
            $this
                ->artisan('warden:spatie')
                ->expectsOutput('Starting migration from Spatie Laravel Permission to Warden...')
                ->expectsOutput('Unable to determine authentication provider.')
                ->assertExitCode(1);
        });

        test('fails when user model is null', function (): void {
            // Arrange
            $config = Mockery::mock(Repository::class);
            $config->shouldReceive('get')
                ->with('auth.defaults.guard')
                ->once()
                ->andReturn('web');
            $config->shouldReceive('get')
                ->with('auth.guards.web.provider')
                ->once()
                ->andReturn('users');
            $config->shouldReceive('get')
                ->with('auth.providers.users.model')
                ->once()
                ->andReturnNull();

            $this->app->instance(Repository::class, $config);

            // Act & Assert
            $this
                ->artisan('warden:spatie')
                ->expectsOutput('Starting migration from Spatie Laravel Permission to Warden...')
                ->expectsOutput('Unable to determine user model.')
                ->assertExitCode(1);
        });

        test('fails when user model is not a string', function (): void {
            // Arrange
            $config = Mockery::mock(Repository::class);
            $config->shouldReceive('get')
                ->with('auth.defaults.guard')
                ->once()
                ->andReturn('web');
            $config->shouldReceive('get')
                ->with('auth.guards.web.provider')
                ->once()
                ->andReturn('users');
            $config->shouldReceive('get')
                ->with('auth.providers.users.model')
                ->once()
                ->andReturn(['App\Models\User']);

            $this->app->instance(Repository::class, $config);

            // Act & Assert
            $this
                ->artisan('warden:spatie')
                ->expectsOutput('Starting migration from Spatie Laravel Permission to Warden...')
                ->expectsOutput('Unable to determine user model.')
                ->assertExitCode(1);
        });
    });

    describe('Happy Paths', function (): void {
        test('successfully migrates when all configuration is valid', function (): void {
            // Arrange - Set up real config
            config([
                'auth.defaults.guard' => 'web',
                'auth.guards.web.provider' => 'users',
                'auth.providers.users.model' => User::class,
                'warden.migrators.spatie.tables' => [
                    'permissions' => 'spatie_permissions',
                    'roles' => 'spatie_roles',
                    'model_has_permissions' => 'spatie_model_has_permissions',
                    'model_has_roles' => 'spatie_model_has_roles',
                    'role_has_permissions' => 'spatie_role_has_permissions',
                ],
            ]);

            // Act & Assert - Migrator runs against empty database (won't migrate anything)
            $this
                ->artisan('warden:spatie')
                ->expectsOutput('Starting migration from Spatie Laravel Permission to Warden...')
                ->expectsOutput('Migration completed successfully!')
                ->assertExitCode(0);
        })->group('happy-path');

        test('successfully migrates with custom log channel', function (): void {
            // Arrange - Set up real config
            config([
                'auth.defaults.guard' => 'web',
                'auth.guards.web.provider' => 'users',
                'auth.providers.users.model' => User::class,
                'warden.migrators.spatie.tables' => [
                    'permissions' => 'spatie_permissions',
                    'roles' => 'spatie_roles',
                    'model_has_permissions' => 'spatie_model_has_permissions',
                    'model_has_roles' => 'spatie_model_has_roles',
                    'role_has_permissions' => 'spatie_role_has_permissions',
                ],
            ]);

            // Act & Assert - Migrator runs against empty database (won't migrate anything)
            $this
                ->artisan('warden:spatie', ['--log-channel' => 'custom'])
                ->expectsOutput('Starting migration from Spatie Laravel Permission to Warden...')
                ->expectsOutput('Migration completed successfully!')
                ->assertExitCode(0);
        })->group('happy-path');
    });

    describe('Edge Cases', function (): void {
        test('validates configuration in correct order', function (): void {
            // Arrange - Test that guard is validated before provider
            $config = Mockery::mock(Repository::class);
            $config->shouldReceive('get')
                ->with('auth.defaults.guard')
                ->once()
                ->andReturnNull();
            // Should not reach provider/model calls since guard validation fails first
            $config->shouldNotReceive('get')->with(Mockery::pattern('/auth\.guards\./'));
            $config->shouldNotReceive('get')->with(Mockery::pattern('/auth\.providers\./'));

            $this->app->instance(Repository::class, $config);

            // Act & Assert
            $this
                ->artisan('warden:spatie')
                ->expectsOutput('Starting migration from Spatie Laravel Permission to Warden...')
                ->expectsOutput('Unable to determine default authentication guard.')
                ->assertExitCode(1);
        });

        test('validates provider before user model', function (): void {
            // Arrange - Test that provider is validated before user model
            $config = Mockery::mock(Repository::class);
            $config->shouldReceive('get')
                ->with('auth.defaults.guard')
                ->once()
                ->andReturn('web');
            $config->shouldReceive('get')
                ->with('auth.guards.web.provider')
                ->once()
                ->andReturnNull();
            // Should not reach user model call since provider validation fails
            $config->shouldNotReceive('get')->with(Mockery::pattern('/auth\.providers\./'));

            $this->app->instance(Repository::class, $config);

            // Act & Assert
            $this
                ->artisan('warden:spatie')
                ->expectsOutput('Starting migration from Spatie Laravel Permission to Warden...')
                ->expectsOutput('Unable to determine authentication provider.')
                ->assertExitCode(1);
        });

        test('handles different guard names', function (): void {
            // Arrange
            $config = Mockery::mock(Repository::class);
            $config->shouldReceive('get')
                ->with('auth.defaults.guard')
                ->once()
                ->andReturn('api');
            $config->shouldReceive('get')
                ->with('auth.guards.api.provider')
                ->once()
                ->andReturnNull();

            $this->app->instance(Repository::class, $config);

            // Act & Assert
            $this
                ->artisan('warden:spatie')
                ->expectsOutput('Starting migration from Spatie Laravel Permission to Warden...')
                ->expectsOutput('Unable to determine authentication provider.')
                ->assertExitCode(1);
        });

        test('handles different provider names', function (): void {
            // Arrange
            $config = Mockery::mock(Repository::class);
            $config->shouldReceive('get')
                ->with('auth.defaults.guard')
                ->once()
                ->andReturn('admin');
            $config->shouldReceive('get')
                ->with('auth.guards.admin.provider')
                ->once()
                ->andReturn('admin_users');
            $config->shouldReceive('get')
                ->with('auth.providers.admin_users.model')
                ->once()
                ->andReturnNull();

            $this->app->instance(Repository::class, $config);

            // Act & Assert
            $this
                ->artisan('warden:spatie')
                ->expectsOutput('Starting migration from Spatie Laravel Permission to Warden...')
                ->expectsOutput('Unable to determine user model.')
                ->assertExitCode(1);
        });

        test('verifies command signature includes log channel option', function (): void {
            // Arrange
            $config = Mockery::mock(Repository::class);
            $config->shouldReceive('get')
                ->with('auth.defaults.guard')
                ->once()
                ->andReturnNull();

            $this->app->instance(Repository::class, $config);

            // Act & Assert - Test that command accepts log-channel option
            $this
                ->artisan('warden:spatie --log-channel=test')
                ->expectsOutput('Starting migration from Spatie Laravel Permission to Warden...')
                ->expectsOutput('Unable to determine default authentication guard.')
                ->assertExitCode(1);
        });
    });
});
