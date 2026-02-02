<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\VariableKeys\Enums\MorphType;
use Cline\VariableKeys\Enums\PrimaryKeyType;

describe('MorphType', function (): void {
    test('has correct case names', function (): void {
        $cases = MorphType::cases();

        expect($cases)->toHaveCount(4)
            ->and($cases[0]->name)->toBe('String')
            ->and($cases[1]->name)->toBe('Numeric')
            ->and($cases[2]->name)->toBe('UUID')
            ->and($cases[3]->name)->toBe('ULID');
    });

    test('has correct string values for each case', function (): void {
        expect(MorphType::String->value)->toBe('string')
            ->and(MorphType::Numeric->value)->toBe('numeric')
            ->and(MorphType::UUID->value)->toBe('uuid')
            ->and(MorphType::ULID->value)->toBe('ulid');
    });

    test('can retrieve case by value using tryFrom', function (): void {
        expect(MorphType::tryFrom('string'))->toBe(MorphType::String)
            ->and(MorphType::tryFrom('numeric'))->toBe(MorphType::Numeric)
            ->and(MorphType::tryFrom('uuid'))->toBe(MorphType::UUID)
            ->and(MorphType::tryFrom('ulid'))->toBe(MorphType::ULID);
    });

    test('returns null for invalid value with tryFrom', function (): void {
        expect(MorphType::tryFrom('invalid'))->toBeNull()
            ->and(MorphType::tryFrom(''))->toBeNull()
            ->and(MorphType::tryFrom('Morph'))->toBeNull()
            ->and(MorphType::tryFrom('Morph'))->toBeNull();
    });

    test('can retrieve case by value using from', function (): void {
        expect(MorphType::from('string'))->toBe(MorphType::String)
            ->and(MorphType::from('numeric'))->toBe(MorphType::Numeric)
            ->and(MorphType::from('uuid'))->toBe(MorphType::UUID)
            ->and(MorphType::from('ulid'))->toBe(MorphType::ULID);
    });

    test('throws exception for invalid value with from', function (): void {
        MorphType::from('invalid');
    })->throws(ValueError::class);

    test('can be compared using strict equality', function (): void {
        expect(MorphType::String === MorphType::String)->toBeTrue()
            ->and(MorphType::String === MorphType::Numeric)->toBeFalse()
            ->and(MorphType::Numeric === MorphType::Numeric)->toBeTrue()
            ->and(MorphType::UUID === MorphType::UUID)->toBeTrue()
            ->and(MorphType::ULID === MorphType::ULID)->toBeTrue();
    });

    test('can be used in match expressions', function (): void {
        $result = match (MorphType::String) {
            MorphType::String => 'auto-detect',
            MorphType::Numeric => 'integer',
            MorphType::UUID => 'uuid',
            MorphType::ULID => 'ulid',
        };

        expect($result)->toBe('auto-detect');
    });

    test('returns all cases in expected order', function (): void {
        $cases = MorphType::cases();
        $names = array_map(fn (MorphType $case) => $case->name, $cases);
        $values = array_map(fn (MorphType $case) => $case->value, $cases);

        expect($names)->toBe(['String', 'Numeric', 'UUID', 'ULID'])
            ->and($values)->toBe(['string', 'numeric', 'uuid', 'ulid']);
    });

    test('is backed by string type', function (): void {
        $reflection = new ReflectionEnum(MorphType::class);

        expect($reflection->isBacked())->toBeTrue()
            ->and($reflection->getBackingType()->getName())->toBe('string');
    });

    test('can be serialized to json', function (): void {
        $json = json_encode(['type' => MorphType::String]);

        expect($json)->toBe('{"type":"string"}');
    });

    test('handles case sensitivity correctly', function (): void {
        // Case names are case-sensitive
        expect(MorphType::tryFrom('string'))->toBe(MorphType::String)
            ->and(MorphType::tryFrom('Morph'))->toBeNull()
            ->and(MorphType::tryFrom('Morph'))->toBeNull();
    });
});

describe('PrimaryKeyType', function (): void {
    test('has correct case names', function (): void {
        $cases = PrimaryKeyType::cases();

        expect($cases)->toHaveCount(3)
            ->and($cases[0]->name)->toBe('ID')
            ->and($cases[1]->name)->toBe('ULID')
            ->and($cases[2]->name)->toBe('UUID');
    });

    test('has correct string values for each case', function (): void {
        expect(PrimaryKeyType::ID->value)->toBe('id')
            ->and(PrimaryKeyType::ULID->value)->toBe('ulid')
            ->and(PrimaryKeyType::UUID->value)->toBe('uuid');
    });

    test('can retrieve case by value using tryFrom', function (): void {
        expect(PrimaryKeyType::tryFrom('id'))->toBe(PrimaryKeyType::ID)
            ->and(PrimaryKeyType::tryFrom('ulid'))->toBe(PrimaryKeyType::ULID)
            ->and(PrimaryKeyType::tryFrom('uuid'))->toBe(PrimaryKeyType::UUID);
    });

    test('returns null for invalid value with tryFrom', function (): void {
        expect(PrimaryKeyType::tryFrom('invalid'))->toBeNull()
            ->and(PrimaryKeyType::tryFrom(''))->toBeNull()
            ->and(PrimaryKeyType::tryFrom('ID'))->toBeNull()
            ->and(PrimaryKeyType::tryFrom('Id'))->toBeNull()
            ->and(PrimaryKeyType::tryFrom('integer'))->toBeNull();
    });

    test('can retrieve case by value using from', function (): void {
        expect(PrimaryKeyType::from('id'))->toBe(PrimaryKeyType::ID)
            ->and(PrimaryKeyType::from('ulid'))->toBe(PrimaryKeyType::ULID)
            ->and(PrimaryKeyType::from('uuid'))->toBe(PrimaryKeyType::UUID);
    });

    test('throws exception for invalid value with from', function (): void {
        PrimaryKeyType::from('invalid');
    })->throws(ValueError::class);

    test('can be compared using strict equality', function (): void {
        expect(PrimaryKeyType::ID === PrimaryKeyType::ID)->toBeTrue()
            ->and(PrimaryKeyType::ID === PrimaryKeyType::ULID)->toBeFalse()
            ->and(PrimaryKeyType::ULID === PrimaryKeyType::ULID)->toBeTrue()
            ->and(PrimaryKeyType::UUID === PrimaryKeyType::UUID)->toBeTrue();
    });

    test('can be used in match expressions', function (): void {
        $result = match (PrimaryKeyType::ID) {
            PrimaryKeyType::ID => 'integer',
            PrimaryKeyType::ULID => 'ulid',
            PrimaryKeyType::UUID => 'uuid',
        };

        expect($result)->toBe('integer');
    });

    test('returns all cases in expected order', function (): void {
        $cases = PrimaryKeyType::cases();
        $names = array_map(fn (PrimaryKeyType $case) => $case->name, $cases);
        $values = array_map(fn (PrimaryKeyType $case) => $case->value, $cases);

        expect($names)->toBe(['ID', 'ULID', 'UUID'])
            ->and($values)->toBe(['id', 'ulid', 'uuid']);
    });

    test('is backed by string type', function (): void {
        $reflection = new ReflectionEnum(PrimaryKeyType::class);

        expect($reflection->isBacked())->toBeTrue()
            ->and($reflection->getBackingType()->getName())->toBe('string');
    });

    test('can be serialized to json', function (): void {
        $json = json_encode(['type' => PrimaryKeyType::ID]);

        expect($json)->toBe('{"type":"id"}');
    });

    test('handles case sensitivity correctly', function (): void {
        // Case values are case-sensitive
        expect(PrimaryKeyType::tryFrom('id'))->toBe(PrimaryKeyType::ID)
            ->and(PrimaryKeyType::tryFrom('ID'))->toBeNull()
            ->and(PrimaryKeyType::tryFrom('Id'))->toBeNull();
    });

    test('values are distinct from case names', function (): void {
        // Case name is 'ID' but value is 'id'
        expect(PrimaryKeyType::ID->name)->toBe('ID')
            ->and(PrimaryKeyType::ID->value)->toBe('id')
            ->and(PrimaryKeyType::ID->name)->not->toBe(PrimaryKeyType::ID->value);
    });
});

describe('Enum interoperability', function (): void {
    test('MorphType and PrimaryKeyType are separate enums', function (): void {
        expect(MorphType::class)->not->toBe(PrimaryKeyType::class)
            ->and(MorphType::cases())->toHaveCount(4)
            ->and(PrimaryKeyType::cases())->toHaveCount(3);
    });

    test('enums with same values are distinct types', function (): void {
        // Both have 'ulid' and 'uuid' with same values but are different enum types
        expect(MorphType::UUID->value)->toBe('uuid')
            ->and(PrimaryKeyType::UUID->value)->toBe('uuid')
            ->and(MorphType::UUID)->not->toBe(PrimaryKeyType::UUID);

        expect(MorphType::ULID->value)->toBe('ulid')
            ->and(PrimaryKeyType::ULID->value)->toBe('ulid')
            ->and(MorphType::ULID)->not->toBe(PrimaryKeyType::ULID);
    });

    test('can use both enums together', function (): void {
        $config = [
            'primary_key' => PrimaryKeyType::ULID,
            'morph_type' => MorphType::ULID,
        ];

        expect($config['primary_key'])->toBe(PrimaryKeyType::ULID)
            ->and($config['morph_type'])->toBe(MorphType::ULID)
            ->and($config['primary_key']->value)->toBe('ulid')
            ->and($config['morph_type']->value)->toBe('ulid');
    });
});

describe('Enum edge cases', function (): void {
    test('handles null value checks gracefully', function (): void {
        expect(MorphType::tryFrom(''))->toBeNull()
            ->and(PrimaryKeyType::tryFrom(''))->toBeNull();
    });

    test('throws ValueError for null with from method', function (): void {
        PrimaryKeyType::from(null);
    })->throws(TypeError::class);

    test('can iterate over all cases', function (): void {
        $morphTypes = [];

        foreach (MorphType::cases() as $case) {
            $morphTypes[$case->name] = $case->value;
        }

        expect($morphTypes)->toBe([
            'String' => 'string',
            'Numeric' => 'numeric',
            'UUID' => 'uuid',
            'ULID' => 'ulid',
        ]);
    });

    test('maintains enum instances across calls', function (): void {
        $morph1 = MorphType::String;
        $morph2 = MorphType::String;

        expect($morph1)->toBe($morph2)
            ->and($morph1 === $morph2)->toBeTrue();
    });

    test('works correctly in array keys', function (): void {
        $mapping = [
            PrimaryKeyType::ID->value => 'bigIncrements',
            PrimaryKeyType::ULID->value => 'ulid',
            PrimaryKeyType::UUID->value => 'uuid',
        ];

        expect($mapping['id'])->toBe('bigIncrements')
            ->and($mapping['ulid'])->toBe('ulid')
            ->and($mapping['uuid'])->toBe('uuid');
    });

    test('can filter cases by condition', function (): void {
        $nonIdTypes = array_filter(
            PrimaryKeyType::cases(),
            fn (PrimaryKeyType $case): bool => $case !== PrimaryKeyType::ID,
        );

        expect($nonIdTypes)->toHaveCount(2)
            ->and(array_values($nonIdTypes)[0])->toBe(PrimaryKeyType::ULID)
            ->and(array_values($nonIdTypes)[1])->toBe(PrimaryKeyType::UUID);
    });
});
