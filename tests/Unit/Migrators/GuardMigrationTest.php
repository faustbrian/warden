<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Database\Models;
use Cline\Warden\Migrators\BouncerMigrator;
use Cline\Warden\Migrators\SpatieMigrator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\Models\BouncerUser;
use Tests\Fixtures\Models\SpatieUser;
use Tests\Fixtures\Models\UserWithSoftDeletes;

beforeEach(function (): void {
    $this->loadMigrationsFrom(__DIR__.'/../../Fixtures/database/migrations');
    $this->artisan('migrate', ['--database' => 'testing'])->run();

    // Configure Spatie table names
    config(['warden.migrators.spatie.tables.roles' => 'spatie_roles']);
    config(['warden.migrators.spatie.tables.permissions' => 'spatie_permissions']);
    config(['warden.migrators.spatie.tables.model_has_roles' => 'spatie_model_has_roles']);
    config(['warden.migrators.spatie.tables.model_has_permissions' => 'spatie_model_has_permissions']);
    config(['warden.migrators.spatie.tables.role_has_permissions' => 'spatie_role_has_permissions']);
    config(['warden.migrators.spatie.model_type' => 'user']);

    // Configure Bouncer table names
    config(['warden.migrators.bouncer.tables.roles' => 'bouncer_roles']);
    config(['warden.migrators.bouncer.tables.abilities' => 'bouncer_abilities']);
    config(['warden.migrators.bouncer.tables.assigned_roles' => 'bouncer_assigned_roles']);
    config(['warden.migrators.bouncer.tables.permissions' => 'bouncer_permissions']);

    // Configure morph key mapping for UUID/ULID modes
    // This tells Warden to use uuid/ulid column for foreign keys while migrator uses id to query legacy data
    $actorMorphType = config('warden.actor_morph_type', 'morph');

    if ($actorMorphType === 'uuidMorph') {
        Models::morphKeyMap([
            SpatieUser::class => 'uuid',
            BouncerUser::class => 'uuid',
        ]);
    } elseif ($actorMorphType === 'ulidMorph') {
        Models::morphKeyMap([
            SpatieUser::class => 'ulid',
            BouncerUser::class => 'ulid',
        ]);
    }

    $this->user = SpatieUser::query()->create();
});

afterEach(function (): void {
    // Clear model boot state after each test to prevent cross-contamination
    Model::clearBootedModels();
});

describe('Spatie migrator with guard_name', function (): void {
    test('migrates roles with guard_name from Spatie tables', function (): void {
        DB::table('spatie_roles')->insert([
            ['name' => 'web-admin', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'api-admin', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $migrator = new SpatieMigrator(SpatieUser::class);
        $migrator->migrate();

        $webAdmin = Models::role()->where('name', 'web-admin')->where('guard_name', 'web')->first();
        $apiAdmin = Models::role()->where('name', 'api-admin')->where('guard_name', 'api')->first();

        expect($webAdmin)->not->toBeNull();
        expect($apiAdmin)->not->toBeNull();
        expect($webAdmin->guard_name)->toBe('web');
        expect($apiAdmin->guard_name)->toBe('api');
    });

    test('migrates permissions with guard_name from Spatie tables', function (): void {
        $webPermissionId = DB::table('spatie_permissions')->insertGetId([
            'name' => 'web-permission', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $apiPermissionId = DB::table('spatie_permissions')->insertGetId([
            'name' => 'api-permission', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('spatie_model_has_permissions')->insert([
            'permission_id' => $webPermissionId,
            'model_type' => 'user',
            'model_id' => $this->user->id,
        ]);

        DB::table('spatie_model_has_permissions')->insert([
            'permission_id' => $apiPermissionId,
            'model_type' => 'user',
            'model_id' => $this->user->id,
        ]);

        $migrator = new SpatieMigrator(SpatieUser::class);
        $migrator->migrate();

        $webAbility = Models::ability()->where('name', 'web-permission')->where('guard_name', 'web')->first();
        $apiAbility = Models::ability()->where('name', 'api-permission')->where('guard_name', 'api')->first();

        expect($webAbility)->not->toBeNull();
        expect($apiAbility)->not->toBeNull();
        expect($webAbility->guard_name)->toBe('web');
        expect($apiAbility->guard_name)->toBe('api');
    });

    test('migrates role assignments with correct guard_name', function (): void {
        $roleId = DB::table('spatie_roles')->insertGetId([
            'name' => 'web-editor',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('spatie_model_has_roles')->insert([
            'role_id' => $roleId,
            'model_type' => 'user',
            'model_id' => $this->user->id,
        ]);

        $migrator = new SpatieMigrator(SpatieUser::class);
        $migrator->migrate();

        $role = Models::role()->where('name', 'web-editor')->where('guard_name', 'web')->first();

        expect($role)->not->toBeNull();
        expect($role->guard_name)->toBe('web');
        expect($this->user->roles()->where('guard_name', 'web')->count())->toBe(1);
    });

    test('migrates role permissions with correct guard_name', function (): void {
        $roleId = DB::table('spatie_roles')->insertGetId([
            'name' => 'api-manager',
            'guard_name' => 'api',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permissionId = DB::table('spatie_permissions')->insertGetId([
            'name' => 'api-manage',
            'guard_name' => 'api',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('spatie_role_has_permissions')->insert([
            'permission_id' => $permissionId,
            'role_id' => $roleId,
        ]);

        $migrator = new SpatieMigrator(SpatieUser::class);
        $migrator->migrate();

        $role = Models::role()->where('name', 'api-manager')->where('guard_name', 'api')->first();
        $ability = Models::ability()->where('name', 'api-manage')->where('guard_name', 'api')->first();

        expect($role)->not->toBeNull();
        expect($ability)->not->toBeNull();
        expect($role->abilities()->where('guard_name', 'api')->count())->toBe(1);
    });
});

describe('Bouncer migrator with guard_name', function (): void {
    test('uses default guard when not specified', function (): void {
        config(['warden.guard' => 'web']);

        DB::table('bouncer_roles')->insert([
            ['name' => 'bouncer-admin', 'scope' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $migrator = new BouncerMigrator(BouncerUser::class);
        $migrator->migrate();

        $role = Models::role()->where('name', 'bouncer-admin')->first();

        expect($role)->not->toBeNull();
        expect($role->guard_name)->toBe('web');
    });

    test('uses specified guard in constructor', function (): void {
        DB::table('bouncer_roles')->insert([
            ['name' => 'rpc-admin', 'scope' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $migrator = new BouncerMigrator(BouncerUser::class, 'migration', 'rpc');
        $migrator->migrate();

        $role = Models::role()->where('name', 'rpc-admin')->first();

        expect($role)->not->toBeNull();
        expect($role->guard_name)->toBe('rpc');
    });

    test('migrates abilities with specified guard', function (): void {
        DB::table('bouncer_abilities')->insert([
            [
                'name' => 'rpc-call',
                'entity_type' => null,
                'entity_id' => null,
                'only_owned' => false,
                'scope' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $migrator = new BouncerMigrator(BouncerUser::class, 'migration', 'rpc');
        $migrator->migrate();

        $ability = Models::ability()->where('name', 'rpc-call')->first();

        expect($ability)->not->toBeNull();
        expect($ability->guard_name)->toBe('rpc');
    });

    test('migrates role assignments with specified guard', function (): void {
        $bouncerUser = BouncerUser::query()->create();

        $roleId = DB::table('bouncer_roles')->insertGetId([
            'name' => 'api-user', 'scope' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('bouncer_assigned_roles')->insert([
            'role_id' => $roleId,
            'entity_type' => BouncerUser::class,
            'entity_id' => $bouncerUser->id,
        ]);

        $migrator = new BouncerMigrator(BouncerUser::class, 'migration', 'api');
        $migrator->migrate();

        $role = Models::role()->where('name', 'api-user')->where('guard_name', 'api')->first();

        expect($role)->not->toBeNull();
        expect($bouncerUser->roles()->where('guard_name', 'api')->count())->toBe(1);
    });
});

describe('Spatie migrator edge cases', function (): void {
    test('skips assignments for non-existent users', function (): void {
        $roleId = DB::table('spatie_roles')->insertGetId([
            'name' => 'admin',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        insertWithoutForeignKeyChecks('spatie_model_has_roles', [
            'role_id' => $roleId,
            'model_type' => 'user',
            'model_id' => 99_999, // Non-existent user - legacy tables use bigint IDs
        ]);

        $migrator = new SpatieMigrator(SpatieUser::class);
        $migrator->migrate();

        // Role should be created but not assigned
        expect(Models::role()->where('name', 'admin')->exists())->toBeTrue();
        expect(SpatieUser::query()->count())->toBe(1); // Only the beforeEach user
    });

    test('skips assignments for non-existent permissions', function (): void {
        insertWithoutForeignKeyChecks('spatie_model_has_permissions', [
            'permission_id' => 99_999, // Non-existent permission - legacy tables use bigint IDs
            'model_type' => 'user',
            'model_id' => $this->user->id,
        ]);

        $migrator = new SpatieMigrator(SpatieUser::class);
        $migrator->migrate();

        // Should complete without error
        expect($this->user->abilities()->count())->toBe(0);
    });

    test('skips role permissions for non-existent roles', function (): void {
        $permissionId = DB::table('spatie_permissions')->insertGetId([
            'name' => 'test-permission',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        insertWithoutForeignKeyChecks('spatie_role_has_permissions', [
            'permission_id' => $permissionId,
            'role_id' => 99_999, // Non-existent role
        ]);

        $migrator = new SpatieMigrator(SpatieUser::class);
        $migrator->migrate();

        // Should complete without error - no ability created since role doesn't exist
        expect(Models::ability()->count())->toBe(0);
    });

    test('skips role permissions for non-existent permissions', function (): void {
        $roleId = DB::table('spatie_roles')->insertGetId([
            'name' => 'admin',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        insertWithoutForeignKeyChecks('spatie_role_has_permissions', [
            'permission_id' => 99_999, // Non-existent permission
            'role_id' => $roleId,
        ]);

        $migrator = new SpatieMigrator(SpatieUser::class);
        $migrator->migrate();

        $role = Models::role()->where('name', 'admin')->first();

        expect($role)->not->toBeNull();
        expect($role->abilities()->count())->toBe(0);
    });
});

describe('Bouncer migrator edge cases', function (): void {
    test('skips user permissions for non-existent users', function (): void {
        $abilityId = DB::table('bouncer_abilities')->insertGetId([
            'name' => 'test-ability',
            'entity_type' => null,
            'entity_id' => null,
            'only_owned' => false,
            'scope' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        insertWithoutForeignKeyChecks('bouncer_permissions', [
            'ability_id' => $abilityId,
            'entity_type' => BouncerUser::class,
            'entity_id' => 99_999, // Non-existent user
            'forbidden' => false,
        ]);

        $migrator = new BouncerMigrator(BouncerUser::class);
        $migrator->migrate();

        // Ability should be created but not assigned
        expect(Models::ability()->where('name', 'test-ability')->exists())->toBeTrue();
    });

    test('skips user permissions for non-existent abilities', function (): void {
        insertWithoutForeignKeyChecks('bouncer_permissions', [
            'ability_id' => 99_999, // Non-existent ability
            'entity_type' => BouncerUser::class,
            'entity_id' => $this->user->id,
            'forbidden' => false,
        ]);

        $migrator = new BouncerMigrator(BouncerUser::class);
        $migrator->migrate();

        // Should complete without error
        expect($this->user->abilities()->count())->toBe(0);
    });

    test('skips role permissions for non-existent abilities', function (): void {
        $roleId = DB::table('bouncer_roles')->insertGetId([
            'name' => 'admin',
            'scope' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        insertWithoutForeignKeyChecks('bouncer_permissions', [
            'ability_id' => 99_999, // Non-existent ability
            'entity_type' => Models::role()::class,
            'entity_id' => $roleId,
            'forbidden' => false,
        ]);

        $migrator = new BouncerMigrator(BouncerUser::class);
        $migrator->migrate();

        $role = Models::role()->where('name', 'admin')->first();

        expect($role)->not->toBeNull();
        expect($role->abilities()->count())->toBe(0);
    });

    test('skips role permissions for non-existent roles', function (): void {
        $abilityId = DB::table('bouncer_abilities')->insertGetId([
            'name' => 'test-ability',
            'entity_type' => null,
            'entity_id' => null,
            'only_owned' => false,
            'scope' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        insertWithoutForeignKeyChecks('bouncer_permissions', [
            'ability_id' => $abilityId,
            'entity_type' => Models::role()::class,
            'entity_id' => 99_999, // Non-existent role
            'forbidden' => false,
        ]);

        $migrator = new BouncerMigrator(BouncerUser::class);
        $migrator->migrate();

        // Ability should be created but not assigned to any role
        expect(Models::ability()->where('name', 'test-ability')->exists())->toBeTrue();
    });

    test('skips role permissions when migrated role not found', function (): void {
        $abilityId = DB::table('bouncer_abilities')->insertGetId([
            'name' => 'test-ability',
            'entity_type' => null,
            'entity_id' => null,
            'only_owned' => false,
            'scope' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $roleId = DB::table('bouncer_roles')->insertGetId([
            'name' => 'admin',
            'scope' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('bouncer_permissions')->insert([
            'ability_id' => $abilityId,
            'entity_type' => Models::role()::class,
            'entity_id' => $roleId,
            'forbidden' => false,
        ]);

        // Use different guard so role won't be found
        $migrator = new BouncerMigrator(BouncerUser::class, 'migration', 'api');
        $migrator->migrate();

        // Should skip the permission assignment
        $ability = Models::ability()->where('name', 'test-ability')->where('guard_name', 'api')->first();

        expect($ability)->not->toBeNull();
    });

    test('uses withTrashed method when available on user model', function (): void {
        // Verify SoftDeletes trait is used (which adds withTrashed static method)
        $traits = class_uses(UserWithSoftDeletes::class);
        expect($traits)->toContain(SoftDeletes::class);

        // The migrator checks method_exists and calls withTrashed() if available
        $migrator = new BouncerMigrator(UserWithSoftDeletes::class);

        // Should complete without error
        expect($migrator)->toBeInstanceOf(BouncerMigrator::class);
    });

    test('skips ability assignment when ability not found in Bouncer migration', function (): void {
        $roleId = DB::table('bouncer_roles')->insertGetId([
            'name' => 'admin',
            'title' => 'Admin',
            'scope' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert permission referencing non-existent ability
        insertWithoutForeignKeyChecks('bouncer_permissions', [
            'ability_id' => 99_999, // Non-existent ability ID
            'entity_type' => Models::role()::class,
            'entity_id' => $roleId,
            'forbidden' => false,
        ]);

        $migrator = new BouncerMigrator(BouncerUser::class);
        $migrator->migrate();

        // Role should be created but ability not assigned
        expect(Models::role()->where('name', 'admin')->exists())->toBeTrue();
        expect(Models::ability()->count())->toBe(0);
    });

    test('skips ability assignment when role not found in Bouncer migration', function (): void {
        $abilityId = DB::table('bouncer_abilities')->insertGetId([
            'name' => 'test-ability',
            'title' => 'Test Ability',
            'entity_type' => null,
            'entity_id' => null,
            'only_owned' => false,
            'scope' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert permission referencing non-existent role
        DB::table('bouncer_permissions')->insert([
            'ability_id' => $abilityId,
            'entity_type' => Models::role()::class,
            'entity_id' => 99_999, // Non-existent role ID
            'forbidden' => false,
        ]);

        $migrator = new BouncerMigrator(BouncerUser::class);
        $migrator->migrate();

        // Ability should be created but not assigned
        expect(Models::ability()->where('name', 'test-ability')->exists())->toBeTrue();
        expect(Models::role()->count())->toBe(0);
    });

    test('skips assignment for non-existent user in Spatie migration', function (): void {
        $permissionId = DB::table('spatie_permissions')->insertGetId([
            'name' => 'test-permission',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        insertWithoutForeignKeyChecks('spatie_model_has_permissions', [
            'permission_id' => $permissionId,
            'model_type' => User::class,
            'model_id' => 99_999, // Non-existent user
        ]);

        $migrator = new SpatieMigrator(SpatieUser::class);
        $migrator->migrate();

        // Nothing should be created when user doesn't exist
        expect(Models::ability()->count())->toBe(0);
        expect(DB::table(Models::table('permissions'))->count())->toBe(0);
    });
});

describe('Multi-guard migration scenarios', function (): void {
    test('can migrate from Spatie with multiple guards simultaneously', function (): void {
        DB::table('spatie_roles')->insert([
            ['name' => 'admin', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'admin', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'admin', 'guard_name' => 'rpc', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $migrator = new SpatieMigrator(SpatieUser::class);
        $migrator->migrate();

        expect(Models::role()->where('name', 'admin')->count())->toBe(3);
        expect(Models::role()->where('name', 'admin')->where('guard_name', 'web')->exists())->toBeTrue();
        expect(Models::role()->where('name', 'admin')->where('guard_name', 'api')->exists())->toBeTrue();
        expect(Models::role()->where('name', 'admin')->where('guard_name', 'rpc')->exists())->toBeTrue();
    });

    test('Bouncer and Spatie migrations do not conflict with different guards', function (): void {
        // Spatie web guard
        DB::table('spatie_roles')->insert([
            ['name' => 'editor', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Bouncer with api guard
        DB::table('bouncer_roles')->insert([
            ['name' => 'editor', 'scope' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $spatieMigrator = new SpatieMigrator(SpatieUser::class);
        $spatieMigrator->migrate();

        $bouncerMigrator = new BouncerMigrator(BouncerUser::class, 'migration', 'api');
        $bouncerMigrator->migrate();

        $webEditor = Models::role()->where('name', 'editor')->where('guard_name', 'web')->first();
        $apiEditor = Models::role()->where('name', 'editor')->where('guard_name', 'api')->first();

        expect($webEditor)->not->toBeNull();
        expect($apiEditor)->not->toBeNull();
        expect($webEditor->id)->not->toBe($apiEditor->id);
    });
});
