<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\TestsClipboards;
use Tests\Fixtures\Enums\AbilityEnum;
use Tests\Fixtures\Enums\RoleEnum;

uses(TestsClipboards::class);

describe('Backed Enum Support', function (): void {
    describe('Happy Paths', function (): void {
        test('assigns role using backed enum', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            // Act
            $warden->assign(RoleEnum::Admin)->to($user);

            // Assert
            expect($warden->is($user)->a(RoleEnum::Admin))->toBeTrue();
        })->with('bouncerProvider');

        test('assigns multiple roles using backed enums', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            // Act
            $warden->assign([RoleEnum::Admin, RoleEnum::Editor])->to($user);

            // Assert
            expect($warden->is($user)->a(RoleEnum::Admin))->toBeTrue();
            expect($warden->is($user)->a(RoleEnum::Editor))->toBeTrue();
            expect($warden->is($user)->all(RoleEnum::Admin, RoleEnum::Editor))->toBeTrue();
        })->with('bouncerProvider');

        test('retracts role using backed enum', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();
            $warden->assign(RoleEnum::Admin)->to($user);

            // Act
            expect($warden->is($user)->a(RoleEnum::Admin))->toBeTrue();
            $warden->retract(RoleEnum::Admin)->from($user);

            // Assert
            expect($warden->is($user)->a(RoleEnum::Admin))->toBeFalse();
        })->with('bouncerProvider');

        test('checks role using backed enum with a() method', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();
            $warden->assign('admin')->to($user);

            // Assert
            expect($warden->is($user)->a(RoleEnum::Admin))->toBeTrue();
            expect($warden->is($user)->a(RoleEnum::Editor))->toBeFalse();
        })->with('bouncerProvider');

        test('checks role using backed enum with an() method', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();
            $warden->assign('admin')->to($user);

            // Assert
            expect($warden->is($user)->an(RoleEnum::Admin))->toBeTrue();
            expect($warden->is($user)->an(RoleEnum::Editor))->toBeFalse();
        })->with('bouncerProvider');

        test('checks role using backed enum with notA() method', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();
            $warden->assign('admin')->to($user);

            // Assert
            expect($warden->is($user)->notA(RoleEnum::Admin))->toBeFalse();
            expect($warden->is($user)->notA(RoleEnum::Editor))->toBeTrue();
        })->with('bouncerProvider');

        test('checks role using backed enum with notAn() method', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();
            $warden->assign('admin')->to($user);

            // Assert
            expect($warden->is($user)->notAn(RoleEnum::Admin))->toBeFalse();
            expect($warden->is($user)->notAn(RoleEnum::Editor))->toBeTrue();
        })->with('bouncerProvider');

        test('checks multiple roles using backed enum with all() method', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();
            $warden->assign([RoleEnum::Admin, RoleEnum::Editor])->to($user);

            // Assert
            expect($warden->is($user)->all(RoleEnum::Admin, RoleEnum::Editor))->toBeTrue();
            expect($warden->is($user)->all(RoleEnum::Admin, RoleEnum::Moderator))->toBeFalse();
        })->with('bouncerProvider');

        test('grants ability using backed enum', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            // Act
            $warden->allow($user)->to(AbilityEnum::Create);

            // Assert
            expect($warden->can('create'))->toBeTrue();
        })->with('bouncerProvider');

        test('grants multiple abilities using backed enums', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            // Act
            $warden->allow($user)->to([AbilityEnum::Create, AbilityEnum::Update, AbilityEnum::Delete]);

            // Assert
            expect($warden->can('create'))->toBeTrue();
            expect($warden->can('update'))->toBeTrue();
            expect($warden->can('delete'))->toBeTrue();
        })->with('bouncerProvider');

        test('removes ability using backed enum', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();
            $warden->allow($user)->to(AbilityEnum::Create);

            // Act
            expect($warden->can('create'))->toBeTrue();
            $warden->disallow($user)->to(AbilityEnum::Create);

            // Assert
            expect($warden->can('create'))->toBeFalse();
        })->with('bouncerProvider');

        test('forbids ability using backed enum', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            // Act
            $warden->forbid($user)->to(AbilityEnum::Delete);

            // Assert
            expect($warden->can('delete'))->toBeFalse();
        })->with('bouncerProvider');

        test('unforbids ability using backed enum allowing it to be granted', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();
            $warden->forbid($user)->to(AbilityEnum::Delete);
            expect($warden->can('delete'))->toBeFalse();

            // Act
            $warden->unforbid($user)->to(AbilityEnum::Delete);
            $warden->allow($user)->to(AbilityEnum::Delete);

            // Assert
            expect($warden->can('delete'))->toBeTrue();
        })->with('bouncerProvider');

        test('mixes backed enums with strings when assigning roles', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            // Act
            $warden->assign([RoleEnum::Admin, 'custom-role'])->to($user);

            // Assert
            expect($warden->is($user)->a(RoleEnum::Admin))->toBeTrue();
            expect($warden->is($user)->a('custom-role'))->toBeTrue();
        })->with('bouncerProvider');

        test('mixes backed enums with strings when granting abilities', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            // Act
            $warden->allow($user)->to([AbilityEnum::Create, 'custom-ability']);

            // Assert
            expect($warden->can('create'))->toBeTrue();
            expect($warden->can('custom-ability'))->toBeTrue();
        })->with('bouncerProvider');

        test('grants ability for everyone using backed enum', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            // Act
            $warden->allowEveryone()->to(AbilityEnum::Read);

            // Assert
            expect($warden->can('read'))->toBeTrue();
        })->with('bouncerProvider');

        test('forbids ability for everyone using backed enum', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            // Act
            $warden->forbidEveryone()->to(AbilityEnum::Delete);

            // Assert
            expect($warden->can('delete'))->toBeFalse();
        })->with('bouncerProvider');
    });
});
