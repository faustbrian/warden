<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Constraints\ColumnConstraint;
use Cline\Warden\Constraints\Constrainer;
use Cline\Warden\Constraints\ValueConstraint;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Models\Account;
use Tests\Fixtures\Models\User;

describe('Constrainer', function (): void {
    describe('Happy Paths', function (): void {
        test('value constraint from data reconstitutes instance', function (): void {
            // Arrange
            $data = [
                'column' => 'status',
                'operator' => '=',
                'value' => 'published',
                'logicalOperator' => 'and',
            ];

            // Act
            $constraint = ValueConstraint::fromData($data);

            // Assert
            expect($constraint)->toBeInstanceOf(ValueConstraint::class);
            expect($constraint)->toBeInstanceOf(Constrainer::class);
        });

        test('value constraint from data preserves parameters', function (): void {
            // Arrange
            $entity = new Account(['status' => 'active']);
            $data = [
                'column' => 'status',
                'operator' => '=',
                'value' => 'active',
                'logicalOperator' => 'or',
            ];

            // Act
            $constraint = ValueConstraint::fromData($data);

            // Assert
            expect($constraint->check($entity))->toBeTrue();
            expect($constraint->isOr())->toBeTrue();
            expect($constraint->isAnd())->toBeFalse();
        });

        test('value constraint from data defaults to and operator', function (): void {
            // Arrange
            $data = [
                'column' => 'active',
                'operator' => '=',
                'value' => true,
                'logicalOperator' => null,
            ];

            // Act
            $constraint = ValueConstraint::fromData($data);

            // Assert
            expect($constraint->isAnd())->toBeTrue();
            expect($constraint->isOr())->toBeFalse();
        });

        test('column constraint from data reconstitutes instance', function (): void {
            // Arrange
            $data = [
                'a' => 'user_id',
                'operator' => '=',
                'b' => 'id',
                'logicalOperator' => 'and',
            ];

            // Act
            $constraint = ColumnConstraint::fromData($data);

            // Assert
            expect($constraint)->toBeInstanceOf(ColumnConstraint::class);
            expect($constraint)->toBeInstanceOf(Constrainer::class);
        });

        test('column constraint from data preserves parameters', function (): void {
            // Arrange
            $entity = new User(['id' => 5, 'age' => 30]);
            $authority = new User(['id' => 5, 'age' => 30]);
            $data = [
                'a' => 'id',
                'operator' => '=',
                'b' => 'id',
                'logicalOperator' => 'or',
            ];

            // Act
            $constraint = ColumnConstraint::fromData($data);

            // Assert
            expect($constraint->check($entity, $authority))->toBeTrue();
            expect($constraint->isOr())->toBeTrue();
            expect($constraint->isAnd())->toBeFalse();
        });

        test('value constraint data returns correct structure', function (): void {
            // Arrange
            $constraint = new ValueConstraint('status', '=', 'published');

            // Act
            $data = $constraint->data();

            // Assert
            expect($data)->toBeArray();
            expect($data)->toHaveKey('class');
            expect($data)->toHaveKey('params');
            expect($data['class'])->toBe(ValueConstraint::class);
        });

        test('value constraint data includes all parameters', function (): void {
            // Arrange
            $constraint = new ValueConstraint('active', '!=', false);
            $constraint->logicalOperator('or');

            // Act
            $data = $constraint->data();

            // Assert
            expect($data['params'])->toHaveKey('column');
            expect($data['params'])->toHaveKey('operator');
            expect($data['params'])->toHaveKey('value');
            expect($data['params'])->toHaveKey('logicalOperator');
            expect($data['params']['column'])->toBe('active');
            expect($data['params']['operator'])->toBe('!=');
            expect($data['params']['value'])->toBeFalse();
            expect($data['params']['logicalOperator'])->toBe('or');
        });

        test('column constraint data returns correct structure', function (): void {
            // Arrange
            $constraint = new ColumnConstraint('user_id', '=', 'id');

            // Act
            $data = $constraint->data();

            // Assert
            expect($data)->toBeArray();
            expect($data)->toHaveKey('class');
            expect($data)->toHaveKey('params');
            expect($data['class'])->toBe(ColumnConstraint::class);
        });

        test('column constraint data includes all parameters', function (): void {
            // Arrange
            $constraint = new ColumnConstraint('age', '>', 'min_age');
            $constraint->logicalOperator('and');

            // Act
            $data = $constraint->data();

            // Assert
            expect($data['params'])->toHaveKey('a');
            expect($data['params'])->toHaveKey('operator');
            expect($data['params'])->toHaveKey('b');
            expect($data['params'])->toHaveKey('logicalOperator');
            expect($data['params']['a'])->toBe('age');
            expect($data['params']['operator'])->toBe('>');
            expect($data['params']['b'])->toBe('min_age');
            expect($data['params']['logicalOperator'])->toBe('and');
        });

        test('value constraint check evaluates entity', function (): void {
            // Arrange
            $publishedPost = new Account(['status' => 'published']);
            $draftPost = new Account(['status' => 'draft']);
            $constraint = new ValueConstraint('status', '=', 'published');

            // Act & Assert
            expect($constraint->check($publishedPost))->toBeTrue();
            expect($constraint->check($draftPost))->toBeFalse();
        });

        test('value constraint check works without authority', function (): void {
            // Arrange
            $activeAccount = new Account(['active' => true]);
            $constraint = new ValueConstraint('active', '=', true);

            // Act
            $result = $constraint->check($activeAccount);

            // Assert
            expect($result)->toBeTrue();
        });

        test('column constraint check compares entity and authority', function (): void {
            // Arrange
            $entity = new User(['user_id' => 42]);
            $authority = new User(['id' => 42]);
            $otherAuthority = new User(['id' => 99]);
            $constraint = new ColumnConstraint('user_id', '=', 'id');

            // Act & Assert
            expect($constraint->check($entity, $authority))->toBeTrue();
            expect($constraint->check($entity, $otherAuthority))->toBeFalse();
        });

        test('logical operator getter returns current value', function (): void {
            // Arrange
            $constraint = new ValueConstraint('status', '=', 'active');

            // Act
            $operator = $constraint->logicalOperator();

            // Assert
            expect($operator)->toBe('and');
        });

        test('logical operator setter enables fluent interface', function (): void {
            // Arrange
            $constraint = new ValueConstraint('status', '=', 'active');

            // Act
            $result = $constraint->logicalOperator('or');

            // Assert
            expect($result)->toBe($constraint);
            expect($constraint->logicalOperator())->toBe('or');
        });

        test('logical operator accepts and', function (): void {
            // Arrange
            $constraint = new ValueConstraint('status', '=', 'active');
            $constraint->logicalOperator('or');

            // Act
            $constraint->logicalOperator('and');

            // Assert
            expect($constraint->logicalOperator())->toBe('and');
        });

        test('logical operator accepts or', function (): void {
            // Arrange
            $constraint = new ValueConstraint('status', '=', 'active');

            // Act
            $constraint->logicalOperator('or');

            // Assert
            expect($constraint->logicalOperator())->toBe('or');
        });

        test('logical operator works through interface type', function (): void {
            // Arrange
            $constraint = new ValueConstraint('status', '=', 'active');

            // Helper function to force interface type usage
            $getOperator = fn (Constrainer $c): string => $c->logicalOperator();

            // Act & Assert
            expect($getOperator($constraint))->toBe('and');
        });

        test('is and returns true for and operator', function (): void {
            // Arrange
            $constraint = new ValueConstraint('status', '=', 'active');
            $constraint->logicalOperator('and');

            // Act
            $result = $constraint->isAnd();

            // Assert
            expect($result)->toBeTrue();
            expect($constraint->isOr())->toBeFalse();
        });

        test('is and returns false for or operator', function (): void {
            // Arrange
            $constraint = new ValueConstraint('status', '=', 'active');
            $constraint->logicalOperator('or');

            // Act
            $result = $constraint->isAnd();

            // Assert
            expect($result)->toBeFalse();
        });

        test('is or returns true for or operator', function (): void {
            // Arrange
            $constraint = new ValueConstraint('status', '=', 'active');
            $constraint->logicalOperator('or');

            // Act
            $result = $constraint->isOr();

            // Assert
            expect($result)->toBeTrue();
            expect($constraint->isAnd())->toBeFalse();
        });

        test('is or returns false for and operator', function (): void {
            // Arrange
            $constraint = new ValueConstraint('status', '=', 'active');
            $constraint->logicalOperator('and');

            // Act
            $result = $constraint->isOr();

            // Assert
            expect($result)->toBeFalse();
        });

        test('value constraint equals returns true for identical', function (): void {
            // Arrange
            $constraint1 = new ValueConstraint('status', '=', 'published');
            $constraint1->logicalOperator('and');

            $constraint2 = new ValueConstraint('status', '=', 'published');
            $constraint2->logicalOperator('and');

            // Act
            $result = $constraint1->equals($constraint2);

            // Assert
            expect($result)->toBeTrue();
        });

        test('value constraint equals returns false for different columns', function (): void {
            // Arrange
            $constraint1 = new ValueConstraint('status', '=', 'published');
            $constraint2 = new ValueConstraint('state', '=', 'published');

            // Act
            $result = $constraint1->equals($constraint2);

            // Assert
            expect($result)->toBeFalse();
        });

        test('value constraint equals returns false for different operators', function (): void {
            // Arrange
            $constraint1 = new ValueConstraint('age', '>', 18);
            $constraint2 = new ValueConstraint('age', '<', 18);

            // Act
            $result = $constraint1->equals($constraint2);

            // Assert
            expect($result)->toBeFalse();
        });

        test('value constraint equals returns false for different values', function (): void {
            // Arrange
            $constraint1 = new ValueConstraint('status', '=', 'published');
            $constraint2 = new ValueConstraint('status', '=', 'draft');

            // Act
            $result = $constraint1->equals($constraint2);

            // Assert
            expect($result)->toBeFalse();
        });

        test('value constraint equals returns false for different logical operators', function (): void {
            // Arrange
            $constraint1 = new ValueConstraint('status', '=', 'published');
            $constraint1->logicalOperator('and');

            $constraint2 = new ValueConstraint('status', '=', 'published');
            $constraint2->logicalOperator('or');

            // Act
            $result = $constraint1->equals($constraint2);

            // Assert
            expect($result)->toBeFalse();
        });

        test('column constraint equals returns true for identical', function (): void {
            // Arrange
            $constraint1 = new ColumnConstraint('user_id', '=', 'id');
            $constraint1->logicalOperator('and');

            $constraint2 = new ColumnConstraint('user_id', '=', 'id');
            $constraint2->logicalOperator('and');

            // Act
            $result = $constraint1->equals($constraint2);

            // Assert
            expect($result)->toBeTrue();
        });

        test('column constraint equals returns false for different column a', function (): void {
            // Arrange
            $constraint1 = new ColumnConstraint('user_id', '=', 'id');
            $constraint2 = new ColumnConstraint('owner_id', '=', 'id');

            // Act
            $result = $constraint1->equals($constraint2);

            // Assert
            expect($result)->toBeFalse();
        });

        test('column constraint equals returns false for different column b', function (): void {
            // Arrange
            $constraint1 = new ColumnConstraint('age', '=', 'min_age');
            $constraint2 = new ColumnConstraint('age', '=', 'max_age');

            // Act
            $result = $constraint1->equals($constraint2);

            // Assert
            expect($result)->toBeFalse();
        });
    });

    describe('Sad Paths', function (): void {
        test('value constraint from data throws for invalid column', function (): void {
            // Arrange
            $data = [
                'column' => 123, // Invalid: should be string
                'operator' => '=',
                'value' => 'test',
            ];

            // Assert
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('Column and operator must be strings');

            // Act
            ValueConstraint::fromData($data);
        });

        test('value constraint from data throws for invalid operator', function (): void {
            // Arrange
            $data = [
                'column' => 'status',
                'operator' => 999, // Invalid: should be string
                'value' => 'test',
            ];

            // Assert
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('Column and operator must be strings');

            // Act
            ValueConstraint::fromData($data);
        });

        test('column constraint from data throws for invalid columns', function (): void {
            // Arrange
            $data = [
                'a' => 'user_id',
                'operator' => '=',
                'b' => 123, // Invalid: should be string
            ];

            // Assert
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('Columns and operator must be strings');

            // Act
            ColumnConstraint::fromData($data);
        });

        test('column constraint check returns false without authority', function (): void {
            // Arrange
            $entity = new User(['user_id' => 42]);
            $constraint = new ColumnConstraint('user_id', '=', 'id');

            // Act
            $result = $constraint->check($entity);

            // Assert
            expect($result)->toBeFalse();
        });

        test('logical operator throws for invalid operator', function (): void {
            // Arrange
            $constraint = new ValueConstraint('status', '=', 'active');

            // Assert
            $this->expectException(InvalidArgumentException::class);

            // Act
            $constraint->logicalOperator('xor');
        });
    });

    describe('Edge Cases', function (): void {
        test('check supports all operators', function (): void {
            // Arrange
            $entity = new User(['age' => 25]);

            // Act & Assert
            expect(
                new ValueConstraint('age', '=', 25)->check($entity),
            )->toBeTrue();
            expect(
                new ValueConstraint('age', '==', 25)->check($entity),
            )->toBeTrue();
            expect(
                new ValueConstraint('age', '!=', 30)->check($entity),
            )->toBeTrue();
            expect(
                new ValueConstraint('age', '>', 20)->check($entity),
            )->toBeTrue();
            expect(
                new ValueConstraint('age', '<', 30)->check($entity),
            )->toBeTrue();
            expect(
                new ValueConstraint('age', '>=', 25)->check($entity),
            )->toBeTrue();
            expect(
                new ValueConstraint('age', '<=', 25)->check($entity),
            )->toBeTrue();
        });

        test('is and defaults to true', function (): void {
            // Arrange
            $constraint = new ValueConstraint('status', '=', 'active');

            // Act & Assert
            expect($constraint->isAnd())->toBeTrue();
            expect($constraint->isOr())->toBeFalse();
        });

        test('equals returns false for different constraint types', function (): void {
            // Arrange
            $valueConstraint = new ValueConstraint('status', '=', 'active');
            $columnConstraint = new ColumnConstraint('status', '=', 'state');

            // Act
            $result1 = $valueConstraint->equals($columnConstraint);
            $result2 = $columnConstraint->equals($valueConstraint);

            // Assert
            expect($result1)->toBeFalse();
            expect($result2)->toBeFalse();
        });

        test('value constraint survives round trip serialization', function (): void {
            // Arrange
            $original = new ValueConstraint('active', '!=', false);
            $original->logicalOperator('or');

            $entity = new Account(['active' => true]);

            // Act
            $data = $original->data();
            $reconstituted = ValueConstraint::fromData($data['params']);

            // Assert
            expect($original->equals($reconstituted))->toBeTrue();
            expect($reconstituted->check($entity))->toBe($original->check($entity));
            expect($reconstituted->logicalOperator())->toBe($original->logicalOperator());
        });

        test('column constraint survives round trip serialization', function (): void {
            // Arrange
            $original = new ColumnConstraint('age', '>=', 'min_age');
            $original->logicalOperator('and');

            $entity = new User(['age' => 30]);
            $authority = new User(['min_age' => 25]);

            // Act
            $data = $original->data();
            $reconstituted = ColumnConstraint::fromData($data['params']);

            // Assert
            expect($original->equals($reconstituted))->toBeTrue();
            expect($reconstituted->check($entity, $authority))->toBe($original->check($entity, $authority));
            expect($reconstituted->logicalOperator())->toBe($original->logicalOperator());
        });

        test('constrainer handles complex value types', function (): void {
            // Arrange
            $entity1 = new Account(['tags' => ['php', 'laravel']]);
            $entity2 = new Account(['tags' => ['javascript', 'vue']]);

            // Act
            $constraint = new ValueConstraint('tags', '=', ['php', 'laravel']);

            // Assert
            expect($constraint->check($entity1))->toBeTrue();
            expect($constraint->check($entity2))->toBeFalse();
        });

        test('constrainer handles null values', function (): void {
            // Arrange
            $entityWithNull = new Account(['description' => null]);
            $entityWithValue = new Account(['description' => 'Some text']);

            // Act
            $constraint = new ValueConstraint('description', '=', null);

            // Assert
            expect($constraint->check($entityWithNull))->toBeTrue();
            expect($constraint->check($entityWithValue))->toBeFalse();
        });

        test('constrainer enforces strict type comparison', function (): void {
            // Arrange
            $entityWithString = new Account(['count' => '10']);
            $entityWithInt = new Account(['count' => 10]);

            // Act
            $stringConstraint = new ValueConstraint('count', '=', '10');
            $intConstraint = new ValueConstraint('count', '=', 10);

            // Assert
            expect($stringConstraint->check($entityWithString))->toBeTrue();
            expect($stringConstraint->check($entityWithInt))->toBeFalse();
            expect($intConstraint->check($entityWithString))->toBeFalse();
            expect($intConstraint->check($entityWithInt))->toBeTrue();
        });
    });
});
