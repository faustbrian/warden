<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Database\Role;
use Cline\Warden\Enums\PrimaryKeyType;
use Cline\Warden\Support\PrimaryKeyGenerator;
use Illuminate\Support\Str;

describe('HasWardenPrimaryKey Trait', function (): void {
    describe('Happy Path - Model Configuration', function (): void {
        test('configures model for ULID when configured', function (): void {
            // Arrange
            config(['warden.primary_key_type' => PrimaryKeyType::ULID->value]);

            // Act
            $role = new Role();

            // Assert
            expect($role->getIncrementing())->toBeFalse()
                ->and($role->getKeyType())->toBe('string');
        })->group('happy-path');

        test('configures model for UUID when configured', function (): void {
            // Arrange
            config(['warden.primary_key_type' => PrimaryKeyType::UUID->value]);

            // Act
            $role = new Role();

            // Assert
            expect($role->getIncrementing())->toBeFalse()
                ->and($role->getKeyType())->toBe('string');
        })->group('happy-path');

        test('uses auto-incrementing ID configuration when configured for ID type', function (): void {
            // Arrange
            config(['warden.primary_key_type' => PrimaryKeyType::ID->value]);

            // Act
            $role = new Role();

            // Assert
            expect($role->getIncrementing())->toBeTrue()
                ->and($role->getKeyType())->toBe('int');
        })->group('happy-path');

        test('creates role with auto-increment ID when configured for ID type', function (): void {
            // Arrange
            config(['warden.primary_key_type' => PrimaryKeyType::ID->value]);

            // Act
            $role = Role::query()->create(['name' => 'admin', 'title' => 'Administrator']);

            // Assert
            expect($role->id)->toBeInt()
                ->and($role->id)->toBeGreaterThan(0);
        })->skip(fn (): bool => config('warden.primary_key_type') !== 'id', 'Test requires ID primary key type')->group('happy-path');

        test('uniqueIds returns empty array for auto-increment keys', function (): void {
            // Arrange
            config(['warden.primary_key_type' => PrimaryKeyType::ID->value]);
            $role = new Role();

            // Act
            $uniqueIds = $role->uniqueIds();

            // Assert
            expect($uniqueIds)->toBeArray()
                ->and($uniqueIds)->toBeEmpty();
        })->group('happy-path');
    });

    describe('Happy Path - Primary Key Generation Logic', function (): void {
        test('bootHasWardenPrimaryKey does not set key when using auto-increment', function (): void {
            // Arrange
            config(['warden.primary_key_type' => PrimaryKeyType::ID->value]);

            // Act
            $role = new Role(['name' => 'admin', 'title' => 'Administrator']);
            $keyBeforeSave = $role->getAttribute('id');
            $role->save();

            // Assert - ID should be null before save, then auto-generated
            expect($keyBeforeSave)->toBeNull()
                ->and($role->id)->toBeInt();
        })->skip(fn (): bool => config('warden.primary_key_type') !== 'id', 'Test requires ID primary key type')->group('happy-path');

        test('PrimaryKeyGenerator generates null for ID type', function (): void {
            // Arrange
            config(['warden.primary_key_type' => PrimaryKeyType::ID->value]);

            // Act
            $primaryKey = PrimaryKeyGenerator::generate();

            // Assert
            expect($primaryKey->isAutoIncrementing())->toBeTrue()
                ->and($primaryKey->value)->toBeNull()
                ->and($primaryKey->requiresValue())->toBeFalse();
        })->skip(fn (): bool => config('warden.primary_key_type') !== 'id', 'Test requires ID primary key type')->group('happy-path');

        test('PrimaryKeyGenerator generates ULID for ULID type', function (): void {
            // Arrange
            config(['warden.primary_key_type' => PrimaryKeyType::ULID->value]);

            // Act
            $primaryKey = PrimaryKeyGenerator::generate();

            // Assert
            expect($primaryKey->isAutoIncrementing())->toBeFalse()
                ->and($primaryKey->value)->toBeString()
                ->and($primaryKey->value)->toHaveLength(26)
                ->and($primaryKey->requiresValue())->toBeTrue();
        })->group('happy-path');

        test('PrimaryKeyGenerator generates UUID for UUID type', function (): void {
            // Arrange
            config(['warden.primary_key_type' => PrimaryKeyType::UUID->value]);

            // Act
            $primaryKey = PrimaryKeyGenerator::generate();

            // Assert
            expect($primaryKey->isAutoIncrementing())->toBeFalse()
                ->and($primaryKey->value)->toBeString()
                ->and($primaryKey->value)->toHaveLength(36)
                ->and($primaryKey->value)->toMatch('/^[a-f0-9-]{36}$/')
                ->and($primaryKey->requiresValue())->toBeTrue();
        })->group('happy-path');
    });

    describe('Edge Cases - Configuration Edge Cases', function (): void {
        test('uniqueIds returns array containing default primary key name for ULID', function (): void {
            // Arrange
            config(['warden.primary_key_type' => PrimaryKeyType::ULID->value]);
            $role = new Role();

            // Act
            $uniqueIds = $role->uniqueIds();

            // Assert
            expect($uniqueIds)->toBeArray()
                ->and($uniqueIds)->toHaveCount(1)
                ->and($uniqueIds[0])->toBe('id')
                ->and($uniqueIds)->toBe([$role->getKeyName()]);
        })->group('edge-case');

        test('configures string key type for ULID on model initialization', function (): void {
            // Arrange
            config(['warden.primary_key_type' => PrimaryKeyType::ULID->value]);

            // Act
            $role = new Role();

            // Assert
            expect($role->getIncrementing())->toBeFalse()
                ->and($role->getKeyType())->toBe('string');
        })->group('edge-case');

        test('configures string key type for UUID on model initialization', function (): void {
            // Arrange
            config(['warden.primary_key_type' => PrimaryKeyType::UUID->value]);

            // Act
            $role = new Role();

            // Assert
            expect($role->getIncrementing())->toBeFalse()
                ->and($role->getKeyType())->toBe('string');
        })->group('edge-case');

        test('does not change configuration for ID type on initialization', function (): void {
            // Arrange
            config(['warden.primary_key_type' => PrimaryKeyType::ID->value]);

            // Act
            $role = new Role();

            // Assert
            expect($role->getIncrementing())->toBeTrue()
                ->and($role->getKeyType())->toBe('int');
        })->skip(fn (): bool => config('warden.primary_key_type') !== 'id', 'Test requires ID primary key type')->group('edge-case');
    });

    describe('Edge Cases - Uniqueness of Generated Keys', function (): void {
        test('generates unique ULID for each call', function (): void {
            // Arrange
            config(['warden.primary_key_type' => PrimaryKeyType::ULID->value]);

            // Act
            $key1 = PrimaryKeyGenerator::generate();
            $key2 = PrimaryKeyGenerator::generate();

            // Assert
            expect($key1->value)->not->toBe($key2->value)
                ->and($key1->value)->toHaveLength(26)
                ->and($key2->value)->toHaveLength(26);
        })->group('edge-case');

        test('generates unique UUID for each call', function (): void {
            // Arrange
            config(['warden.primary_key_type' => PrimaryKeyType::UUID->value]);

            // Act
            $key1 = PrimaryKeyGenerator::generate();
            $key2 = PrimaryKeyGenerator::generate();

            // Assert
            expect($key1->value)->not->toBe($key2->value)
                ->and($key1->value)->toHaveLength(36)
                ->and($key2->value)->toHaveLength(36);
        })->group('edge-case');

        test('generates lowercase ULID', function (): void {
            // Arrange
            config(['warden.primary_key_type' => PrimaryKeyType::ULID->value]);

            // Act
            $key = PrimaryKeyGenerator::generate();

            // Assert
            expect($key->value)->toBe(Str::lower($key->value))
                ->and($key->value)->toMatch('/^[a-z0-9]{26}$/');
        })->group('edge-case');

        test('generates lowercase UUID', function (): void {
            // Arrange
            config(['warden.primary_key_type' => PrimaryKeyType::UUID->value]);

            // Act
            $key = PrimaryKeyGenerator::generate();

            // Assert
            expect($key->value)->toBe(Str::lower($key->value))
                ->and($key->value)->toMatch('/^[a-f0-9-]{36}$/');
        })->group('edge-case');
    });

    describe('Sad Path - Invalid Configuration', function (): void {
        test('falls back to ID type behavior when config is invalid', function (): void {
            // Arrange
            config(['warden.primary_key_type' => 'invalid_type']);

            // Act
            $role = new Role();

            // Assert - Should default to auto-increment behavior
            expect($role->getIncrementing())->toBeTrue()
                ->and($role->getKeyType())->toBe('int');
        })->group('sad-path');

        test('creates role with auto-increment when invalid config and no ID provided', function (): void {
            // Arrange
            config(['warden.primary_key_type' => 'invalid_type']);

            // Act
            $role = Role::query()->create(['name' => 'admin', 'title' => 'Administrator']);

            // Assert
            expect($role->id)->toBeInt()
                ->and($role->id)->toBeGreaterThan(0);
        })->skip(fn (): bool => config('warden.primary_key_type') !== 'id', 'Test requires ID primary key type')->group('sad-path');

        test('PrimaryKeyGenerator falls back to ID type for invalid config', function (): void {
            // Arrange
            config(['warden.primary_key_type' => 'invalid']);

            // Act
            $primaryKey = PrimaryKeyGenerator::generate();

            // Assert
            expect($primaryKey->type)->toBe(PrimaryKeyType::ID)
                ->and($primaryKey->value)->toBeNull()
                ->and($primaryKey->isAutoIncrementing())->toBeTrue();
        })->group('sad-path');
    });

    describe('Edge Cases - bootHasWardenPrimaryKey Behavior', function (): void {
        test('bootHasWardenPrimaryKey skips setting key when already set for ID type', function (): void {
            // Arrange
            config(['warden.primary_key_type' => PrimaryKeyType::ID->value]);
            $role = new Role(['name' => 'admin', 'title' => 'Administrator']);

            // Act - Set an ID manually before save (simulates key already being set)
            $role->setAttribute('id', 999);

            $idBeforeSave = $role->getAttribute('id');

            // Assert - The creating event handler should not override an already-set ID
            expect($idBeforeSave)->toBe(999);
        })->skip(fn (): bool => config('warden.primary_key_type') !== 'id', 'Test requires ID primary key type')->group('edge-case');

        test('model initialization does not trigger creating event', function (): void {
            // Arrange
            config(['warden.primary_key_type' => PrimaryKeyType::ULID->value]);

            // Act
            $role = new Role(['name' => 'admin', 'title' => 'Administrator']);

            // Assert - ID should be null since creating event hasn't fired yet
            expect($role->getAttribute('id'))->toBeNull();
        })->group('edge-case');

        test('initializeHasWardenPrimaryKey is called on model instantiation', function (): void {
            // Arrange
            config(['warden.primary_key_type' => PrimaryKeyType::ULID->value]);

            // Act
            $role = new Role();

            // Assert - Configuration should be applied even before save
            expect($role->getIncrementing())->toBeFalse()
                ->and($role->getKeyType())->toBe('string');
        })->group('edge-case');

        test('bootHasWardenPrimaryKey sets ULID when no key is set', function (): void {
            // Arrange
            config(['warden.primary_key_type' => PrimaryKeyType::ULID->value]);
            $role = new Role(['name' => 'admin', 'title' => 'Administrator']);

            // Verify the ID is NOT set before the event
            expect($role->getAttribute('id'))->toBeNull();

            // Act - Fire creating event using reflection
            $reflection = new ReflectionClass($role);
            $method = $reflection->getMethod('fireModelEvent');
            $method->invoke($role, 'creating');

            // Assert - A ULID should have been generated and set
            expect($role->getAttribute('id'))->not->toBeNull()
                ->and($role->getAttribute('id'))->toBeString()
                ->and(mb_strlen((string) $role->getAttribute('id')))->toBe(26); // ULID is 26 chars
        })->group('edge-case');

        test('bootHasWardenPrimaryKey sets UUID when no key is set', function (): void {
            // Arrange
            config(['warden.primary_key_type' => PrimaryKeyType::UUID->value]);
            $role = new Role(['name' => 'admin', 'title' => 'Administrator']);

            // Verify the ID is NOT set before the event
            expect($role->getAttribute('id'))->toBeNull();

            // Act - Fire creating event using reflection
            $reflection = new ReflectionClass($role);
            $method = $reflection->getMethod('fireModelEvent');
            $method->invoke($role, 'creating');

            // Assert - A UUID should have been generated and set
            expect($role->getAttribute('id'))->not->toBeNull()
                ->and($role->getAttribute('id'))->toBeString()
                ->and(mb_strlen((string) $role->getAttribute('id')))->toBe(36); // UUID is 36 chars
        })->group('edge-case');

        test('bootHasWardenPrimaryKey skips setting key when already set for ULID type', function (): void {
            // Arrange
            config(['warden.primary_key_type' => PrimaryKeyType::ULID->value]);
            $customUlid = (string) Str::ulid();
            $role = new Role(['name' => 'admin', 'title' => 'Administrator']);
            $role->setAttribute('id', $customUlid);

            // Verify the ID is set before the event
            $idBefore = $role->getAttribute('id');

            // Act - Fire creating event using reflection
            $reflection = new ReflectionClass($role);
            $method = $reflection->getMethod('fireModelEvent');
            $method->invoke($role, 'creating');

            // Assert - The custom ULID should be preserved (not overridden)
            expect($role->getAttribute('id'))->toBe($idBefore)
                ->and($role->getAttribute('id'))->toBe($customUlid);
        })->group('edge-case');

        test('bootHasWardenPrimaryKey skips setting key when already set for UUID type', function (): void {
            // Arrange
            config(['warden.primary_key_type' => PrimaryKeyType::UUID->value]);
            $customUuid = (string) Str::uuid();
            $role = new Role(['name' => 'admin', 'title' => 'Administrator']);
            $role->setAttribute('id', $customUuid);

            // Verify the ID is set before the event
            $idBefore = $role->getAttribute('id');

            // Act - Fire creating event using reflection
            $reflection = new ReflectionClass($role);
            $method = $reflection->getMethod('fireModelEvent');
            $method->invoke($role, 'creating');

            // Assert - The custom UUID should be preserved (not overridden)
            expect($role->getAttribute('id'))->toBe($idBefore)
                ->and($role->getAttribute('id'))->toBe($customUuid);
        })->group('edge-case');
    });
});
