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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Models\User;

test('auto generates title when creating role', function (): void {
    // Arrange
    $roleName = 'super_admin';

    // Act
    $role = Role::query()->create(['name' => $roleName]);

    // Assert
    expect($role->title)->not->toBeNull();
    expect($role->title)->toEqual('Super admin');
})->group('happy-path');
test('preserves explicit title when provided', function (): void {
    // Arrange
    $roleName = 'admin';
    $explicitTitle = 'Custom Admin Title';

    // Act
    $role = Role::query()->create(['name' => $roleName, 'title' => $explicitTitle]);

    // Assert
    expect($role->title)->toEqual($explicitTitle);
})->group('edge-case');
test('detaches abilities when role deleted', function (): void {
    // Arrange
    $role = Role::query()->create(['name' => 'admin']);
    $ability = Ability::query()->create(['name' => 'edit-posts']);
    $role->abilities()->attach($ability);

    // Act
    $role->delete();

    // Assert
    $this->assertDatabaseMissing(Models::table('permissions'), [
        'actor_type' => $role->getMorphClass(),
        'actor_id' => $role->id,
    ]);
})->group('happy-path');
test('users relationship includes assigned users', function (): void {
    // Arrange
    config()->set('warden.user_model', User::class);
    $role = Role::query()->create(['name' => 'admin']);
    $user1 = User::query()->create(['name' => 'Alice']);
    $user2 = User::query()->create(['name' => 'Bob']);
    $role->assignTo($user1);

    // Act
    $users = $role->users()->get();

    // Assert
    expect($users)->toHaveCount(1);
    expect($users->contains('id', $user1->id))->toBeTrue();
    expect($users->contains('id', $user2->id))->toBeFalse();
})->group('happy-path');
test('assigns role to single user instance', function (): void {
    // Arrange
    $role = Role::query()->create(['name' => 'admin']);
    $user = User::query()->create(['name' => 'John Doe']);

    // Act
    $result = $role->assignTo($user);

    // Assert
    expect($result)->toBe($role);
    $this->assertDatabaseHas(Models::table('assigned_roles'), [
        'role_id' => $role->id,
        'actor_type' => $user->getMorphClass(),
        'actor_id' => $user->id,
    ]);
})->group('happy-path');
test('assigns role to multiple users via class and keys', function (): void {
    // Arrange
    $role = Role::query()->create(['name' => 'editor']);
    $user1 = User::query()->create(['name' => 'Alice']);
    $user2 = User::query()->create(['name' => 'Bob']);

    // Act
    $role->assignTo(User::class, [$user1->id, $user2->id]);

    // Assert
    $this->assertDatabaseHas(Models::table('assigned_roles'), [
        'role_id' => $role->id,
        'actor_id' => $user1->id,
    ]);
    $this->assertDatabaseHas(Models::table('assigned_roles'), [
        'role_id' => $role->id,
        'actor_id' => $user2->id,
    ]);
})->group('happy-path');
test('assigns role to class name with array of ids', function (): void {
    // Arrange
    $role = Role::query()->create(['name' => 'moderator']);
    $user1 = User::query()->create(['name' => 'Charlie']);
    $user2 = User::query()->create(['name' => 'Diana']);

    // Act
    $role->assignTo(User::class, [$user1->id, $user2->id]);

    // Assert
    $this->assertDatabaseHas(Models::table('assigned_roles'), [
        'role_id' => $role->id,
        'actor_id' => $user1->id,
    ]);
    $this->assertDatabaseHas(Models::table('assigned_roles'), [
        'role_id' => $role->id,
        'actor_id' => $user2->id,
    ]);
})->group('happy-path');
test('finds existing roles by ids', function (): void {
    // Arrange
    $role1 = Role::query()->create(['name' => 'admin']);
    $role2 = Role::query()->create(['name' => 'editor']);
    $roleInstance = new Role();

    // Act
    $roles = $roleInstance->findOrCreateRoles([$role1->id, $role2->id]);

    // Assert
    expect($roles)->toHaveCount(2);
    expect($roles->contains('id', $role1->id))->toBeTrue();
    expect($roles->contains('id', $role2->id))->toBeTrue();
})->group('happy-path');
test('finds existing roles by name', function (): void {
    // Arrange
    Role::query()->create(['name' => 'viewer']);
    Role::query()->create(['name' => 'contributor']);
    $roleInstance = new Role();

    // Act
    $roles = $roleInstance->findOrCreateRoles(['viewer', 'contributor']);

    // Assert
    expect($roles)->toHaveCount(2);
    expect($roles->contains('name', 'viewer'))->toBeTrue();
    expect($roles->contains('name', 'contributor'))->toBeTrue();
})->group('happy-path');
test('creates missing roles by name', function (): void {
    // Arrange
    Role::query()->create(['name' => 'existing-role']);
    $roleInstance = new Role();

    // Act
    $roles = $roleInstance->findOrCreateRoles(['existing-role', 'new-role']);

    // Assert
    expect($roles)->toHaveCount(2);
    expect($roles->contains('name', 'existing-role'))->toBeTrue();
    expect($roles->contains('name', 'new-role'))->toBeTrue();
    $this->assertDatabaseHas(Models::table('roles'), ['name' => 'new-role']);
})->group('happy-path');
test('returns collection of found and created roles', function (): void {
    // Arrange
    $existingRole = Role::query()->create(['name' => 'admin']);
    $roleInstance = new Role();

    // Act
    $roles = $roleInstance->findOrCreateRoles([
        $existingRole->id,
        'new-role-1',
        'new-role-2',
    ]);

    // Assert
    expect($roles)->toHaveCount(3);
    expect($roles)->toBeInstanceOf(Collection::class);
})->group('happy-path');
test('extracts keys from role ids', function (): void {
    // Arrange
    $role1 = Role::query()->create(['name' => 'role1']);
    $role2 = Role::query()->create(['name' => 'role2']);
    $roleInstance = new Role();

    // Act
    $keys = $roleInstance->getRoleKeys([$role1->id, $role2->id]);

    // Assert
    expect($keys)->toHaveCount(2);
    expect($keys)->toContain($role1->id);
    expect($keys)->toContain($role2->id);
})->group('happy-path');
test('extracts keys from role names', function (): void {
    // Arrange
    $role1 = Role::query()->create(['name' => 'manager']);
    $role2 = Role::query()->create(['name' => 'supervisor']);
    $roleInstance = new Role();

    // Act
    $keys = $roleInstance->getRoleKeys(['manager', 'supervisor']);

    // Assert
    expect($keys)->toHaveCount(2);
    expect($keys)->toContain($role1->id);
    expect($keys)->toContain($role2->id);
})->group('happy-path');
test('extracts keys from role instances', function (): void {
    // Arrange
    $role1 = Role::query()->create(['name' => 'analyst']);
    $role2 = Role::query()->create(['name' => 'developer']);
    $roleInstance = new Role();

    // Act
    $keys = $roleInstance->getRoleKeys([$role1, $role2]);

    // Assert
    expect($keys)->toHaveCount(2);
    expect($keys)->toContain($role1->id);
    expect($keys)->toContain($role2->id);
})->group('happy-path');
test('handles mixed identifier types for keys extraction', function (): void {
    // Arrange
    $role1 = Role::query()->create(['name' => 'tester']);
    $role2 = Role::query()->create(['name' => 'architect']);
    $role3 = Role::query()->create(['name' => 'consultant']);
    $roleInstance = new Role();

    // Act
    $keys = $roleInstance->getRoleKeys([$role1->id, 'architect', $role3]);

    // Assert
    expect($keys)->toHaveCount(3);
    expect($keys)->toContain($role1->id);
    expect($keys)->toContain($role2->id);
    expect($keys)->toContain($role3->id);
})->group('edge-case');
test('extracts names from role ids', function (): void {
    // Arrange
    $role1 = Role::query()->create(['name' => 'publisher']);
    $role2 = Role::query()->create(['name' => 'subscriber']);
    $roleInstance = new Role();

    // Act
    $names = $roleInstance->getRoleNames([$role1->id, $role2->id]);

    // Assert
    expect($names)->toHaveCount(2);
    expect($names)->toContain('publisher');
    expect($names)->toContain('subscriber');
})->group('happy-path');
test('extracts names from role names', function (): void {
    // Arrange
    Role::query()->create(['name' => 'guest']);
    Role::query()->create(['name' => 'member']);
    $roleInstance = new Role();

    // Act
    $names = $roleInstance->getRoleNames(['guest', 'member']);

    // Assert
    expect($names)->toHaveCount(2);
    expect($names)->toContain('guest');
    expect($names)->toContain('member');
})->group('happy-path');
test('extracts names from role instances', function (): void {
    // Arrange
    $role1 = Role::query()->create(['name' => 'coordinator']);
    $role2 = Role::query()->create(['name' => 'facilitator']);
    $roleInstance = new Role();

    // Act
    $names = $roleInstance->getRoleNames([$role1, $role2]);

    // Assert
    expect($names)->toHaveCount(2);
    expect($names)->toContain('coordinator');
    expect($names)->toContain('facilitator');
})->group('happy-path');
test('handles mixed identifier types for names extraction', function (): void {
    // Arrange
    $role1 = Role::query()->create(['name' => 'auditor']);
    Role::query()->create(['name' => 'reviewer']);
    $role3 = Role::query()->create(['name' => 'approver']);
    $roleInstance = new Role();

    // Act
    $names = $roleInstance->getRoleNames([$role1->id, 'reviewer', $role3]);

    // Assert
    expect($names)->toHaveCount(3);
    expect($names)->toContain('auditor');
    expect($names)->toContain('reviewer');
    expect($names)->toContain('approver');
})->group('edge-case');
test('queries role ids by names', function (): void {
    // Arrange
    $role1 = Role::query()->create(['name' => 'operator']);
    $role2 = Role::query()->create(['name' => 'technician']);
    $roleInstance = new Role();

    // Act
    $keys = $roleInstance->getKeysByName(['operator', 'technician']);

    // Assert
    expect($keys)->toHaveCount(2);
    expect($keys)->toContain($role1->id);
    expect($keys)->toContain($role2->id);
})->group('happy-path');
test('returns empty array when get keys by name receives empty input', function (): void {
    // Arrange
    $roleInstance = new Role();

    // Act
    $keys = $roleInstance->getKeysByName([]);

    // Assert
    expect($keys)->toBeEmpty();
    expect($keys)->toBeArray();
})->group('sad-path');
test('queries role names by ids', function (): void {
    // Arrange
    $role1 = Role::query()->create(['name' => 'specialist']);
    $role2 = Role::query()->create(['name' => 'expert']);
    $roleInstance = new Role();

    // Act
    $names = $roleInstance->getNamesByKey([$role1->id, $role2->id]);

    // Assert
    expect($names)->toHaveCount(2);
    expect($names)->toContain('specialist');
    expect($names)->toContain('expert');
})->group('happy-path');
test('returns empty array when get names by key receives empty input', function (): void {
    // Arrange
    $roleInstance = new Role();

    // Act
    $names = $roleInstance->getNamesByKey([]);

    // Assert
    expect($names)->toBeEmpty();
    expect($names)->toBeArray();
})->group('sad-path');
test('removes role from single user instance', function (): void {
    // Arrange
    $role = Role::query()->create(['name' => 'admin']);
    $user = User::query()->create(['name' => 'Jane Smith']);
    $role->assignTo($user);

    // Act
    $result = $role->retractFrom($user);

    // Assert
    expect($result)->toBe($role);
    $this->assertDatabaseMissing(Models::table('assigned_roles'), [
        'role_id' => $role->id,
        'actor_id' => $user->id,
    ]);
})->group('happy-path');
test('removes role from multiple users via class and keys', function (): void {
    // Arrange
    $role = Role::query()->create(['name' => 'editor']);
    $user1 = User::query()->create(['name' => 'Emma']);
    $user2 = User::query()->create(['name' => 'Frank']);
    $role->assignTo(User::class, [$user1->id, $user2->id]);

    // Act
    $role->retractFrom(User::class, [$user1->id, $user2->id]);

    // Assert
    $this->assertDatabaseMissing(Models::table('assigned_roles'), [
        'role_id' => $role->id,
        'actor_id' => $user1->id,
    ]);
    $this->assertDatabaseMissing(Models::table('assigned_roles'), [
        'role_id' => $role->id,
        'actor_id' => $user2->id,
    ]);
})->group('happy-path');
test('removes role from class name with array of ids', function (): void {
    // Arrange
    $role = Role::query()->create(['name' => 'moderator']);
    $user1 = User::query()->create(['name' => 'George']);
    $user2 = User::query()->create(['name' => 'Hannah']);
    $role->assignTo(User::class, [$user1->id, $user2->id]);

    // Act
    $role->retractFrom(User::class, [$user1->id, $user2->id]);

    // Assert
    $this->assertDatabaseMissing(Models::table('assigned_roles'), [
        'role_id' => $role->id,
        'actor_id' => $user1->id,
    ]);
    $this->assertDatabaseMissing(Models::table('assigned_roles'), [
        'role_id' => $role->id,
        'actor_id' => $user2->id,
    ]);
})->group('happy-path');
test('handles retraction from non assigned users gracefully', function (): void {
    // Arrange
    $role = Role::query()->create(['name' => 'viewer']);
    $user = User::query()->create(['name' => 'Isaac']);

    // Act
    $result = $role->retractFrom($user);

    // Assert
    expect($result)->toBe($role);
    $this->assertDatabaseMissing(Models::table('assigned_roles'), [
        'role_id' => $role->id,
        'actor_id' => $user->id,
    ]);
})->group('edge-case');
test('constrains roles query by assigned user', function (): void {
    // Arrange
    $user = User::query()->create(['name' => 'Jack']);
    $role1 = Role::query()->create(['name' => 'admin']);
    $role2 = Role::query()->create(['name' => 'editor']);
    Role::query()->create(['name' => 'viewer']);
    $role1->assignTo($user);
    $role2->assignTo($user);

    // Act
    $roles = Role::whereAssignedTo($user)->get();

    // Assert
    expect($roles)->toHaveCount(2);
    expect($roles->contains('name', 'admin'))->toBeTrue();
    expect($roles->contains('name', 'editor'))->toBeTrue();
    expect($roles->contains('name', 'viewer'))->toBeFalse();
})->group('happy-path');
test('constrains roles query by class name and multiple ids', function (): void {
    // Arrange
    $user1 = User::query()->create(['name' => 'Kate']);
    $user2 = User::query()->create(['name' => 'Liam']);
    $role1 = Role::query()->create(['name' => 'manager']);
    $role2 = Role::query()->create(['name' => 'staff']);
    Role::query()->create(['name' => 'guest']);
    $role1->assignTo($user1);
    $role2->assignTo($user2);

    // Act
    $roles = Role::whereAssignedTo(User::class, [$user1->id, $user2->id])->get();

    // Assert
    expect($roles)->toHaveCount(2);
    expect($roles->contains('name', 'manager'))->toBeTrue();
    expect($roles->contains('name', 'staff'))->toBeTrue();
    expect($roles->contains('name', 'guest'))->toBeFalse();
})->group('happy-path');
test('constrains roles query by class name and ids', function (): void {
    // Arrange
    $user1 = User::query()->create(['name' => 'Mike']);
    $user2 = User::query()->create(['name' => 'Nancy']);
    $role1 = Role::query()->create(['name' => 'owner']);
    $role2 = Role::query()->create(['name' => 'collaborator']);
    Role::query()->create(['name' => 'reader']);
    $role1->assignTo($user1);
    $role2->assignTo($user2);

    // Act
    $roles = Role::whereAssignedTo(User::class, [$user1->id, $user2->id])->get();

    // Assert
    expect($roles)->toHaveCount(2);
    expect($roles->contains('name', 'owner'))->toBeTrue();
    expect($roles->contains('name', 'collaborator'))->toBeTrue();
    expect($roles->contains('name', 'reader'))->toBeFalse();
})->group('happy-path');
test('users relationship is properly configured', function (): void {
    // Arrange
    config()->set('warden.user_model', User::class);
    $role = Role::query()->create(['name' => 'admin']);
    $user = User::query()->create(['name' => 'Test User']);
    $role->assignTo($user);

    // Act
    $relation = $role->users();
    $users = $relation->get();

    // Assert
    expect($relation)->toBeInstanceOf(MorphToMany::class);
    expect($users)->toHaveCount(1);
    expect($users->first()->id)->toEqual($user->id);
})->group('happy-path');
test('assigns role to multiple users via array', function (): void {
    // Arrange
    $role = Role::query()->create(['name' => 'admin']);
    $user1 = User::query()->create(['name' => 'User 1']);
    $user2 = User::query()->create(['name' => 'User 2']);

    // Act
    $role->assignTo($user1->getMorphClass(), [$user1->id, $user2->id]);

    // Assert
    $this->assertDatabaseHas(Models::table('assigned_roles'), [
        'role_id' => $role->id,
        'actor_id' => $user1->id,
    ]);
    $this->assertDatabaseHas(Models::table('assigned_roles'), [
        'role_id' => $role->id,
        'actor_id' => $user2->id,
    ]);
})->group('happy-path');
test('retrieves role names from mixed identifiers', function (): void {
    // Arrange
    $role1 = Role::query()->create(['name' => 'manager']);
    $role2 = Role::query()->create(['name' => 'employee']);
    $roleInstance = new Role();

    // Act
    $names = $roleInstance->getRoleNames([$role1->id, $role2]);

    // Assert
    expect($names)->toHaveCount(2);
    expect($names)->toContain('manager');
    expect($names)->toContain('employee');
})->group('happy-path');
test('queries names by keys with empty array', function (): void {
    // Arrange
    $roleInstance = new Role();

    // Act
    $names = $roleInstance->getNamesByKey([]);

    // Assert
    expect($names)->toBeEmpty();
})->group('edge-case');
test('retrieves role names from role ids', function (): void {
    // Arrange
    $role1 = Role::query()->create(['name' => 'supervisor']);
    $role2 = Role::query()->create(['name' => 'worker']);
    $roleInstance = new Role();

    // Act
    $names = $roleInstance->getRoleNames([$role1->id, $role2->id]);

    // Assert
    expect($names)->toHaveCount(2);
    expect($names)->toContain('supervisor');
    expect($names)->toContain('worker');
})->group('happy-path');
test('retracts role from multiple users via array', function (): void {
    // Arrange
    $role = Role::query()->create(['name' => 'editor']);
    $user1 = User::query()->create(['name' => 'User 1']);
    $user2 = User::query()->create(['name' => 'User 2']);
    $role->assignTo($user1->getMorphClass(), [$user1->id, $user2->id]);

    // Act
    $role->retractFrom($user1->getMorphClass(), [$user1->id, $user2->id]);

    // Assert
    $this->assertDatabaseMissing(Models::table('assigned_roles'), [
        'role_id' => $role->id,
        'actor_id' => $user1->id,
    ]);
    $this->assertDatabaseMissing(Models::table('assigned_roles'), [
        'role_id' => $role->id,
        'actor_id' => $user2->id,
    ]);
})->group('happy-path');
test('creates assign records with scope attributes', function (): void {
    // Arrange
    Models::scope()->to(42);
    $role = Role::query()->create(['name' => 'admin']);
    $user1 = User::query()->create(['name' => 'User 1']);
    $user2 = User::query()->create(['name' => 'User 2']);

    // Act
    $role->assignTo(User::class, [$user1->id, $user2->id]);

    // Assert
    $this->assertDatabaseHas(Models::table('assigned_roles'), [
        'role_id' => $role->id,
        'actor_id' => $user1->id,
        'scope' => 42,
    ]);
    $this->assertDatabaseHas(Models::table('assigned_roles'), [
        'role_id' => $role->id,
        'actor_id' => $user2->id,
        'scope' => 42,
    ]);

    // Cleanup
    Models::scope()->to(null);
})->group('happy-path');
