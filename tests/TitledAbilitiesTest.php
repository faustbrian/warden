<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Database\Ability;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Models\Account;
use Tests\Fixtures\Models\User;

describe('Titled Abilities', function (): void {
    describe('Happy Paths', function (): void {
        test('creates titled ability for simple ability permission', function (): void {
            // Arrange
            $warden = $this->bouncer($user = User::query()->create());

            // Act
            $warden->allow($user)->to('access-dashboard', null, [
                'title' => 'Dashboard administration',
            ]);

            // Assert
            seeTitledAbility('Dashboard administration');
        });

        test('creates titled ability for model class permission', function (): void {
            // Arrange
            $warden = $this->bouncer($user = User::query()->create());

            // Act
            $warden->allow($user)->to('create', User::class, [
                'title' => 'Create users',
            ]);

            // Assert
            seeTitledAbility('Create users');
        });

        test('creates titled ability for specific model instance permission', function (): void {
            // Arrange
            $warden = $this->bouncer($user = User::query()->create());

            // Act
            $warden->allow($user)->to('delete', $user, [
                'title' => 'Delete user #1',
            ]);

            // Assert
            seeTitledAbility('Delete user #1');
        });

        test('creates titled ability for everything permission', function (): void {
            // Arrange
            $warden = $this->bouncer($user = User::query()->create());

            // Act
            $warden->allow($user)->everything([
                'title' => 'Omnipotent',
            ]);

            // Assert
            seeTitledAbility('Omnipotent');
        });

        test('creates titled ability for manage model class permission', function (): void {
            // Arrange
            $warden = $this->bouncer($user = User::query()->create());

            // Act
            $warden->allow($user)->toManage(User::class, [
                'title' => 'Manage users',
            ]);

            // Assert
            seeTitledAbility('Manage users');
        });

        test('creates titled ability for manage specific model permission', function (): void {
            // Arrange
            $warden = $this->bouncer($user = User::query()->create());

            // Act
            $warden->allow($user)->toManage($user, [
                'title' => 'Manage user #1',
            ]);

            // Assert
            seeTitledAbility('Manage user #1');
        });

        test('creates titled ability for ability on everything permission', function (): void {
            // Arrange
            $warden = $this->bouncer($user = User::query()->create());

            // Act
            $warden->allow($user)->to('create')->everything([
                'title' => 'Create anything',
            ]);

            // Assert
            seeTitledAbility('Create anything');
        });

        test('creates titled ability for own model class permission', function (): void {
            // Arrange
            $warden = $this->bouncer($user = User::query()->create());

            // Act
            $warden->allow($user)->toOwn(Account::class, [
                'title' => 'Manage onwed account',
            ]);

            // Assert
            seeTitledAbility('Manage onwed account');
        });

        test('creates titled ability for own specific model permission', function (): void {
            // Arrange
            $warden = $this->bouncer($user = User::query()->create());

            // Act
            $warden->allow($user)->toOwn($user, [
                'title' => 'Manage user #1 when owned',
            ]);

            // Assert
            seeTitledAbility('Manage user #1 when owned');
        });

        test('creates titled ability for own everything permission', function (): void {
            // Arrange
            $warden = $this->bouncer($user = User::query()->create());

            // Act
            $warden->allow($user)->toOwnEverything([
                'title' => 'Manage anything onwed',
            ]);

            // Assert
            seeTitledAbility('Manage anything onwed');
        });
    });

    describe('Sad Paths', function (): void {
        test('creates titled ability for forbidden simple ability', function (): void {
            // Arrange
            $warden = $this->bouncer($user = User::query()->create());

            // Act
            $warden->forbid($user)->to('access-dashboard', null, [
                'title' => 'Dashboard administration',
            ]);

            // Assert
            seeTitledAbility('Dashboard administration');
        });

        test('creates titled ability for forbidden model class ability', function (): void {
            // Arrange
            $warden = $this->bouncer($user = User::query()->create());

            // Act
            $warden->forbid($user)->to('create', User::class, [
                'title' => 'Create users',
            ]);

            // Assert
            seeTitledAbility('Create users');
        });

        test('creates titled ability for forbidden model instance ability', function (): void {
            // Arrange
            $warden = $this->bouncer($user = User::query()->create());

            // Act
            $warden->forbid($user)->to('delete', $user, [
                'title' => 'Delete user #1',
            ]);

            // Assert
            seeTitledAbility('Delete user #1');
        });

        test('creates titled ability for forbidden everything', function (): void {
            // Arrange
            $warden = $this->bouncer($user = User::query()->create());

            // Act
            $warden->forbid($user)->everything([
                'title' => 'Omnipotent',
            ]);

            // Assert
            seeTitledAbility('Omnipotent');
        });

        test('creates titled ability for forbidden manage model class', function (): void {
            // Arrange
            $warden = $this->bouncer($user = User::query()->create());

            // Act
            $warden->forbid($user)->toManage(User::class, [
                'title' => 'Manage users',
            ]);

            // Assert
            seeTitledAbility('Manage users');
        });

        test('creates titled ability for forbidden manage specific model', function (): void {
            // Arrange
            $warden = $this->bouncer($user = User::query()->create());

            // Act
            $warden->forbid($user)->toManage($user, [
                'title' => 'Manage user #1',
            ]);

            // Assert
            seeTitledAbility('Manage user #1');
        });

        test('creates titled ability for forbidden ability on everything', function (): void {
            // Arrange
            $warden = $this->bouncer($user = User::query()->create());

            // Act
            $warden->forbid($user)->to('create')->everything([
                'title' => 'Create anything',
            ]);

            // Assert
            seeTitledAbility('Create anything');
        });

        test('creates titled ability for forbidden own model class', function (): void {
            // Arrange
            $warden = $this->bouncer($user = User::query()->create());

            // Act
            $warden->forbid($user)->toOwn(Account::class, [
                'title' => 'Manage onwed account',
            ]);

            // Assert
            seeTitledAbility('Manage onwed account');
        });

        test('creates titled ability for forbidden own specific model', function (): void {
            // Arrange
            $warden = $this->bouncer($user = User::query()->create());

            // Act
            $warden->forbid($user)->toOwn($user, [
                'title' => 'Manage user #1 when owned',
            ]);

            // Assert
            seeTitledAbility('Manage user #1 when owned');
        });

        test('creates titled ability for forbidden own everything', function (): void {
            // Arrange
            $warden = $this->bouncer($user = User::query()->create());

            // Act
            $warden->forbid($user)->toOwnEverything([
                'title' => 'Manage anything onwed',
            ]);

            // Assert
            seeTitledAbility('Manage anything onwed');
        });
    });
});

/**
 * Assert that there's an ability with the given title in the DB.
 */
function seeTitledAbility(string $title): void
{
    expect(Ability::query()->where(['title' => $title])->exists())->toBeTrue();
}

/**
 * @author Brian Faust <brian@cline.sh>
 */
