<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Database\Role;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Models\User;

describe('Role Query Scopes', function (): void {
    describe('Happy Paths', function (): void {
        test('filters roles assigned to specific user', function (): void {
            // Arrange
            $warden = $this->bouncer($user = User::query()->create());

            Role::query()->create(['name' => 'admin']);
            Role::query()->create(['name' => 'editor']);
            Role::query()->create(['name' => 'manager']);
            Role::query()->create(['name' => 'subscriber']);

            $warden->assign('admin')->to($user);
            $warden->assign('manager')->to($user);

            // Act
            $roles = Role::whereAssignedTo($user)->get();

            // Assert
            expect($roles)->toHaveCount(2);
            expect($roles->contains('name', 'admin'))->toBeTrue();
            expect($roles->contains('name', 'manager'))->toBeTrue();
            expect($roles->contains('name', 'editor'))->toBeFalse();
            expect($roles->contains('name', 'subscriber'))->toBeFalse();
        });

        test('filters roles assigned to any user in collection', function (): void {
            // Arrange
            $user1 = User::query()->create();
            $user2 = User::query()->create();

            $warden = $this->bouncer($user1);

            Role::query()->create(['name' => 'admin']);
            Role::query()->create(['name' => 'editor']);
            Role::query()->create(['name' => 'manager']);
            Role::query()->create(['name' => 'subscriber']);

            $warden->assign('editor')->to($user1);
            $warden->assign('manager')->to($user1);
            $warden->assign('subscriber')->to($user2);

            // Act
            $roles = Role::whereAssignedTo(User::all())->get();

            // Assert
            expect($roles)->toHaveCount(3);
            expect($roles->contains('name', 'manager'))->toBeTrue();
            expect($roles->contains('name', 'editor'))->toBeTrue();
            expect($roles->contains('name', 'subscriber'))->toBeTrue();
            expect($roles->contains('name', 'admin'))->toBeFalse();
        });

        test('filters roles assigned to users by model class and keys', function (): void {
            // Arrange
            $user1 = User::query()->create();
            $user2 = User::query()->create();

            $warden = $this->bouncer($user1);

            Role::query()->create(['name' => 'admin']);
            Role::query()->create(['name' => 'editor']);
            Role::query()->create(['name' => 'manager']);
            Role::query()->create(['name' => 'subscriber']);

            $warden->assign('editor')->to($user1);
            $warden->assign('manager')->to($user1);
            $warden->assign('subscriber')->to($user2);

            // Act
            $roles = Role::whereAssignedTo(User::class, User::all()->modelKeys())->get();

            // Assert
            expect($roles)->toHaveCount(3);
            expect($roles->contains('name', 'manager'))->toBeTrue();
            expect($roles->contains('name', 'editor'))->toBeTrue();
            expect($roles->contains('name', 'subscriber'))->toBeTrue();
            expect($roles->contains('name', 'admin'))->toBeFalse();
        });
    });
});

/**
 * @author Brian Faust <brian@cline.sh>
 */
