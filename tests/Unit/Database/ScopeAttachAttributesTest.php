<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Database\Models;
use Cline\Warden\Database\Role;
use Cline\Warden\Database\Scope\Scope;
use Cline\Warden\Enums\PrimaryKeyType;
use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\Models\User;

describe('Scope getAttachAttributes with Primary Key Generation', function (): void {
    beforeEach(function (): void {
        Models::reset();
        config()->set('warden.user_model', User::class);
    });

    afterEach(function (): void {
        Models::reset();
    });

    describe('Edge Cases', function (): void {
        test('getAttachAttributes works with auto-increment primary keys', function (): void {
            // Arrange
            config(['warden.primary_key_type' => PrimaryKeyType::ID->value]);
            $scope = new Scope();
            $scope->to('tenant-1');

            // Act
            $attributes = $scope->getAttachAttributes();

            // Assert
            expect($attributes)->toBe(['scope' => 'tenant-1']);
        });

        test('getAttachAttributes works with ULID primary keys', function (): void {
            // Arrange
            config(['warden.primary_key_type' => PrimaryKeyType::ULID->value]);
            $scope = new Scope();
            $scope->to('tenant-ulid');

            // Act
            $attributes = $scope->getAttachAttributes();

            // Assert
            expect($attributes)->toBe(['scope' => 'tenant-ulid']);
        });

        test('getAttachAttributes works with UUID primary keys', function (): void {
            // Arrange
            config(['warden.primary_key_type' => PrimaryKeyType::UUID->value]);
            $scope = new Scope();
            $scope->to('tenant-uuid');

            // Act
            $attributes = $scope->getAttachAttributes();

            // Assert
            expect($attributes)->toBe(['scope' => 'tenant-uuid']);
        });

        test('getAttachAttributes returns empty when scope is null', function (): void {
            // Arrange
            config(['warden.primary_key_type' => PrimaryKeyType::ULID->value]);
            $scope = new Scope();
            // No scope set

            // Act
            $attributes = $scope->getAttachAttributes();

            // Assert
            expect($attributes)->toBe([]);
        });

        test('getAttachAttributes with authority parameter works with ULID', function (): void {
            // Arrange
            config(['warden.primary_key_type' => PrimaryKeyType::ULID->value]);

            // Recreate users table with correct column type
            Schema::dropIfExists('users');
            Schema::create('users', function ($table): void {
                $table->ulid('id')->primary();
                $table->string('name')->nullable();
                $table->integer('age')->nullable();
                $table->integer('account_id')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });

            $scope = new Scope();
            $scope->to('tenant-1');

            $user = User::query()->create(['name' => 'Test User']);

            // Act
            $attributes = $scope->getAttachAttributes($user);

            // Assert
            expect($attributes)->toBe(['scope' => 'tenant-1']);
        });

        test('getAttachAttributes with authority parameter works with UUID', function (): void {
            // Arrange
            config(['warden.primary_key_type' => PrimaryKeyType::UUID->value]);

            // Recreate users table with correct column type
            Schema::dropIfExists('users');
            Schema::create('users', function ($table): void {
                $table->uuid('id')->primary();
                $table->string('name')->nullable();
                $table->integer('age')->nullable();
                $table->integer('account_id')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });

            $scope = new Scope();
            $scope->to('tenant-1');

            $user = User::query()->create(['name' => 'Test User']);

            // Act
            $attributes = $scope->getAttachAttributes($user);

            // Assert
            expect($attributes)->toBe(['scope' => 'tenant-1']);
        });

        test('getAttachAttributes excludes role abilities when configured with ULID', function (): void {
            // Arrange
            config(['warden.primary_key_type' => PrimaryKeyType::ULID->value]);
            $scope = new Scope();
            $scope->to('tenant-1');
            $scope->dontScopeRoleAbilities();

            // Act
            $attributes = $scope->getAttachAttributes(Role::class);

            // Assert
            expect($attributes)->toBe([]);
        });

        test('getAttachAttributes includes role abilities by default with UUID', function (): void {
            // Arrange
            config(['warden.primary_key_type' => PrimaryKeyType::UUID->value]);
            $scope = new Scope();
            $scope->to('tenant-1');
            // Don't call dontScopeRoleAbilities - should include by default

            // Act
            $attributes = $scope->getAttachAttributes(Role::class);

            // Assert
            expect($attributes)->toBe(['scope' => 'tenant-1']);
        });

        test('getAttachAttributes with role class string excludes when configured', function (): void {
            // Arrange
            config(['warden.primary_key_type' => PrimaryKeyType::ULID->value]);
            $scope = new Scope();
            $scope->to('tenant-1');
            $scope->dontScopeRoleAbilities();

            // Act
            $attributes = $scope->getAttachAttributes(Role::class);

            // Assert
            expect($attributes)->toBe([]);
        });

        test('getAttachAttributes with role class string includes by default', function (): void {
            // Arrange
            config(['warden.primary_key_type' => PrimaryKeyType::UUID->value]);
            $scope = new Scope();
            $scope->to('tenant-1');
            // Don't call dontScopeRoleAbilities - should include by default

            // Act
            $attributes = $scope->getAttachAttributes(Role::class);

            // Assert
            expect($attributes)->toBe(['scope' => 'tenant-1']);
        });
    });
});
