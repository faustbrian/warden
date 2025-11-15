<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Database;

use Cline\Warden\Database\Ability;
use Cline\Warden\Database\Role;
use Cline\Warden\Enums\PrimaryKeyType;

use function config;
use function describe;
use function expect;
use function test;

describe('Primary Key Type Configuration', function (): void {
    describe('auto-incrementing integer IDs', function (): void {
        test('Role uses auto-incrementing ID when configured', function (): void {
            // Act
            $role = Role::query()->create(['name' => 'admin', 'title' => 'Administrator']);

            // Assert
            expect($role->getIncrementing())->toBeTrue()
                ->and($role->getKeyType())->toBe('int')
                ->and($role->id)->toBeInt();
        });

        test('Ability uses auto-incrementing ID when configured', function (): void {
            // Act
            $ability = Ability::query()->create(['name' => 'edit-posts', 'title' => 'Edit Posts']);

            // Assert
            expect($ability->getIncrementing())->toBeTrue()
                ->and($ability->getKeyType())->toBe('int')
                ->and($ability->id)->toBeInt();
        });
    });

    describe('trait configuration behavior', function (): void {
        test('configures incrementing=false for ULID type', function (): void {
            // Arrange
            config(['warden.primary_key_type' => PrimaryKeyType::ULID->value]);

            // Act
            $role = new Role();

            // Assert
            expect($role->getIncrementing())->toBeFalse()
                ->and($role->getKeyType())->toBe('string');
        });

        test('configures incrementing=false for UUID type', function (): void {
            // Arrange
            config(['warden.primary_key_type' => PrimaryKeyType::UUID->value]);

            // Act
            $role = new Role();

            // Assert
            expect($role->getIncrementing())->toBeFalse()
                ->and($role->getKeyType())->toBe('string');
        });

        test('Ability model also configures correctly for ULID', function (): void {
            // Arrange
            config(['warden.primary_key_type' => PrimaryKeyType::ULID->value]);

            // Act
            $ability = new Ability();

            // Assert
            expect($ability->getIncrementing())->toBeFalse()
                ->and($ability->getKeyType())->toBe('string');
        });

        test('Ability model also configures correctly for UUID', function (): void {
            // Arrange
            config(['warden.primary_key_type' => PrimaryKeyType::UUID->value]);

            // Act
            $ability = new Ability();

            // Assert
            expect($ability->getIncrementing())->toBeFalse()
                ->and($ability->getKeyType())->toBe('string');
        });
    });

    describe('defaults to auto-increment when invalid config', function (): void {
        test('falls back to ID type for invalid config value', function (): void {
            // Arrange
            config(['warden.primary_key_type' => 'invalid']);

            // Act
            $role = Role::query()->create(['name' => 'admin']);

            // Assert - should default to ID behavior
            expect($role->id)->toBeInt();
        });
    });
});
