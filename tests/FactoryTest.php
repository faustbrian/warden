<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Clipboard\CachedClipboard;
use Cline\Warden\Clipboard\Clipboard;
use Cline\Warden\Contracts\ClipboardInterface;
use Cline\Warden\Warden as WardenClass;
use Illuminate\Auth\Access\Gate;
use Illuminate\Cache\ArrayStore;
use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Models\User;

describe('WardenFactory', function (): void {
    describe('Happy Paths', function (): void {
        test('creates default warden instance with gate and cached clipboard', function (): void {
            // Act
            $warden = WardenClass::create();

            // Assert
            expect($warden)->toBeInstanceOf(WardenClass::class);
            expect($warden->getGate())->toBeInstanceOf(Gate::class);
            expect($warden->usesCachedClipboard())->toBeTrue();
        });

        test('creates warden instance for given user with abilities', function (): void {
            // Arrange
            $user = User::query()->create();

            // Act
            $warden = WardenClass::create($user);
            $warden->allow($user)->to('create-bouncers');

            // Assert
            expect($warden->can('create-bouncers'))->toBeTrue();
            expect($warden->cannot('delete-bouncers'))->toBeTrue();
        });

        test('builds warden with withUser method and abilities', function (): void {
            // Arrange
            $user = User::query()->create();

            // Act
            $warden = WardenClass::make()->withUser($user)->create();
            $warden->allow($user)->to('create-bouncers');

            // Assert
            expect($warden->can('create-bouncers'))->toBeTrue();
            expect($warden->cannot('delete-bouncers'))->toBeTrue();
        });

        test('builds warden with custom gate instance', function (): void {
            // Arrange
            $user = User::query()->create();
            $gate = new Gate(
                new Container(),
                fn () => $user,
            );

            // Act
            $warden = WardenClass::make()->withGate($gate)->create();
            $warden->allow($user)->to('create-bouncers');

            // Assert
            expect($warden->can('create-bouncers'))->toBeTrue();
            expect($warden->cannot('delete-bouncers'))->toBeTrue();
        });

        test('builds warden with custom cache store for caching abilities', function (): void {
            // Arrange
            $user = User::query()->create();
            $customCache = new ArrayStore();

            // Act
            $warden = WardenClass::make()
                ->withUser($user)
                ->withCache($customCache)
                ->create();
            $warden->allow($user)->to('test-ability');

            // Assert
            expect($warden)->toBeInstanceOf(WardenClass::class);
            expect($warden->usesCachedClipboard())->toBeTrue();
            expect($warden->can('test-ability'))->toBeTrue();
        });

        test('builds warden with custom clipboard instance', function (): void {
            // Arrange
            $user = User::query()->create();
            $customClipboard = new Clipboard();

            // Act
            $warden = WardenClass::make()
                ->withUser($user)
                ->withClipboard($customClipboard)
                ->create();
            $warden->allow($user)->to('test-ability');

            // Assert
            expect($warden)->toBeInstanceOf(WardenClass::class);
            expect($warden->usesCachedClipboard())->toBeFalse();
            expect($warden->can('test-ability'))->toBeTrue();
        });

        test('registers clipboard at container when flag is enabled', function (): void {
            // Arrange
            $user = User::query()->create();
            $container = Container::getInstance();
            $container->forgetInstance(ClipboardInterface::class);

            // Act
            $warden = WardenClass::make()
                ->withUser($user)
                ->registerClipboardAtContainer(true)
                ->create();

            // Assert
            expect($warden)->toBeInstanceOf(WardenClass::class);
            expect($container->bound(ClipboardInterface::class))->toBeTrue();
            expect($container->make(ClipboardInterface::class))->toBeInstanceOf(ClipboardInterface::class);
        });

        test('registers guard at gate and allows ability checks', function (): void {
            // Arrange
            $user = User::query()->create();

            // Act
            $warden = WardenClass::make()
                ->withUser($user)
                ->registerAtGate(true)
                ->create();
            $warden->allow($user)->to('test-ability');

            // Assert
            expect($warden)->toBeInstanceOf(WardenClass::class);
            expect($warden->can('test-ability'))->toBeTrue();
            expect($warden->can('non-existent-ability'))->toBeFalse();
        });

        test('chains all configuration methods successfully', function (): void {
            // Arrange
            $user = User::query()->create();
            $customCache = new ArrayStore();
            $customClipboard = new CachedClipboard($customCache);
            $customGate = new Gate(
                new Container(),
                fn () => $user,
            );

            // Act
            $warden = WardenClass::make()
                ->withUser($user)
                ->withCache($customCache)
                ->withClipboard($customClipboard)
                ->withGate($customGate)
                ->registerClipboardAtContainer(true)
                ->registerAtGate(true)
                ->create();
            $warden->allow($user)->to('chained-ability');

            // Assert
            expect($warden)->toBeInstanceOf(WardenClass::class);
            expect($warden->can('chained-ability'))->toBeTrue();
            expect($warden->getGate())->toBe($customGate);
        });

        test('uses default array store when no cache provided', function (): void {
            // Arrange
            $user = User::query()->create();

            // Act
            $warden = WardenClass::make()
                ->withUser($user)
                ->create();
            $warden->allow($user)->to('cached-ability');

            // Assert
            expect($warden)->toBeInstanceOf(WardenClass::class);
            expect($warden->usesCachedClipboard())->toBeTrue();
            expect($warden->can('cached-ability'))->toBeTrue();
        });
    });

    describe('Edge Cases', function (): void {
        test('skips clipboard container registration when flag is disabled', function (): void {
            // Arrange
            $user = User::query()->create();
            $container = Container::getInstance();
            $container->forgetInstance(ClipboardInterface::class);

            // Act
            $warden = WardenClass::make()
                ->withUser($user)
                ->registerClipboardAtContainer(false)
                ->create();

            // Assert
            expect($warden)->toBeInstanceOf(WardenClass::class);
            expect($container->bound(ClipboardInterface::class) && $container->resolved(ClipboardInterface::class))->toBeFalse();
        });

        test('skips gate registration and warden abilities do not work through gate', function (): void {
            // Arrange
            $user = User::query()->create();
            $customGate = new Gate(
                new Container(),
                fn () => $user,
            );
            $customGate->define('gate-ability', fn (): true => true);

            // Act
            $warden = WardenClass::make()
                ->withUser($user)
                ->withGate($customGate)
                ->registerAtGate(false)
                ->create();
            $warden->allow($user)->to('test-ability');

            // Assert
            expect($warden)->toBeInstanceOf(WardenClass::class);
            expect($warden->can('gate-ability'))->toBeTrue();
            expect($warden->can('test-ability'))->toBeFalse();
        });

        test('chains methods with registration flags disabled', function (): void {
            // Arrange
            $user = User::query()->create();
            $customCache = new ArrayStore();
            $customGate = new Gate(
                new Container(),
                fn () => $user,
            );
            $customGate->define('gate-ability', fn (): true => true);

            // Act
            $warden = WardenClass::make()
                ->withUser($user)
                ->withCache($customCache)
                ->withGate($customGate)
                ->registerClipboardAtContainer(false)
                ->registerAtGate(false)
                ->create();
            $warden->allow($user)->to('test-ability');

            // Assert
            expect($warden)->toBeInstanceOf(WardenClass::class);
            expect($warden->usesCachedClipboard())->toBeTrue();
            expect($warden->can('gate-ability'))->toBeTrue();
            expect($warden->can('test-ability'))->toBeFalse();
        });

        test('creates with minimal configuration using only user', function (): void {
            // Arrange
            $user = User::query()->create();

            // Act
            $warden = WardenClass::make()
                ->withUser($user)
                ->create();
            $warden->allow($user)->to('minimal-ability');

            // Assert
            expect($warden)->toBeInstanceOf(WardenClass::class);
            expect($warden->can('minimal-ability'))->toBeTrue();
            expect($warden->usesCachedClipboard())->toBeTrue();
        });

        test('returns factory instance from all fluent methods for chaining', function (): void {
            // Arrange
            $user = User::query()->create();
            $cache = new ArrayStore();
            $clipboard = new Clipboard();
            $gate = new Gate(
                new Container(),
                fn () => $user,
            );

            // Act
            $factory = WardenClass::make();

            // Assert
            expect($factory->withUser($user))->toBe($factory);
            expect($factory->withCache($cache))->toBe($factory);
            expect($factory->withClipboard($clipboard))->toBe($factory);
            expect($factory->withGate($gate))->toBe($factory);
            expect($factory->registerClipboardAtContainer(false))->toBe($factory);
            expect($factory->registerAtGate(false))->toBe($factory);
        });
    });
});
