<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Unit\Support;

use Cline\Warden\Enums\PrimaryKeyType;
use Cline\Warden\Support\PrimaryKeyGenerator;
use Cline\Warden\Support\PrimaryKeyValue;

use function array_column;
use function array_unique;
use function config;
use function describe;
use function expect;
use function mb_strlen;
use function mb_strtolower;
use function range;
use function test;

describe('PrimaryKeyGenerator', function (): void {
    describe('generate()', function (): void {
        describe('Happy Path: Primary key generation', function (): void {
            test('generates ULID when configured', function (): void {
                // Arrange
                config(['warden.primary_key_type' => PrimaryKeyType::ULID->value]);

                // Act
                $result = PrimaryKeyGenerator::generate();

                // Assert
                expect($result)->toBeInstanceOf(PrimaryKeyValue::class)
                    ->and($result->type)->toBe(PrimaryKeyType::ULID)
                    ->and($result->value)->toBeString()
                    ->and(mb_strlen((string) $result->value))->toBe(26)
                    ->and($result->requiresValue())->toBeTrue()
                    ->and($result->isAutoIncrementing())->toBeFalse();
            });

            test('generates UUID when configured', function (): void {
                // Arrange
                config(['warden.primary_key_type' => PrimaryKeyType::UUID->value]);

                // Act
                $result = PrimaryKeyGenerator::generate();

                // Assert
                expect($result)->toBeInstanceOf(PrimaryKeyValue::class)
                    ->and($result->type)->toBe(PrimaryKeyType::UUID)
                    ->and($result->value)->toBeString()
                    ->and(mb_strlen((string) $result->value))->toBe(36)
                    ->and($result->requiresValue())->toBeTrue()
                    ->and($result->isAutoIncrementing())->toBeFalse();
            });

            test('generates null for auto-increment ID when configured', function (): void {
                // Arrange
                config(['warden.primary_key_type' => PrimaryKeyType::ID->value]);

                // Act
                $result = PrimaryKeyGenerator::generate();

                // Assert
                expect($result)->toBeInstanceOf(PrimaryKeyValue::class)
                    ->and($result->type)->toBe(PrimaryKeyType::ID)
                    ->and($result->value)->toBeNull()
                    ->and($result->requiresValue())->toBeFalse()
                    ->and($result->isAutoIncrementing())->toBeTrue();
            });
        });

        describe('Edge Case: Invalid configuration', function (): void {
            test('defaults to ID type when config is invalid', function (): void {
                // Arrange
                config(['warden.primary_key_type' => 'invalid_type']);

                // Act
                $result = PrimaryKeyGenerator::generate();

                // Assert
                expect($result)->toBeInstanceOf(PrimaryKeyValue::class)
                    ->and($result->type)->toBe(PrimaryKeyType::ID)
                    ->and($result->value)->toBeNull()
                    ->and($result->requiresValue())->toBeFalse();
            });

            test('defaults to ID type when config is empty string', function (): void {
                // Arrange
                config(['warden.primary_key_type' => '']);

                // Act
                $result = PrimaryKeyGenerator::generate();

                // Assert
                expect($result)->toBeInstanceOf(PrimaryKeyValue::class)
                    ->and($result->type)->toBe(PrimaryKeyType::ID)
                    ->and($result->value)->toBeNull();
            });

            test('defaults to ID type when config is unknown string', function (): void {
                // Arrange
                config(['warden.primary_key_type' => 'unknown']);

                // Act
                $result = PrimaryKeyGenerator::generate();

                // Assert
                expect($result)->toBeInstanceOf(PrimaryKeyValue::class)
                    ->and($result->type)->toBe(PrimaryKeyType::ID)
                    ->and($result->value)->toBeNull();
            });
        });

        describe('Edge Case: Value format validation', function (): void {
            test('generates lowercase ULID', function (): void {
                // Arrange
                config(['warden.primary_key_type' => PrimaryKeyType::ULID->value]);

                // Act
                $result = PrimaryKeyGenerator::generate();

                // Assert
                expect($result->value)->toBe(mb_strtolower((string) $result->value));
            });

            test('generates lowercase UUID', function (): void {
                // Arrange
                config(['warden.primary_key_type' => PrimaryKeyType::UUID->value]);

                // Act
                $result = PrimaryKeyGenerator::generate();

                // Assert
                expect($result->value)->toBe(mb_strtolower((string) $result->value));
            });

            test('generates unique ULIDs on consecutive calls', function (): void {
                // Arrange
                config(['warden.primary_key_type' => PrimaryKeyType::ULID->value]);

                // Act
                $result1 = PrimaryKeyGenerator::generate();
                $result2 = PrimaryKeyGenerator::generate();

                // Assert
                expect($result1->value)->not->toBe($result2->value);
            });

            test('generates unique UUIDs on consecutive calls', function (): void {
                // Arrange
                config(['warden.primary_key_type' => PrimaryKeyType::UUID->value]);

                // Act
                $result1 = PrimaryKeyGenerator::generate();
                $result2 = PrimaryKeyGenerator::generate();

                // Assert
                expect($result1->value)->not->toBe($result2->value);
            });
        });
    });

    describe('enrichPivotData()', function (): void {
        describe('Happy Path: Pivot data enrichment', function (): void {
            test('adds ULID to pivot data', function (): void {
                // Arrange
                config(['warden.primary_key_type' => PrimaryKeyType::ULID->value]);
                $data = ['role_id' => 1, 'ability_id' => 2];

                // Act
                $result = PrimaryKeyGenerator::enrichPivotData($data);

                // Assert
                expect($result)->toHaveKey('id')
                    ->and($result['id'])->toBeString()
                    ->and(mb_strlen((string) $result['id']))->toBe(26)
                    ->and($result['role_id'])->toBe(1)
                    ->and($result['ability_id'])->toBe(2);
            });

            test('adds UUID to pivot data', function (): void {
                // Arrange
                config(['warden.primary_key_type' => PrimaryKeyType::UUID->value]);
                $data = ['role_id' => 1, 'ability_id' => 2];

                // Act
                $result = PrimaryKeyGenerator::enrichPivotData($data);

                // Assert
                expect($result)->toHaveKey('id')
                    ->and($result['id'])->toBeString()
                    ->and(mb_strlen((string) $result['id']))->toBe(36)
                    ->and($result['role_id'])->toBe(1)
                    ->and($result['ability_id'])->toBe(2);
            });

            test('does not add id for auto-increment type', function (): void {
                // Arrange
                config(['warden.primary_key_type' => PrimaryKeyType::ID->value]);
                $data = ['role_id' => 1, 'ability_id' => 2];

                // Act
                $result = PrimaryKeyGenerator::enrichPivotData($data);

                // Assert
                expect($result)->not->toHaveKey('id')
                    ->and($result['role_id'])->toBe(1)
                    ->and($result['ability_id'])->toBe(2);
            });
        });

        describe('Edge Case: Empty and existing data', function (): void {
            test('enriches empty pivot data with ULID', function (): void {
                // Arrange
                config(['warden.primary_key_type' => PrimaryKeyType::ULID->value]);
                $data = [];

                // Act
                $result = PrimaryKeyGenerator::enrichPivotData($data);

                // Assert
                expect($result)->toHaveKey('id')
                    ->and($result['id'])->toBeString()
                    ->and(mb_strlen((string) $result['id']))->toBe(26);
            });

            test('preserves existing id when using auto-increment', function (): void {
                // Arrange
                config(['warden.primary_key_type' => PrimaryKeyType::ID->value]);
                $data = ['id' => 999, 'role_id' => 1];

                // Act
                $result = PrimaryKeyGenerator::enrichPivotData($data);

                // Assert
                expect($result['id'])->toBe(999)
                    ->and($result['role_id'])->toBe(1);
            });

            test('overwrites existing id when using ULID', function (): void {
                // Arrange
                config(['warden.primary_key_type' => PrimaryKeyType::ULID->value]);
                $data = ['id' => 999, 'role_id' => 1];

                // Act
                $result = PrimaryKeyGenerator::enrichPivotData($data);

                // Assert
                expect($result['id'])->not->toBe(999)
                    ->and($result['id'])->toBeString()
                    ->and(mb_strlen((string) $result['id']))->toBe(26);
            });

            test('preserves all other pivot attributes', function (): void {
                // Arrange
                config(['warden.primary_key_type' => PrimaryKeyType::ULID->value]);
                $data = [
                    'role_id' => 1,
                    'ability_id' => 2,
                    'created_at' => '2024-01-01',
                    'custom_field' => 'value',
                ];

                // Act
                $result = PrimaryKeyGenerator::enrichPivotData($data);

                // Assert
                expect($result)->toHaveKey('id')
                    ->and($result['role_id'])->toBe(1)
                    ->and($result['ability_id'])->toBe(2)
                    ->and($result['created_at'])->toBe('2024-01-01')
                    ->and($result['custom_field'])->toBe('value');
            });
        });
    });

    describe('enrichPivotDataForIds()', function (): void {
        describe('Happy Path: Multiple ID enrichment', function (): void {
            test('enriches data for multiple IDs with ULIDs', function (): void {
                // Arrange
                config(['warden.primary_key_type' => PrimaryKeyType::ULID->value]);
                $ids = [1, 2, 3];
                $data = ['role_id' => 5];

                // Act
                $result = PrimaryKeyGenerator::enrichPivotDataForIds($ids, $data);

                // Assert
                expect($result)->toHaveCount(3)
                    ->and($result[1])->toHaveKey('id')
                    ->and($result[2])->toHaveKey('id')
                    ->and($result[3])->toHaveKey('id')
                    ->and($result[1]['id'])->toBeString()
                    ->and($result[2]['id'])->toBeString()
                    ->and($result[3]['id'])->toBeString()
                    ->and($result[1]['role_id'])->toBe(5)
                    ->and($result[2]['role_id'])->toBe(5)
                    ->and($result[3]['role_id'])->toBe(5);
            });

            test('generates unique IDs for each entry with ULIDs', function (): void {
                // Arrange
                config(['warden.primary_key_type' => PrimaryKeyType::ULID->value]);
                $ids = [1, 2, 3];
                $data = [];

                // Act
                $result = PrimaryKeyGenerator::enrichPivotDataForIds($ids, $data);

                // Assert
                expect($result[1]['id'])->not->toBe($result[2]['id'])
                    ->and($result[2]['id'])->not->toBe($result[3]['id'])
                    ->and($result[1]['id'])->not->toBe($result[3]['id']);
            });

            test('does not add IDs when using auto-increment', function (): void {
                // Arrange
                config(['warden.primary_key_type' => PrimaryKeyType::ID->value]);
                $ids = [1, 2];
                $data = ['role_id' => 5];

                // Act
                $result = PrimaryKeyGenerator::enrichPivotDataForIds($ids, $data);

                // Assert
                expect($result)->toHaveCount(2)
                    ->and($result[1])->not->toHaveKey('id')
                    ->and($result[2])->not->toHaveKey('id')
                    ->and($result[1]['role_id'])->toBe(5)
                    ->and($result[2]['role_id'])->toBe(5);
            });
        });

        describe('Edge Case: Empty and single ID', function (): void {
            test('handles empty ID array', function (): void {
                // Arrange
                config(['warden.primary_key_type' => PrimaryKeyType::ULID->value]);
                $ids = [];
                $data = ['role_id' => 5];

                // Act
                $result = PrimaryKeyGenerator::enrichPivotDataForIds($ids, $data);

                // Assert
                expect($result)->toBeEmpty();
            });

            test('handles single ID', function (): void {
                // Arrange
                config(['warden.primary_key_type' => PrimaryKeyType::UUID->value]);
                $ids = [1];
                $data = ['role_id' => 5];

                // Act
                $result = PrimaryKeyGenerator::enrichPivotDataForIds($ids, $data);

                // Assert
                expect($result)->toHaveCount(1)
                    ->and($result[1])->toHaveKey('id')
                    ->and($result[1]['id'])->toBeString()
                    ->and(mb_strlen((string) $result[1]['id']))->toBe(36);
            });

            test('preserves ID keys in result', function (): void {
                // Arrange
                config(['warden.primary_key_type' => PrimaryKeyType::ULID->value]);
                $ids = [10, 20, 30];
                $data = ['role_id' => 5];

                // Act
                $result = PrimaryKeyGenerator::enrichPivotDataForIds($ids, $data);

                // Assert
                expect($result)->toHaveKeys([10, 20, 30])
                    ->and($result[10])->toBeArray()
                    ->and($result[20])->toBeArray()
                    ->and($result[30])->toBeArray();
            });

            test('handles large number of IDs', function (): void {
                // Arrange
                config(['warden.primary_key_type' => PrimaryKeyType::ULID->value]);
                $ids = range(1, 100);
                $data = ['role_id' => 5];

                // Act
                $result = PrimaryKeyGenerator::enrichPivotDataForIds($ids, $data);

                // Assert
                expect($result)->toHaveCount(100);

                // Verify all IDs are unique
                $generatedIds = array_column($result, 'id');
                expect($generatedIds)->toHaveCount(100)
                    ->and(array_unique($generatedIds))->toHaveCount(100);
            });
        });
    });
});
