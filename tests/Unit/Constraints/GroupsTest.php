<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Constraints\Constraint;
use Cline\Warden\Constraints\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Models\Account;
use Tests\Fixtures\Models\User;

describe('Constraint Groups', function (): void {
    describe('Happy Paths', function (): void {
        test('named and constructor', function (): void {
            // Arrange & Act
            $group = Group::withAnd();

            // Assert
            expect($group)->toBeInstanceOf(Group::class);
            expect($group->logicalOperator())->toBe('and');
        });

        test('named or constructor', function (): void {
            // Arrange & Act
            $group = Group::withOr();

            // Assert
            expect($group)->toBeInstanceOf(Group::class);
            expect($group->logicalOperator())->toBe('or');
        });

        test('group of constraints only passes if all constraints pass the check', function (): void {
            // Arrange
            $account = new Account([
                'name' => 'the-account',
                'active' => false,
            ]);

            $groupA = new Group([
                Constraint::where('name', 'the-account'),
                Constraint::where('active', false),
            ]);

            $groupB = new Group([
                Constraint::where('name', 'the-account'),
                Constraint::where('active', true),
            ]);

            // Act & Assert
            expect($groupA->check($account, new User()))->toBeTrue();
            expect($groupB->check($account, new User()))->toBeFalse();
        });

        test('group of ors passes if any constraint passes the check', function (): void {
            // Arrange
            $account = new Account([
                'name' => 'the-account',
                'active' => false,
            ]);

            $groupA = new Group([
                Constraint::orWhere('name', '=', 'the-account'),
                Constraint::orWhere('active', '=', true),
            ]);

            $groupB = new Group([
                Constraint::orWhere('name', '=', 'a-different-account'),
                Constraint::orWhere('active', '=', true),
            ]);

            // Act & Assert
            expect($groupA->check($account, new User()))->toBeTrue();
            expect($groupB->check($account, new User()))->toBeFalse();
        });

        test('group can be serialized and deserialized', function (): void {
            // Arrange
            $activeAccount = new Account([
                'name' => 'the-account',
                'active' => true,
            ]);

            $inactiveAccount = new Account([
                'name' => 'the-account',
                'active' => false,
            ]);

            // Act
            $group = serializeAndDeserializeGroup(
                new Group([
                    Constraint::where('name', 'the-account'),
                    Constraint::where('active', true),
                ]),
            );

            // Assert
            expect($group)->toBeInstanceOf(Group::class);
            expect($group->check($activeAccount, new User()))->toBeTrue();
            expect($group->check($inactiveAccount, new User()))->toBeFalse();
        });

        test('group can be added to', function (): void {
            // Arrange
            $activeAccount = new Account([
                'name' => 'account',
                'active' => true,
            ]);

            $inactiveAccount = new Account([
                'name' => 'account',
                'active' => false,
            ]);

            // Act
            $group = new Group()
                ->add(Constraint::where('name', 'account'))
                ->add(Constraint::where('active', true));

            // Assert
            expect($group->check($activeAccount, new User()))->toBeTrue();
            expect($group->check($inactiveAccount, new User()))->toBeFalse();
        });

        test('group checks if it is and', function (): void {
            // Arrange
            $group = Group::withAnd();

            // Act & Assert
            expect($group->isAnd())->toBeTrue();
            expect($group->isOr())->toBeFalse();
        });

        test('group checks if it is or', function (): void {
            // Arrange
            $group = Group::withOr();

            // Act & Assert
            expect($group->isOr())->toBeTrue();
            expect($group->isAnd())->toBeFalse();
        });

        test('equals with different constraint count returns false', function (): void {
            // Arrange
            $groupA = new Group([
                Constraint::where('name', 'test'),
                Constraint::where('active', true),
            ]);

            $groupB = new Group([
                Constraint::where('name', 'test'),
            ]);

            // Act & Assert
            expect($groupA->equals($groupB))->toBeFalse();
            expect($groupB->equals($groupA))->toBeFalse();
        });

        test('equals with mismatched constraints returns false', function (): void {
            // Arrange
            $groupA = new Group([
                Constraint::where('name', 'test-a'),
                Constraint::where('active', true),
            ]);

            $groupB = new Group([
                Constraint::where('name', 'test-b'),
                Constraint::where('active', true),
            ]);

            // Act & Assert
            expect($groupA->equals($groupB))->toBeFalse();
        });
    });

    describe('Edge Cases', function (): void {
        test('check with empty group returns true', function (): void {
            // Arrange
            $account = new Account([
                'name' => 'test-account',
                'active' => false,
            ]);

            $emptyGroup = new Group();

            // Act
            $result = $emptyGroup->check($account, new User());

            // Assert
            expect($result)->toBeTrue();
        });

        test('equals with non group constrainer returns false', function (): void {
            // Arrange
            $group = new Group([
                Constraint::where('name', 'test'),
            ]);

            $constraint = Constraint::where('name', 'test');

            // Act & Assert
            expect($group->equals($constraint))->toBeFalse();
        });
    });

    describe('Sad Paths', function (): void {
        test('logical operator with non string type throws exception', function (): void {
            // Arrange
            $group = new Group();

            // Assert
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('Logical operator must be a string, got integer');

            // Act
            $group->logicalOperator(123);
        });
    });
});

/**
 * Convert the given object to JSON, then back.
 *
 * @return Group
 */
function serializeAndDeserializeGroup(Group $group)
{
    $data = json_decode(json_encode($group->data()), true);

    return $data['class']::fromData($data['params']);
}
