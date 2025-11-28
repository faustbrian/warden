<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden;

use Cline\Warden\Clipboard\CachedClipboard;
use Cline\Warden\Console\CleanCommand;
use Cline\Warden\Console\MigrateFromBouncerCommand;
use Cline\Warden\Console\MigrateFromSpatieCommand;
use Cline\Warden\Database\Models;
use Cline\Warden\Exceptions\InvalidConfigurationException;
use Illuminate\Cache\ArrayStore;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Override;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

use function app;
use function is_array;
use function is_string;
use function is_subclass_of;
use function sprintf;

/**
 * Laravel service provider for Warden authorization package.
 *
 * Handles registration and bootstrapping of Warden's components including the main
 * Warden instance, database models, artisan commands, and publishable assets
 * (migrations and middleware). Integrates Warden with Laravel's service container
 * and authorization gate.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class WardenServiceProvider extends PackageServiceProvider
{
    /**
     * Configure the package.
     *
     * Defines package configuration including publishable config, migrations,
     * and artisan commands. Uses Spatie's package tools for streamlined setup.
     *
     * @param Package $package The package instance to configure
     */
    public function configurePackage(Package $package): void
    {
        $package
            ->name('warden')
            ->hasConfigFile()
            ->hasMigration('create_warden_tables')
            ->hasCommands([
                CleanCommand::class,
                MigrateFromSpatieCommand::class,
                MigrateFromBouncerCommand::class,
            ])
            ->publishesServiceProvider('WardenServiceProvider');
    }

    /**
     * Register Warden services in the container.
     *
     * Called during Laravel's service provider registration phase. Registers
     * the Warden singleton with the service container.
     */
    #[Override()]
    public function registeringPackage(): void
    {
        $this->registerBouncer();
    }

    /**
     * Bootstrap Warden services.
     *
     * Called after all service providers have been registered. Initializes
     * Warden's models, configures polymorphic mappings, and integrates with
     * Laravel's Gate for authorization.
     */
    #[Override()]
    public function bootingPackage(): void
    {
        $this->registerMorphs();
        $this->setUserModel();
        $this->configureMorphKeyMaps();
        $this->registerAtGate();
    }

    /**
     * Register Warden as a singleton in the service container.
     *
     * Creates a Warden instance with cached clipboard using ArrayStore for fast
     * in-memory caching. Wires the instance to Laravel's Gate for seamless integration
     * with the authorization system. The singleton ensures a consistent Warden instance
     * is used throughout the application lifecycle.
     */
    private function registerBouncer(): void
    {
        $this->app->singleton(function (): Warden {
            $gate = app(Gate::class);
            $factory = new Factory();
            $clipboard = new CachedClipboard(
                new ArrayStore(),
            );

            return $factory
                ->withClipboard($clipboard)
                ->withGate($gate)
                ->create();
        });
    }

    /**
     * Register Warden's models in Laravel's polymorphic morph map.
     *
     * Configures Laravel's polymorphic morph map to use consistent, human-readable
     * aliases (like 'user', 'role', 'ability') instead of fully-qualified class names
     * when storing model types in polymorphic relationship columns. This prevents
     * database schema breakage when model classes are moved or renamed.
     */
    private function registerMorphs(): void
    {
        Models::updateMorphMap();
    }

    /**
     * Configure Warden to use the application's user model.
     *
     * Automatically detects the user model class from Laravel's auth configuration
     * and registers it with Warden's Models registry. This ensures Warden uses the
     * correct user model for authorization relationships without manual configuration.
     */
    private function setUserModel(): void
    {
        $model = $this->getUserModel();

        if ($model !== null) {
            /** @var class-string $model */
            Models::setUsersModel($model);
        }
    }

    /**
     * Extract the user model class from Laravel's authentication configuration.
     *
     * Traverses Laravel's auth configuration through the following path:
     * 1. Gets the default guard from 'auth.defaults.guard'
     * 2. Gets the provider from 'auth.guards.{guard}.provider'
     * 3. Gets the model from 'auth.providers.{provider}.model'
     *
     * Returns null if any step in the configuration chain is missing or invalid,
     * or if the final model class is not a subclass of Eloquent Model. This ensures
     * only valid Eloquent models are registered with Warden.
     *
     * @return null|string The fully-qualified user model class name, or null if not found/invalid
     */
    private function getUserModel(): ?string
    {
        $config = $this->app->make(Repository::class);

        $guard = $config->get('auth.defaults.guard');

        if ($guard === null || !is_string($guard)) {
            return null;
        }

        $provider = $config->get(sprintf('auth.guards.%s.provider', $guard));

        if ($provider === null || !is_string($provider)) {
            return null;
        }

        $model = $config->get(sprintf('auth.providers.%s.model', $provider));

        // The standard auth config that ships with Laravel references the
        // Eloquent User model in the above config path. However, users
        // are free to reference anything there - so we check first.
        if (is_string($model) && is_subclass_of($model, EloquentModel::class)) {
            return $model;
        }

        return null;
    }

    /**
     * Configure polymorphic key mappings from configuration.
     *
     * Reads and applies morphKeyMap or enforceMorphKeyMap configuration to control
     * how polymorphic model types are stored in the database. morphKeyMap provides
     * lenient mapping with fallback, while enforceMorphKeyMap is strict and throws
     * exceptions for unmapped types. Validates that only one mapping strategy is
     * configured to prevent conflicting behavior.
     *
     * @throws InvalidConfigurationException When both morphKeyMap and enforceMorphKeyMap are configured
     */
    private function configureMorphKeyMaps(): void
    {
        $config = $this->app->make(Repository::class);

        $morphKeyMap = $config->get('warden.morphKeyMap', []);
        $enforceMorphKeyMap = $config->get('warden.enforceMorphKeyMap', []);

        if (!is_array($morphKeyMap)) {
            $morphKeyMap = [];
        }

        if (!is_array($enforceMorphKeyMap)) {
            $enforceMorphKeyMap = [];
        }

        $hasMorphKeyMap = $morphKeyMap !== [];
        $hasEnforceMorphKeyMap = $enforceMorphKeyMap !== [];

        if ($hasMorphKeyMap && $hasEnforceMorphKeyMap) {
            throw InvalidConfigurationException::conflictingMorphKeyMaps();
        }

        if ($hasEnforceMorphKeyMap) {
            /** @var array<class-string, string> $enforceMorphKeyMap */
            Models::enforceMorphKeyMap($enforceMorphKeyMap);
        } elseif ($hasMorphKeyMap) {
            /** @var array<class-string, string> $morphKeyMap */
            Models::morphKeyMap($morphKeyMap);
        }
    }

    /**
     * Trigger Warden's gate registration by resolving it from the container.
     *
     * Resolves Warden from the container to trigger its Factory initialization,
     * which automatically registers authorization callbacks with Laravel's Gate.
     * This lazy registration pattern ensures the Gate is fully initialized before
     * Warden integrates, preventing timing issues during the application bootstrap.
     * The resolution also ensures all Warden configuration is applied before the
     * first authorization check occurs.
     */
    private function registerAtGate(): void
    {
        // When creating a Bouncer instance thru the Factory class, it'll
        // auto-register at the gate. We already registered Bouncer in
        // the container using the Factory, so now we'll resolve it.
        $this->app->make(Warden::class);
    }
}
