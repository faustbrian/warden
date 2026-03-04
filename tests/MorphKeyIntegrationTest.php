<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Database\Ability;
use Cline\Warden\Database\Models;
use Cline\Warden\Database\Role;
use Tests\Fixtures\Models\Account;
use Tests\Fixtures\Models\Organization;
use Tests\Fixtures\Models\User;

beforeEach(function (): void {
    Models::reset();
    Models::setUsersModel(User::class);

    // Use actual key column name for all models
    $keyName = new User()->getKeyName();
    Models::morphKeyMap([
        User::class => $keyName,
        Organization::class => $keyName,
        Account::class => $keyName,
    ]);
});

afterEach(function (): void {
    Models::reset();
});

describe('MorphKeyIntegration', function (): void {
    describe('Happy Paths', function (): void {
        test('uses correct key for actor id when assigning abilities', function (): void {
            // Arrange
            $org = Organization::query()->create(['name' => 'Acme']);
            $ability = Ability::query()->create(['name' => 'edit-posts']);
            $warden = self::bouncer($org);

            // Act
            $warden->allow($org)->to('edit-posts');

            // Assert
            $permission = $this->db()
                ->table('permissions')
                ->where('ability_id', $ability->id)
                ->where('actor_type', $org->getMorphClass())
                ->first();

            expect($permission)->not->toBeNull();
            expect($permission->actor_id)->toEqual($org->getKey());
        });

        test('uses correct key for actor id when assigning roles', function (): void {
            // Arrange
            $org = Organization::query()->create(['name' => 'Acme']);
            $role = Role::query()->create(['name' => 'admin']);
            $warden = self::bouncer($org);

            // Act
            $warden->assign('admin')->to($org);

            // Assert
            $assignment = $this->db()
                ->table('assigned_roles')
                ->where('role_id', $role->id)
                ->where('actor_type', $org->getMorphClass())
                ->first();

            expect($assignment)->not->toBeNull();
            expect($assignment->actor_id)->toEqual($org->getKey());
        });

        test('uses correct key for actor id with multiple authority types', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'John']);
            $org = Organization::query()->create(['name' => 'Acme']);
            $role = Role::query()->create(['name' => 'admin']);
            $warden = self::bouncer();

            // Act
            $warden->assign('admin')->to([$user, $org]);

            // Assert
            $userAssignment = $this->db()
                ->table('assigned_roles')
                ->where('role_id', $role->id)
                ->where('actor_type', $user->getMorphClass())
                ->first();

            $orgAssignment = $this->db()
                ->table('assigned_roles')
                ->where('role_id', $role->id)
                ->where('actor_type', $org->getMorphClass())
                ->first();

            expect($userAssignment)->not->toBeNull();
            expect($userAssignment->actor_id)->toEqual($user->id);
            expect($orgAssignment)->not->toBeNull();
            expect($orgAssignment->actor_id)->toEqual($org->getKey());
        });

        test('uses correct key for boundary id when assigning abilities', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'John']);
            $org = Organization::query()->create(['name' => 'Acme']);
            $ability = Ability::query()->create(['name' => 'edit-posts']);
            $warden = self::bouncer($user);

            // Act
            $warden->allow($user)->within($org)->to('edit-posts');

            // Assert
            $permission = $this->db()
                ->table('permissions')
                ->where('ability_id', $ability->id)
                ->where('actor_id', $user->id)
                ->where('boundary_type', $org->getMorphClass())
                ->first();

            expect($permission)->not->toBeNull();
            expect($permission->boundary_id)->toEqual($org->getKey());
        });

        test('uses correct key for boundary id when assigning roles', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'John']);
            $org = Organization::query()->create(['name' => 'Acme']);
            $role = Role::query()->create(['name' => 'admin']);
            $warden = self::bouncer($user);

            // Act
            $warden->assign('admin')->within($org)->to($user);

            // Assert
            $assignment = $this->db()
                ->table('assigned_roles')
                ->where('role_id', $role->id)
                ->where('actor_id', $user->id)
                ->where('boundary_type', $org->getMorphClass())
                ->first();

            expect($assignment)->not->toBeNull();
            expect($assignment->boundary_id)->toEqual($org->getKey());
        });

        test('uses correct key for boundary id when allowing everyone', function (): void {
            // Arrange
            $org = Organization::query()->create(['name' => 'Acme']);
            $ability = Ability::query()->create(['name' => 'edit-posts']);
            $user = User::query()->create(['name' => 'John']);
            $warden = self::bouncer($user);

            // Act
            $warden->allowEveryone()->within($org)->to('edit-posts');

            // Assert
            $permission = $this->db()
                ->table('permissions')
                ->where('ability_id', $ability->id)
                ->whereNull('actor_id')
                ->where('boundary_type', $org->getMorphClass())
                ->first();

            expect($permission)->not->toBeNull();
            expect($permission->boundary_id)->toEqual($org->getKey());
        });

        test('uses correct key for subject id when creating abilities', function (): void {
            // Arrange
            $org = Organization::query()->create(['name' => 'Acme']);

            // Act
            $ability = Ability::makeForModel($org, [
                'name' => 'edit',
            ]);

            // Assert
            expect($ability->subject_type)->toEqual($org->getMorphClass());
            expect(Models::getModelKey($org))->toBe('id'); // Column name is always 'id' regardless of type
        });

        test('uses correct key for subject id when creating model specific abilities', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'John']);
            $org = Organization::query()->create(['name' => 'Acme']);
            $warden = self::bouncer($user);

            // Act
            $warden->allow($user)->to('edit', $org);

            // Assert
            $ability = Ability::query()
                ->where('name', 'edit')
                ->where('subject_type', $org->getMorphClass())
                ->first();

            expect($ability)->toBeInstanceOf(Ability::class);
            $keyName = Models::getModelKey($org);
            expect($keyName)->toBe('id'); // Column name is always 'id' regardless of type
            expect($org->getAttribute($keyName))->toEqual($org->getKey());
        });

        test('queries abilities correctly with custom subject id', function (): void {
            // Arrange
            $user = User::query()->create(['name' => 'John']);
            $org = Organization::query()->create(['name' => 'Acme']);
            $warden = self::bouncer($user);

            // Act
            $warden->allow($user)->to('edit', $org);

            // Assert
            expect($warden->can('edit', $org))->toBeTrue();
        });

        test('removes abilities correctly with custom actor id', function (): void {
            // Arrange
            $org = Organization::query()->create(['name' => 'Acme']);
            $ability = Ability::query()->create(['name' => 'edit-posts']);
            $warden = self::bouncer($org);
            $warden->allow($org)->to('edit-posts');

            // Assert - Before removal
            expect($warden->can('edit-posts'))->toBeTrue();

            // Act
            $warden->disallow($org)->to('edit-posts');

            // Assert - After removal
            $permission = $this->db()
                ->table('permissions')
                ->where('ability_id', $ability->id)
                ->where('actor_type', $org->getMorphClass())
                ->where('actor_id', $org->getKey())
                ->first();

            expect($permission)->toBeNull();
            expect($warden->can('edit-posts'))->toBeFalse();
        });

        test('removes roles correctly with custom actor id', function (): void {
            // Arrange
            $org = Organization::query()->create(['name' => 'Acme']);
            $role = Role::query()->create(['name' => 'admin']);
            $warden = self::bouncer($org);
            $warden->assign('admin')->to($org);

            // Act
            $warden->retract('admin')->from($org);

            // Assert
            $assignment = $this->db()
                ->table('assigned_roles')
                ->where('role_id', $role->id)
                ->where('actor_type', $org->getMorphClass())
                ->where('actor_id', $org->getKey())
                ->first();

            expect($assignment)->toBeNull();
        });

        test('handles mixed key types in bulk operations', function (): void {
            // Arrange
            $user1 = User::query()->create(['name' => 'John']);
            $user2 = User::query()->create(['name' => 'Jane']);
            $org1 = Organization::query()->create(['name' => 'Acme']);
            $org2 = Organization::query()->create(['name' => 'TechCorp']);
            $role = Role::query()->create(['name' => 'editor']);
            $warden = self::bouncer();

            // Act
            $warden->assign('editor')->to([$user1, $user2, $org1, $org2]);

            // Assert
            $userAssignments = $this->db()
                ->table('assigned_roles')
                ->where('role_id', $role->id)
                ->where('actor_type', User::class)
                ->get();

            $orgAssignments = $this->db()
                ->table('assigned_roles')
                ->where('role_id', $role->id)
                ->where('actor_type', Organization::class)
                ->get();

            expect($userAssignments)->toHaveCount(2);
            expect($orgAssignments)->toHaveCount(2);
            expect($userAssignments->pluck('actor_id')->map(fn ($id): string => (string) $id)->toArray())->toContain((string) $user1->id);
            expect($userAssignments->pluck('actor_id')->map(fn ($id): string => (string) $id)->toArray())->toContain((string) $user2->id);
            expect($orgAssignments->pluck('actor_id')->map(fn ($id): string => (string) $id)->toArray())->toContain((string) $org1->getKey());
            expect($orgAssignments->pluck('actor_id')->map(fn ($id): string => (string) $id)->toArray())->toContain((string) $org2->getKey());
        });
    });
});
