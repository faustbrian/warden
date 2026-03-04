<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Constraints\Constraint;
use Cline\Warden\Constraints\Group;
use Cline\Warden\Database\Ability;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Models\Account;
use Tests\Fixtures\Models\User;

describe('Ability Constraints', function (): void {
    describe('Happy Paths', function (): void {
        test('returns empty constraint group for ability without constraints', function (): void {
            // Act
            $group = Ability::createForModel(Account::class, '*')->getConstraints();

            // Assert
            expect($group)->toBeInstanceOf(Group::class);
        });

        test('correctly identifies when ability has constraints set', function (): void {
            // Arrange
            $empty = Ability::makeForModel(Account::class, '*');

            $full = Ability::makeForModel(Account::class, '*')->setConstraints(
                Group::withAnd()->add(Constraint::where('active', true)),
            );

            // Assert
            expect($empty->hasConstraints())->toBeFalse();
            expect($full->hasConstraints())->toBeTrue();
        });

        test('persists and retrieves constraints with proper validation behavior', function (): void {
            // Arrange
            $ability = Ability::makeForModel(Account::class, '*')->setConstraints(
                new Group([
                    Constraint::where('active', true),
                ]),
            );

            // Act
            $ability->save();

            $constraints = Ability::query()->find($ability->id)->getConstraints();

            // Assert
            expect($constraints)->toBeInstanceOf(Group::class);
            expect($constraints->check(
                new Account(['active' => true]),
                new User(),
            ))->toBeTrue();
            expect($constraints->check(
                new Account(['active' => false]),
                new User(),
            ))->toBeFalse();
        });
    });
});
