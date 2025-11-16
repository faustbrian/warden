<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests;

use Cline\Warden\Clipboard\Clipboard;
use Cline\Warden\Contracts\ClipboardInterface;
use Cline\Warden\Database\Models;
use Cline\Warden\Guard;
use Cline\Warden\Warden as WardenClass;
use Illuminate\Auth\Access\Gate;
use Illuminate\Container\Container;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Override;
use Tests\Fixtures\Models\Account;
use Tests\Fixtures\Models\User;

use function env;
use function Orchestra\Testbench\artisan;
use function Orchestra\Testbench\package_path;

/**
 * @internal
 *
 * @author Brian Faust <brian@cline.sh>
 */
abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Setup the world for the tests.
     */
    #[Override()]
    protected function setUp(): void
    {
        parent::setUp();

        Mockery::close();

        // Clear booted models to prevent stale event listeners
        Model::clearBootedModels();

        Models::setUsersModel(User::class);

        $keyName = new User()->getKeyName();
        Models::morphKeyMap([
            User::class => $keyName,
            Account::class => $keyName,
        ]);

        static::registerClipboard();
    }

    /**
     * Clean up after each test.
     */
    #[Override()]
    protected function tearDown(): void
    {
        // Clear booted models after test to prevent contamination
        Model::clearBootedModels();
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Get a bouncer instance.
     */
    public static function bouncer(?Model $authority = null): WardenClass
    {
        $gate = static::gate($authority ?: User::query()->create());

        $clipboard = Container::getInstance()->make(ClipboardInterface::class);

        $warden = new WardenClass(
            new Guard($clipboard)->registerAt($gate),
        );

        return $warden->setGate($gate);
    }

    /**
     * Get an access gate instance.
     */
    public static function gate(Model $authority): Gate
    {
        return new Gate(Container::getInstance(), fn (): Model => $authority);
    }

    /**
     * Get the Clipboard instance from the container.
     */
    public function clipboard(): ClipboardInterface
    {
        return Container::getInstance()->make(ClipboardInterface::class);
    }

    /**
     * Register the clipboard with the container.
     */
    protected static function registerClipboard(): void
    {
        Container::getInstance()
            ->instance(ClipboardInterface::class, static::makeClipboard());
    }

    /**
     * Make a new clipboard with the container.
     */
    protected static function makeClipboard(): ClipboardInterface
    {
        return new Clipboard();
    }

    /**
     * Define environment setup.
     *
     * @param mixed $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('warden.primary_key_type', env('WARDEN_PRIMARY_KEY_TYPE', 'id'));
        $app['config']->set('warden.actor_morph_type', env('WARDEN_ACTOR_MORPH_TYPE', 'morph'));
        $app['config']->set('warden.context_morph_type', env('WARDEN_CONTEXT_MORPH_TYPE', 'morph'));
        $app['config']->set('warden.subject_morph_type', env('WARDEN_SUBJECT_MORPH_TYPE', 'morph'));
    }

    /**
     * Define database migrations.
     */
    protected function defineDatabaseMigrations(): void
    {
        artisan($this, 'migrate:install');

        $this->loadMigrationsFrom(package_path('migrations'));
        $this->loadMigrationsFrom(__DIR__.'/Fixtures/database/migrations');
    }

    /**
     * Get the DB manager instance from the container.
     */
    protected function db(): DatabaseManager
    {
        return Container::getInstance()->make(ConnectionResolverInterface::class);
    }
}
