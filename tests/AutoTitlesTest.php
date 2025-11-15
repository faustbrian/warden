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

describe('Auto Titles', function (): void {
    describe('Happy Paths', function (): void {
        test('preserves explicit role title when provided on creation', function (): void {
            // Act
            $role = Role::query()->create(['name' => 'admin', 'title' => 'Something Else']);

            // Assert
            expect($role->title)->toEqual('Something Else');
        });

        test('capitalizes role name to create title when not provided', function (): void {
            // Act
            $role = Role::query()->create(['name' => 'admin']);

            // Assert
            expect($role->title)->toEqual('Admin');
        });

        test('capitalizes first word only for role title with spaces', function (): void {
            // Act
            $role = Role::query()->create(['name' => 'site admin']);

            // Assert
            expect($role->title)->toEqual('Site admin');
        });

        test('converts dashes to spaces in role title', function (): void {
            // Act
            $role = Role::query()->create(['name' => 'site-admin']);

            // Assert
            expect($role->title)->toEqual('Site admin');
        });

        test('converts underscores to spaces in role title', function (): void {
            // Act
            $role = Role::query()->create(['name' => 'site_admin']);

            // Assert
            expect($role->title)->toEqual('Site admin');
        });

        test('converts camelCase to title case with spaces for role title', function (): void {
            // Act
            $role = Role::query()->create(['name' => 'siteAdmin']);

            // Assert
            expect($role->title)->toEqual('Site admin');
        });

        test('converts StudlyCase to title case with spaces for role title', function (): void {
            // Act
            $role = Role::query()->create(['name' => 'SiteAdmin']);

            // Assert
            expect($role->title)->toEqual('Site admin');
        });

        test('preserves explicit ability title when provided', function (): void {
            // Arrange
            $warden = $this->bouncer($user = User::query()->create());

            // Act
            $warden->allow($user)->to('ban-users', null, [
                'title' => 'Something Else',
            ]);

            // Assert
            expect($warden->ability()->first()->title)->toEqual('Something Else');
        });

        test('generates "All abilities" title for everything wildcard', function (): void {
            // Arrange
            $warden = $this->bouncer($user = User::query()->create());

            // Act
            $warden->allow($user)->everything();

            // Assert
            expect($warden->ability()->first()->title)->toEqual('All abilities');
        });

        test('generates "All simple abilities" title for restricted wildcard', function (): void {
            // Arrange
            $warden = $this->bouncer($user = User::query()->create());

            // Act
            $warden->allow($user)->to('*');

            // Assert
            expect($warden->ability()->first()->title)->toEqual('All simple abilities');
        });

        test('generates human-readable title for simple abilities', function (): void {
            // Arrange
            $warden = $this->bouncer($user = User::query()->create());

            // Act
            $warden->allow($user)->to('ban-users');

            // Assert
            expect($warden->ability()->first()->title)->toEqual('Ban users');
        });

        test('generates "Manage everything owned" title for blanket ownership ability', function (): void {
            // Arrange
            $warden = $this->bouncer($user = User::query()->create());

            // Act
            $warden->allow($user)->toOwnEverything();

            // Assert
            expect($warden->ability()->first()->title)->toEqual('Manage everything owned');
        });

        test('generates action-specific title for restricted ownership ability', function (): void {
            // Arrange
            $warden = $this->bouncer($user = User::query()->create());

            // Act
            $warden->allow($user)->toOwnEverything()->to('edit');

            // Assert
            expect($warden->ability()->first()->title)->toEqual('Edit everything owned');
        });

        test('generates "Manage [model]" title for management ability', function (): void {
            // Arrange
            $warden = $this->bouncer($user = User::query()->create());

            // Act
            $warden->allow($user)->toManage(User::class);

            // Assert
            expect($warden->ability()->first()->title)->toEqual('Manage users');
        });

        test('generates "[Action] [models]" title for blanket model ability', function (): void {
            // Arrange
            $warden = $this->bouncer($user = User::query()->create());

            // Act
            $warden->allow($user)->to('create', User::class);

            // Assert
            expect($warden->ability()->first()->title)->toEqual('Create users');
        });

        test('generates "[Action] [model] #[id]" title for specific model instance ability', function (): void {
            // Arrange
            $warden = $this->bouncer($user = User::query()->create());

            // Act
            $warden->allow($user)->to('delete', User::query()->create());

            // Assert
            expect($warden->ability()->first()->title)->toEqual('Delete user #2');
        });

        test('generates "[Action] everything" title for global action ability', function (): void {
            // Arrange
            $warden = $this->bouncer($user = User::query()->create());

            // Act
            $warden->allow($user)->to('delete')->everything();

            // Assert
            expect($warden->ability()->first()->title)->toEqual('Delete everything');
        });
    });
});
