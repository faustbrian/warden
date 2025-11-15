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
use Cline\Warden\Database\Models;
use Cline\Warden\Exceptions\InvalidConfigurationException;
use Illuminate\Cache\ArrayStore;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\ServiceProvider;
use Override;

use function app;
use function app_path;
use function class_exists;
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
final class WardenServiceProvider extends ServiceProvider
{
    /**
     * Register Warden services in the container.
     *
     * Registers the Warden singleton with configured clipboard and gate instances,
     * and registers artisan commands for console access.
     */
    #[Override()]
    public function register(): void
    {
        $this->registerBouncer();
        $this->registerCommands();
    }

    /**
     * Bootstrap Warden services.
     *
     * Configures model relationships, sets the user model from auth config,
     * registers Warden with the gate, and publishes assets when running in console.
     */
    public function boot(): void
    {
        $this->registerMorphs();
        $this->setUserModel();
        $this->configureMorphKeyMaps();

        $this->registerAtGate();

        if ($this->app->runningInConsole()) {
            $this->publishMiddleware();
            $this->publishMigrations();
        }
    }

    /**
     * Register Warden as a singleton in the service container.
     *
     * Creates a Warden instance with cached clipboard (using ArrayStore for in-memory
     * caching) and wires it to Laravel's Gate for authorization integration.
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
     * Register Warden's artisan commands.
     *
     * Makes Warden's console commands available for maintenance tasks like
     * cleaning up stale permissions and orphaned records.
     */
    private function registerCommands(): void
    {
        $this->commands(CleanCommand::class);
    }

    /**
     * Register Warden's models in Laravel's polymorphic morph map.
     *
     * Ensures polymorphic relationships use consistent naming when storing
     * model types in the database, preventing issues during refactoring.
     */
    private function registerMorphs(): void
    {
        Models::updateMorphMap();
    }

    /**
     * Publish the package's middleware stub to the application.
     *
     * Makes the ScopeBouncer middleware available for users to customize
     * and register in their HTTP kernel for multi-tenant scoping.
     */
    private function publishMiddleware(): void
    {
        $stub = __DIR__.'/../middleware/ScopeBouncer.php';

        $target = app_path('Http/Middleware/ScopeBouncer.php');

        $this->publishes([$stub => $target], 'bouncer.middleware');
    }

    /**
     * Publish the package's database migrations to the application.
     *
     * Generates timestamped migration files for creating Warden's database tables.
     * Skips publishing if migrations have already been published (CreateBouncerTables exists).
     */
    private function publishMigrations(): void
    {
        if (class_exists('CreateBouncerTables')) {
            return;
        }

        $timestamp = Date::createFromTimestamp(Date::now()->getTimestamp())->format('Y_m_d_His');

        $stub = __DIR__.'/../migrations/create_warden_tables.php';

        $target = $this->app->databasePath().'/migrations/'.$timestamp.'_create_warden_tables.php';

        $this->publishes([$stub => $target], 'bouncer.migrations');
    }

    /**
     * Configure Warden to use the application's user model.
     *
     * Reads the user model class from Laravel's auth configuration and
     * registers it with Warden's Models registry.
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
     * Traverses the auth configuration to find the user model class for the default
     * guard and provider. Returns null if configuration is incomplete or the model
     * class is not an Eloquent model.
     *
     * @return null|string The user model class name or null
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
     * Applies morphKeyMap or enforceMorphKeyMap configuration based on which is defined.
     * Throws InvalidConfigurationException if both are configured simultaneously.
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
     * Resolving Warden from the container triggers its Factory creation process,
     * which automatically registers the guard's callbacks with Laravel's Gate.
     * This lazy registration ensures the Gate is fully initialized before Warden
     * hooks into it.
     */
    private function registerAtGate(): void
    {
        // When creating a Bouncer instance thru the Factory class, it'll
        // auto-register at the gate. We already registered Bouncer in
        // the container using the Factory, so now we'll resolve it.
        $this->app->make(Warden::class);
    }
}
