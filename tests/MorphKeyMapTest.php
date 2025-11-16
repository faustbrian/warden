<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Database\Models;
use Cline\Warden\Exceptions\MorphKeyViolationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Models\Organization;
use Tests\Fixtures\Models\User;

beforeEach(function (): void {
    Models::reset();
    Models::setUsersModel(User::class);
});

afterEach(function (): void {
    Models::reset();
});

describe('MorphKeyMap', function (): void {
    describe('Happy Paths', function (): void {
        test('returns model key name when no mapping exists', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'John']);

            // Act
            $result = Models::getModelKey($user);

            // Assert
            expect($result)->toBe('id');
        });

        test('returns mapped key when mapping exists', function (): void {
            // Arrange
            Models::morphKeyMap([
                Organization::class => 'ulid',
            ]);
            $org = Organization::query()->create(['name' => 'Acme']);

            // Act
            $result = Models::getModelKey($org);

            // Assert
            expect($result)->toBe('ulid');
        });

        test('allows multiple models to be mapped simultaneously', function (): void {
            // Arrange
            Models::morphKeyMap([
                User::class => 'id',
                Organization::class => 'ulid',
            ]);
            $user = User::query()->create(['name' => 'John']);
            $org = Organization::query()->create(['name' => 'Acme']);

            // Act & Assert
            expect(Models::getModelKey($user))->toBe('id');
            expect(Models::getModelKey($org))->toBe('ulid');
        });

        test('merges multiple morph key map calls', function (): void {
            // Arrange
            Models::morphKeyMap([
                User::class => 'id',
            ]);
            Models::morphKeyMap([
                Organization::class => 'ulid',
            ]);
            $user = User::query()->create(['name' => 'John']);
            $org = Organization::query()->create(['name' => 'Acme']);

            // Act & Assert
            expect(Models::getModelKey($user))->toBe('id');
            expect(Models::getModelKey($org))->toBe('ulid');
        });

        test('does not throw without enforcement when mapping missing', function (): void {
            // Arrange
            Models::morphKeyMap([
                User::class => 'id',
            ]);
            $org = Organization::query()->create(['name' => 'Acme']);

            // Act
            $key = Models::getModelKey($org);

            // Assert - Falls back to model's actual configured key name
            expect($key)->toBe($org->getKeyName());
        });

        test('does not throw with enforcement when mapping exists', function (): void {
            // Arrange
            Models::enforceMorphKeyMap([
                User::class => 'id',
                Organization::class => 'ulid',
            ]);
            $org = Organization::query()->create(['name' => 'Acme']);

            // Act
            $key = Models::getModelKey($org);

            // Assert
            expect($key)->toBe('ulid');
        });
    });

    describe('Sad Paths', function (): void {
        test('throws with enforcement when mapping missing', function (): void {
            // Arrange
            $this->expectException(MorphKeyViolationException::class);
            $this->expectExceptionMessage('No polymorphic key mapping defined for [Tests\Fixtures\Models\Organization]');
            Models::enforceMorphKeyMap([
                User::class => 'id',
            ]);
            $org = Organization::query()->create(['name' => 'Acme']);

            // Act & Assert (expect exception)
            Models::getModelKey($org);
        });

        test('throws when require key map enabled and mapping missing', function (): void {
            // Arrange
            $this->expectException(MorphKeyViolationException::class);
            Models::morphKeyMap([
                User::class => 'id',
            ]);
            Models::requireKeyMap();
            $org = Organization::query()->create(['name' => 'Acme']);

            // Act & Assert (expect exception)
            Models::getModelKey($org);
        });
    });

    describe('Edge Cases', function (): void {
        test('reset clears key mappings', function (): void {
            // Arrange
            Models::morphKeyMap([
                User::class => 'id',
                Organization::class => 'ulid',
            ]);

            // Act
            Models::reset();

            // Assert
            $user = User::query()->create(['name' => 'John']);
            expect(Models::getModelKey($user))->toBe('id');
        });

        test('reset clears enforcement flag', function (): void {
            // Arrange
            Models::enforceMorphKeyMap([
                User::class => 'id',
            ]);

            // Act
            Models::reset();

            // Assert - After reset, falls back to model's actual configured key name
            $org = Organization::query()->create(['name' => 'Acme']);
            $key = Models::getModelKey($org);
            expect($key)->toBe($org->getKeyName());
        });
    });
});
