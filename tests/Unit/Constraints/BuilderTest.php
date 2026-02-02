<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Constraints\Builder;
use Cline\Warden\Constraints\Constraint;
use Cline\Warden\Constraints\Group;
use PHPUnit\Framework\Attributes\Test;

describe('Constraints Builder', function (): void {
    describe('Happy Paths', function (): void {
        test('building without constraints returns empty group', function (): void {
            // Arrange & Act
            $actual = new Builder()->build();

            // Assert
            $expected = new Group();
            expect($expected->equals($actual))->toBeTrue();
        });

        test('a single where returns a single constraint', function (): void {
            // Arrange & Act
            $constraint = Builder::make()->where('active', false)->build();

            // Assert
            expect($constraint->equals(Constraint::where('active', false)))->toBeTrue();
        });

        test('a single where column returns a single column constraint', function (): void {
            // Arrange & Act
            $builder = Builder::make()->whereColumn('team_id', 'team_id');

            // Assert
            $expected = Constraint::whereColumn('team_id', 'team_id');
            expect($expected->equals($builder->build()))->toBeTrue();
        });

        test('a single or where returns a single or constraint', function (): void {
            // Arrange & Act
            $actual = Builder::make()->orWhere('active', '=', false)->build();

            // Assert
            $expected = Constraint::orWhere('active', '=', false);
            expect($expected->equals($actual))->toBeTrue();
        });

        test('two wheres return a group', function (): void {
            // Arrange & Act
            $builder = Builder::make()
                ->where('active', false)
                ->where('age', '>=', 18);

            // Assert
            $expected = new Group()
                ->add(Constraint::where('active', false))
                ->add(Constraint::where('age', '>=', 18));
            expect($expected->equals($builder->build()))->toBeTrue();
        });

        test('two where columns return a group', function (): void {
            // Arrange & Act
            $builder = Builder::make()
                ->whereColumn('active', 'other_active')
                ->whereColumn('age', '>=', 'min_age');

            // Assert
            $expected = new Group()
                ->add(Constraint::whereColumn('active', 'other_active'))
                ->add(Constraint::whereColumn('age', '>=', 'min_age'));
            expect($expected->equals($builder->build()))->toBeTrue();
        });

        test('or wheres return a group', function (): void {
            // Arrange & Act
            $builder = Builder::make()
                ->where('active', false)
                ->orWhere('age', '>=', 18);

            // Assert
            $expected = new Group()
                ->add(Constraint::where('active', false))
                ->add(Constraint::orWhere('age', '>=', 18));
            expect($expected->equals($builder->build()))->toBeTrue();
        });

        test('nested wheres return a group', function (): void {
            // Arrange & Act
            $builder = Builder::make()->where('active', false)->where(function ($query): void {
                $query->where('a', 'b')->where('c', 'd');
            });

            // Assert
            $expected = new Group()
                ->add(Constraint::where('active', false))
                ->add(
                    new Group()
                        ->add(Constraint::where('a', 'b'))
                        ->add(Constraint::where('c', 'd')),
                );
            expect($expected->equals($builder->build()))->toBeTrue();
        });

        test('nested or where returns an or group', function (): void {
            // Arrange & Act
            $builder = Builder::make()->where('active', false)->orWhere(function ($query): void {
                $query->where('a', 'b')->where('c', 'd');
            });

            // Assert
            $expected = new Group()
                ->add(Constraint::where('active', false))
                ->add(
                    Group::withOr()
                        ->add(Constraint::where('a', 'b'))
                        ->add(Constraint::where('c', 'd')),
                );
            expect($expected->equals($builder->build()))->toBeTrue();
        });

        test('can nest multiple levels', function (): void {
            // Arrange & Act
            $builder = Builder::make()
                ->where('active', false)
                ->orWhere(function ($query): void {
                    $query->where('a', 'b')->where('c', 'd')->where(function ($query): void {
                        $query->where('1', '=', '2')->orWhere('3', '=', '4');
                    });
                });

            // Assert
            $expected = new Group()
                ->add(Constraint::where('active', false))
                ->add(
                    Group::withOr()
                        ->add(Constraint::where('a', 'b'))
                        ->add(Constraint::where('c', 'd'))
                        ->add(
                            Group::withAnd()
                                ->add(Constraint::where('1', '=', '2'))
                                ->add(Constraint::orWhere('3', '=', '4')),
                        ),
                );
            expect($expected->equals($builder->build()))->toBeTrue();
        });

        test('or where column returns constraint with or logic', function (): void {
            // Arrange & Act
            $builder = Builder::make()->orWhereColumn('team_id', '=', 'team_id');

            // Assert
            $expected = Constraint::orWhereColumn('team_id', '=', 'team_id');
            expect($expected->equals($builder->build()))->toBeTrue();
        });

        test('or where column with operator returns constraint', function (): void {
            // Arrange & Act
            $builder = Builder::make()->orWhereColumn('team_id', '!=', 'other_team_id');

            // Assert
            $expected = Constraint::orWhereColumn('team_id', '!=', 'other_team_id');
            expect($expected->equals($builder->build()))->toBeTrue();
        });
    });
});
