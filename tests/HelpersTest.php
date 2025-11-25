<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Database\Models;
use Cline\Warden\Support\Helpers;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Models\User;

describe('Helpers', function (): void {
    describe('Happy Paths', function (): void {
        test('extracts model and keys from string class with keys array', function (): void {
            // Arrange
            $modelClass = User::class;
            $keys = [1, 2, 3];

            // Act
            $result = Helpers::extractModelAndKeys($modelClass, $keys);

            // Assert
            expect($result)->not->toBeNull();
            expect($result)->toBeArray();
            expect($result)->toHaveCount(2);
            expect($result[0])->toBeInstanceOf(User::class);
            expect($result[1])->toEqual($keys);
        });

        test('extracts model and keys from model instance', function (): void {
            // Arrange
            $user = User::query()->create();

            // Act
            $result = Helpers::extractModelAndKeys($user);

            // Assert
            expect($result)->not->toBeNull();
            expect($result)->toBeArray();
            expect($result)->toHaveCount(2);
            expect($result[0])->toBe($user);
            expect($result[1])->toEqual([$user->getKey()]);
        });

        test('extracts model and keys from collection of models', function (): void {
            // Arrange
            $user1 = User::query()->create();
            $user2 = User::query()->create();
            $collection = new Collection([$user1, $user2]);

            // Act
            $result = Helpers::extractModelAndKeys($collection);

            // Assert
            expect($result)->not->toBeNull();
            expect($result)->toBeArray();
            expect($result)->toHaveCount(2);
            expect($result[0])->toBe($user1);
            expect($result[1])->toBeInstanceOf(Collection::class);
            expect($result[1]->all())->toEqual([$user1->getKey(), $user2->getKey()]);
        });

        test('maps integer authorities to user class', function (): void {
            // Arrange
            $authorities = [1, 2, 3];

            // Act
            $result = Helpers::mapAuthorityByClass($authorities);

            // Assert
            expect($result)->toHaveKey(User::class);
            expect($result[User::class])->toEqual([1, 2, 3]);
        });

        test('maps string authorities to user class', function (): void {
            // Arrange
            $authorities = ['uuid-1', 'uuid-2'];

            // Act
            $result = Helpers::mapAuthorityByClass($authorities);

            // Assert
            expect($result)->toHaveKey(User::class);
            expect($result[User::class])->toEqual(['uuid-1', 'uuid-2']);
        });

        test('maps mixed primitive authorities to user class', function (): void {
            // Arrange
            $authorities = [1, 'uuid-1', 2, 'uuid-2'];

            // Act
            $result = Helpers::mapAuthorityByClass($authorities);

            // Assert
            expect($result)->toHaveKey(User::class);
            expect($result[User::class])->toEqual([1, 'uuid-1', 2, 'uuid-2']);
        });

        test('maps mixed models and primitives to user class', function (): void {
            // Arrange
            $user1 = User::query()->create();
            $user2 = User::query()->create();
            $authorities = [$user1, 123, $user2, 'uuid-1'];

            // Act
            $result = Helpers::mapAuthorityByClass($authorities);

            // Assert
            expect($result)->toHaveKey(User::class);
            expect($result[User::class])->toHaveCount(4);
            expect($result[User::class])->toContain($user1->id);
            expect($result[User::class])->toContain($user2->id);
            expect($result[User::class])->toContain(123);
            expect($result[User::class])->toContain('uuid-1');
        });

        test('identifies indexed array with sequential numeric keys', function (): void {
            // Arrange
            $array = [0 => 'a', 1 => 'b', 2 => 'c'];

            // Act
            $result = Helpers::isIndexedArray($array);

            // Assert
            expect($result)->toBeTrue();
        });

        test('identifies associative array returns false', function (): void {
            // Arrange
            $array = ['name' => 'John', 'email' => 'john@example.com'];

            // Act
            $result = Helpers::isIndexedArray($array);

            // Assert
            expect($result)->toBeFalse();
        });
    });

    describe('Regression Tests', function (): void {
        test('extracts keymap values not primary keys from model instance', function (): void {
            // Arrange - Configure keymap to use 'id' column
            Models::enforceMorphKeyMap([
                User::class => 'id',
            ]);

            $user = User::query()->create(['name' => 'Test User']);

            // Act
            $result = Helpers::extractModelAndKeys($user);

            // Assert
            expect($result)->not->toBeNull();
            expect($result[1])->toEqual([$user->id]); // Should use keymap value (id column)
        });

        test('extracts keymap values from collection of models with custom keymap', function (): void {
            // Arrange - Configure keymap
            Models::enforceMorphKeyMap([
                User::class => 'id',
            ]);

            $user1 = User::query()->create(['name' => 'User 1']);
            $user2 = User::query()->create(['name' => 'User 2']);
            $collection = new Collection([$user1, $user2]);

            // Act
            $result = Helpers::extractModelAndKeys($collection);

            // Assert
            expect($result)->not->toBeNull();
            expect($result[1]->all())->toEqual([$user1->id, $user2->id]); // Should use keymap values
        });

        test('uses keymap values for mixed models preventing assignment to wrong user', function (): void {
            // Arrange - This tests the bug where all assignments went to user ID 1
            Models::enforceMorphKeyMap([
                User::class => 'id',
            ]);

            $user1 = User::query()->create(['name' => 'Alice']);
            $user2 = User::query()->create(['name' => 'Bob']);
            $user3 = User::query()->create(['name' => 'Charlie']);

            // Act - Extract keys from mixed collection
            $result1 = Helpers::extractModelAndKeys($user1);
            $result2 = Helpers::extractModelAndKeys($user2);
            $result3 = Helpers::extractModelAndKeys($user3);

            // Assert - Each user gets their own keymap value, not all getting ID 1
            expect($result1[1])->toEqual([$user1->id]);
            expect($result2[1])->toEqual([$user2->id]);
            expect($result3[1])->toEqual([$user3->id]);
        });
    });

    describe('Edge Cases', function (): void {
        test('returns null when extracting from non-model with keys', function (): void {
            // Arrange
            $nonModel = new stdClass();
            $keys = [1, 2, 3];

            // Act
            $result = Helpers::extractModelAndKeys($nonModel, $keys);

            // Assert
            expect($result)->toBeNull();
        });

        test('returns null when extracting from empty collection', function (): void {
            // Arrange
            $collection = new Collection([]);

            // Act
            $result = Helpers::extractModelAndKeys($collection);

            // Assert
            expect($result)->toBeNull();
        });

        test('returns null when extracting from string input', function (): void {
            // Arrange
            $unhandledInput = 'string-input';

            // Act
            $result = Helpers::extractModelAndKeys($unhandledInput);

            // Assert
            expect($result)->toBeNull();
        });

        test('returns null when extracting from integer input', function (): void {
            // Arrange
            $unhandledInput = 123;

            // Act
            $result = Helpers::extractModelAndKeys($unhandledInput);

            // Assert
            expect($result)->toBeNull();
        });

        test('returns null when extracting from array input', function (): void {
            // Arrange
            $unhandledInput = [1, 2, 3];

            // Act
            $result = Helpers::extractModelAndKeys($unhandledInput);

            // Assert
            expect($result)->toBeNull();
        });

        test('returns false for non-array string input to isIndexedArray', function (): void {
            // Arrange
            $nonArray = 'not-an-array';

            // Act
            $result = Helpers::isIndexedArray($nonArray);

            // Assert
            expect($result)->toBeFalse();
        });

        test('returns false for non-array integer input to isIndexedArray', function (): void {
            // Arrange
            $nonArray = 42;

            // Act
            $result = Helpers::isIndexedArray($nonArray);

            // Assert
            expect($result)->toBeFalse();
        });

        test('returns false for non-array object input to isIndexedArray', function (): void {
            // Arrange
            $nonArray = new stdClass();

            // Act
            $result = Helpers::isIndexedArray($nonArray);

            // Assert
            expect($result)->toBeFalse();
        });

        test('returns false for null input to isIndexedArray', function (): void {
            // Arrange
            $nonArray = null;

            // Act
            $result = Helpers::isIndexedArray($nonArray);

            // Assert
            expect($result)->toBeFalse();
        });

        test('identifies indexed array with non-sequential numeric keys', function (): void {
            // Arrange
            $array = [0 => 'a', 5 => 'b', 10 => 'c'];

            // Act
            $result = Helpers::isIndexedArray($array);

            // Assert
            expect($result)->toBeTrue();
        });
    });
});
