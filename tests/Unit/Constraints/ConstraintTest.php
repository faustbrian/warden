<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Constraints\ColumnConstraint;
use Cline\Warden\Constraints\Constraint;
use Cline\Warden\Constraints\ValueConstraint;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Models\Account;
use Tests\Fixtures\Models\User;

describe('Constraint', function (): void {
    describe('Happy Paths', function (): void {
        test('value constraint implicit equals', function (): void {
            // Arrange
            $authority = new User();
            $activeAccount = new Account(['active' => true]);
            $inactiveAccount = new Account(['active' => false]);
            $constraint = Constraint::where('active', true);

            // Act & Assert
            expect($constraint->check($activeAccount, $authority))->toBeTrue();
            expect($constraint->check($inactiveAccount, $authority))->toBeFalse();
        });

        test('value constraint explicit equals', function (): void {
            // Arrange
            $authority = new User();
            $activeAccount = new Account(['active' => true]);
            $inactiveAccount = new Account(['active' => false]);
            $constraint = Constraint::where('active', '=', true);

            // Act & Assert
            expect($constraint->check($activeAccount, $authority))->toBeTrue();
            expect($constraint->check($inactiveAccount, $authority))->toBeFalse();
        });

        test('value constraint explicit double equals', function (): void {
            // Arrange
            $authority = new User();
            $activeAccount = new Account(['active' => true]);
            $inactiveAccount = new Account(['active' => false]);
            $constraint = Constraint::where('active', '==', true);

            // Act & Assert
            expect($constraint->check($activeAccount, $authority))->toBeTrue();
            expect($constraint->check($inactiveAccount, $authority))->toBeFalse();
        });

        test('value constraint not equals', function (): void {
            // Arrange
            $authority = new User();
            $activeAccount = new Account(['active' => true]);
            $inactiveAccount = new Account(['active' => false]);
            $constraint = Constraint::where('active', '!=', false);

            // Act & Assert
            expect($constraint->check($activeAccount, $authority))->toBeTrue();
            expect($constraint->check($inactiveAccount, $authority))->toBeFalse();
        });

        test('value constraint greater than', function (): void {
            // Arrange
            $authority = new User();
            $forty = new User(['age' => 40]);
            $fortyOne = new User(['age' => 41]);
            $constraint = Constraint::where('age', '>', 40);

            // Act & Assert
            expect($constraint->check($fortyOne, $authority))->toBeTrue();
            expect($constraint->check($forty, $authority))->toBeFalse();
        });

        test('value constraint less than', function (): void {
            // Arrange
            $authority = new User();
            $thirtyNine = new User(['age' => 39]);
            $forty = new User(['age' => 40]);
            $constraint = Constraint::where('age', '<', 40);

            // Act & Assert
            expect($constraint->check($thirtyNine, $authority))->toBeTrue();
            expect($constraint->check($forty, $authority))->toBeFalse();
        });

        test('value constraint greater than or equal', function (): void {
            // Arrange
            $authority = new User();
            $minor = new User(['age' => 17]);
            $adult = new User(['age' => 18]);
            $senior = new User(['age' => 80]);
            $constraint = Constraint::where('age', '>=', 18);

            // Act & Assert
            expect($constraint->check($adult, $authority))->toBeTrue();
            expect($constraint->check($senior, $authority))->toBeTrue();
            expect($constraint->check($minor, $authority))->toBeFalse();
        });

        test('value constraint less than or equal', function (): void {
            // Arrange
            $authority = new User();
            $youngerTeen = new User(['age' => 18]);
            $olderTeen = new User(['age' => 19]);
            $adult = new User(['age' => 20]);
            $constraint = Constraint::where('age', '<=', 19);

            // Act & Assert
            expect($constraint->check($youngerTeen, $authority))->toBeTrue();
            expect($constraint->check($olderTeen, $authority))->toBeTrue();
            expect($constraint->check($adult, $authority))->toBeFalse();
        });

        test('column constraint implicit equals', function (): void {
            // Arrange
            $authority = new User(['age' => 1]);
            $one = new User(['age' => 1]);
            $two = new User(['age' => 2]);
            $constraint = Constraint::whereColumn('age', 'age');

            // Act & Assert
            expect($constraint->check($one, $authority))->toBeTrue();
            expect($constraint->check($two, $authority))->toBeFalse();
        });

        test('column constraint explicit equals', function (): void {
            // Arrange
            $authority = new User(['age' => 1]);
            $one = new User(['age' => 1]);
            $two = new User(['age' => 2]);
            $constraint = Constraint::whereColumn('age', '=', 'age');

            // Act & Assert
            expect($constraint->check($one, $authority))->toBeTrue();
            expect($constraint->check($two, $authority))->toBeFalse();
        });

        test('column constraint explicit double equals', function (): void {
            // Arrange
            $authority = new User(['age' => 1]);
            $one = new User(['age' => 1]);
            $two = new User(['age' => 2]);
            $constraint = Constraint::whereColumn('age', '=', 'age');

            // Act & Assert
            expect($constraint->check($one, $authority))->toBeTrue();
            expect($constraint->check($two, $authority))->toBeFalse();
        });

        test('column constraint not equals', function (): void {
            // Arrange
            $authority = new User(['age' => 1]);
            $one = new User(['age' => 1]);
            $two = new User(['age' => 2]);
            $constraint = Constraint::whereColumn('age', '!=', 'age');

            // Act & Assert
            expect($constraint->check($two, $authority))->toBeTrue();
            expect($constraint->check($one, $authority))->toBeFalse();
        });

        test('column constraint greater than', function (): void {
            // Arrange
            $authority = new User(['age' => 18]);
            $younger = new User(['age' => 17]);
            $same = new User(['age' => 18]);
            $older = new User(['age' => 19]);
            $constraint = Constraint::whereColumn('age', '>', 'age');

            // Act & Assert
            expect($constraint->check($older, $authority))->toBeTrue();
            expect($constraint->check($younger, $authority))->toBeFalse();
            expect($constraint->check($same, $authority))->toBeFalse();
        });

        test('column constraint less than', function (): void {
            // Arrange
            $authority = new User(['age' => 18]);
            $younger = new User(['age' => 17]);
            $same = new User(['age' => 18]);
            $older = new User(['age' => 19]);
            $constraint = Constraint::whereColumn('age', '<', 'age');

            // Act & Assert
            expect($constraint->check($younger, $authority))->toBeTrue();
            expect($constraint->check($older, $authority))->toBeFalse();
            expect($constraint->check($same, $authority))->toBeFalse();
        });

        test('column constraint greater than or equal', function (): void {
            // Arrange
            $authority = new User(['age' => 18]);
            $younger = new User(['age' => 17]);
            $same = new User(['age' => 18]);
            $older = new User(['age' => 19]);
            $constraint = Constraint::whereColumn('age', '>=', 'age');

            // Act & Assert
            expect($constraint->check($same, $authority))->toBeTrue();
            expect($constraint->check($older, $authority))->toBeTrue();
            expect($constraint->check($younger, $authority))->toBeFalse();
        });

        test('column constraint less than or equal', function (): void {
            // Arrange
            $authority = new User(['age' => 18]);
            $younger = new User(['age' => 17]);
            $same = new User(['age' => 18]);
            $older = new User(['age' => 19]);
            $constraint = Constraint::whereColumn('age', '<=', 'age');

            // Act & Assert
            expect($constraint->check($younger, $authority))->toBeTrue();
            expect($constraint->check($same, $authority))->toBeTrue();
            expect($constraint->check($older, $authority))->toBeFalse();
        });

        test('value constraint can be properly serialized and deserialized', function (): void {
            // Arrange
            $authority = new User();
            $activeAccount = new Account(['active' => true]);
            $inactiveAccount = new Account(['active' => false]);

            // Act
            $constraint = serializeAndDeserializeConstraint(Constraint::where('active', true));

            // Assert
            expect($constraint)->toBeInstanceOf(ValueConstraint::class);
            expect($constraint->check($activeAccount, $authority))->toBeTrue();
            expect($constraint->check($inactiveAccount, $authority))->toBeFalse();
        });

        test('column constraint can be properly serialized and deserialized', function (): void {
            // Arrange
            $authority = new User(['age' => 1]);
            $one = new User(['age' => 1]);
            $two = new User(['age' => 2]);

            // Act
            $constraint = serializeAndDeserializeConstraint(Constraint::whereColumn('age', 'age'));

            // Assert
            expect($constraint)->toBeInstanceOf(ColumnConstraint::class);
            expect($constraint->check($one, $authority))->toBeTrue();
            expect($constraint->check($two, $authority))->toBeFalse();
        });

        test('or where column creates or constraint', function (): void {
            // Arrange
            $authority = new User(['age' => 1]);
            $one = new User(['age' => 1]);
            $two = new User(['age' => 2]);

            // Act
            $constraint = Constraint::orWhereColumn('age', '=', 'age');

            // Assert
            expect($constraint)->toBeInstanceOf(ColumnConstraint::class);
            expect($constraint->logicalOperator())->toBe('or');
            expect($constraint->check($one, $authority))->toBeTrue();
            expect($constraint->check($two, $authority))->toBeFalse();
        });

        test('or where column with operator creates or constraint', function (): void {
            // Arrange
            $authority = new User(['age' => 18]);
            $younger = new User(['age' => 17]);
            $older = new User(['age' => 19]);

            // Act
            $constraint = Constraint::orWhereColumn('age', '>', 'age');

            // Assert
            expect($constraint)->toBeInstanceOf(ColumnConstraint::class);
            expect($constraint->logicalOperator())->toBe('or');
            expect($constraint->check($older, $authority))->toBeTrue();
            expect($constraint->check($younger, $authority))->toBeFalse();
        });
    });

    describe('Sad Paths', function (): void {
        test('logical operator throws exception when integer provided', function (): void {
            // Arrange
            $constraint = Constraint::where('active', true);

            // Assert
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('Logical operator must be a string, int given');

            // Act
            $constraint->logicalOperator(123);
        });

        test('logical operator throws exception when array provided', function (): void {
            // Arrange
            $constraint = Constraint::where('active', true);

            // Assert
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('Logical operator must be a string, array given');

            // Act
            $constraint->logicalOperator(['and']);
        });

        test('logical operator throws exception when object provided', function (): void {
            // Arrange
            $constraint = Constraint::where('active', true);

            // Assert
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('Logical operator must be a string, stdClass given');

            // Act
            $constraint->logicalOperator((object) ['operator' => 'and']);
        });
    });
});

/**
 * Convert the given object to JSON, then back.
 *
 * @return Constraint
 */
function serializeAndDeserializeConstraint(Constraint $constraint)
{
    $data = json_decode(json_encode($constraint->data()), true);

    return $data['class']::fromData($data['params']);
}
