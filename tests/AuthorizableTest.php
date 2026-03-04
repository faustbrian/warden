<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Database\Role;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\TestsClipboards;
use Tests\Fixtures\Models\Account;
use Tests\Fixtures\Models\User;

uses(TestsClipboards::class);

describe('Authorizable (Role Authorization)', function (): void {
    describe('Happy Paths', function (): void {
        test('checks simple abilities on roles returning correct authorization state', function ($provider): void {
            // Arrange
            $provider();
            $role = Role::query()->create(['name' => 'admin']);

            // Act
            $role->allow('scream');

            // Assert
            expect($role->can('scream'))->toBeTrue();
            expect($role->cant('shout'))->toBeTrue();
            expect($role->cannot('cry'))->toBeTrue();
        })->with('bouncerProvider');

        test('checks model-specific abilities on roles with proper scoping', function ($provider): void {
            // Arrange
            $provider();
            $role = Role::query()->create(['name' => 'admin']);

            // Act
            $role->allow('create', User::class);

            // Assert
            expect($role->can('create', User::class))->toBeTrue();
            expect($role->cannot('create', Account::class))->toBeTrue();
            expect($role->cannot('update', User::class))->toBeTrue();
            expect($role->cannot('create'))->toBeTrue();
        })->with('bouncerProvider');
    });
});
