# Migrating from Other Packages

Warden provides migrators to help you transition from other popular Laravel permission packages. These migrators preserve all your existing roles, abilities, permissions, and assignments while converting them to Warden's schema.

## Supported Packages

- [Spatie Laravel Permission](#migrating-from-spatie-laravel-permission)
- [Silber Bouncer](#migrating-from-silber-bouncer)

## Migrating from Spatie Laravel Permission

### Prerequisites

Before migrating, ensure you have:

1. Installed Warden alongside Spatie Laravel Permission
2. Published and run Warden's migrations
3. Added the `HasRolesAndAbilities` trait to your User model

### Configuration

Configure the Spatie table names in `config/warden.php`:

```php
'migrators' => [
    'spatie' => [
        'tables' => [
            'permissions' => env('WARDEN_SPATIE_PERMISSIONS_TABLE', 'permissions'),
            'roles' => env('WARDEN_SPATIE_ROLES_TABLE', 'roles'),
            'model_has_permissions' => env('WARDEN_SPATIE_MODEL_HAS_PERMISSIONS_TABLE', 'model_has_permissions'),
            'model_has_roles' => env('WARDEN_SPATIE_MODEL_HAS_ROLES_TABLE', 'model_has_roles'),
            'role_has_permissions' => env('WARDEN_SPATIE_ROLE_HAS_PERMISSIONS_TABLE', 'role_has_permissions'),
        ],
        'model_type' => env('WARDEN_SPATIE_MODEL_TYPE', 'user'),
    ],
],
```

### Running the Migration

```php
use Cline\Warden\Migrators\SpatieMigrator;
use App\Models\User;

$migrator = new SpatieMigrator(User::class);
$migrator->migrate();
```

### What Gets Migrated

The Spatie migrator transfers:

- **Roles**: All roles with their names and guard names
- **Permissions**: All permissions converted to Warden abilities (preserving guard_name)
- **Model Role Assignments**: User-to-role associations
- **Model Permissions**: Direct user permissions
- **Role Permissions**: Permissions assigned to roles

**Important:** The Spatie migrator preserves the `guard_name` from your existing Spatie roles and permissions. This ensures guard isolation is maintained during migration. See [Multi-Guard Support](multi-guard-support.md) for details.

### Migration Scenarios Covered

- ✅ Users with only roles
- ✅ Users with only direct permissions
- ✅ Users with both roles AND direct permissions
- ✅ Roles with permissions but no users assigned
- ✅ Multiple users sharing the same role
- ✅ Users with multiple roles

### Example

If you have this Spatie setup:

```php
// Spatie
$user->assignRole('admin');
$user->givePermissionTo('edit-articles');
$admin = Role::findByName('admin');
$admin->givePermissionTo('manage-users');
```

After migration, you'll have:

```php
// Warden equivalent (already migrated, no need to run this)
$user->hasRole('admin'); // true
$user->can('edit-articles'); // true (direct permission)
$user->can('manage-users'); // true (via admin role)
```

## Migrating from Silber Bouncer

### Prerequisites

Before migrating, ensure you have:

1. Installed Warden alongside Silber Bouncer
2. Published and run Warden's migrations
3. Added the `HasRolesAndAbilities` trait to your User model

### Configuration

Configure the Bouncer table names in `config/warden.php`:

```php
'migrators' => [
    'bouncer' => [
        'tables' => [
            'abilities' => env('WARDEN_BOUNCER_ABILITIES_TABLE', 'abilities'),
            'roles' => env('WARDEN_BOUNCER_ROLES_TABLE', 'roles'),
            'assigned_roles' => env('WARDEN_BOUNCER_ASSIGNED_ROLES_TABLE', 'assigned_roles'),
            'permissions' => env('WARDEN_BOUNCER_PERMISSIONS_TABLE', 'permissions'),
        ],
        'entity_type' => env('WARDEN_BOUNCER_ENTITY_TYPE'),
    ],
],
```

### Running the Migration

```php
use Cline\Warden\Migrators\BouncerMigrator;
use App\Models\User;

// Default guard (uses 'guard' config value)
$migrator = new BouncerMigrator(User::class);
$migrator->migrate();

// Specify a custom guard
$apiMigrator = new BouncerMigrator(User::class, 'migration', 'api');
$apiMigrator->migrate();
```

**Note:** Since Bouncer doesn't have built-in guard support, you can specify which guard the migrated roles and abilities should use. See [Multi-Guard Support](multi-guard-support.md) for more information.

### What Gets Migrated

The Bouncer migrator transfers:

- **Roles**: All roles with their names and titles
- **Abilities**: All abilities with complete metadata (title, only_owned, options, entity scoping)
- **User Role Assignments**: User-to-role associations
- **User Permissions**: Direct user permissions (both allowed and forbidden)
- **Role Permissions**: Permissions assigned to roles (both allowed and forbidden)

### Ability Metadata Preserved

Bouncer abilities have rich metadata that Warden preserves:

- `title`: Human-readable ability title
- `only_owned`: Whether the ability only applies to owned resources
- `options`: JSON options for fine-grained control
- `entity_id`/`entity_type`: Entity scoping (migrated to `subject_id`/`subject_type`)

### Migration Scenarios Covered

- ✅ Users with only roles
- ✅ Users with only direct permissions
- ✅ Users with both roles AND direct permissions
- ✅ Forbidden permissions (on both users and roles)
- ✅ Roles with permissions but no users assigned
- ✅ Multiple users sharing the same role
- ✅ Users with multiple roles
- ✅ Roles without titles (title defaults to role name)

### Example

If you have this Bouncer setup:

```php
// Bouncer
Bouncer::assign('admin')->to($user);
Bouncer::allow($user)->to('edit-articles');
Bouncer::allow('admin')->to('manage-users');
Bouncer::forbid($user)->to('delete-posts');
```

After migration, you'll have:

```php
// Warden equivalent (already migrated, no need to run this)
$user->hasRole('admin'); // true
$user->can('edit-articles'); // true (direct permission)
$user->can('manage-users'); // true (via admin role)
$user->cannot('delete-posts'); // true (forbidden)
```

## Post-Migration Steps

After successfully migrating:

1. **Test thoroughly**: Verify all permissions work as expected
2. **Update code**: Replace old package facades/helpers with Warden equivalents
3. **Remove old package**: Once confident, uninstall the previous package
4. **Clean up**: Drop the old package's tables if no longer needed

## Migration in Production

For production environments:

1. **Backup your database** before migrating
2. **Test migration in staging** first
3. **Plan for downtime** or use a maintenance window
4. **Monitor logs** during and after migration
5. **Have a rollback plan** ready

## Troubleshooting

### Missing Users

The migrator skips assignments for non-existent users. Check logs for:

```
User not found: 12345
```

### Missing Roles/Abilities

The migrator skips assignments referencing non-existent roles or abilities. Check logs for:

```
Role not found: 98765
Ability not found: 45678
```

### Guard Name Issues

**For Spatie migrations**: Guard names are preserved automatically from your Spatie tables.

**For Bouncer migrations**: Bouncer doesn't have guard support, so specify the target guard when creating the migrator:

```php
// Migrate to 'api' guard instead of default 'web'
$migrator = new BouncerMigrator(User::class, 'migration', 'api');
```

See [Multi-Guard Support](multi-guard-support.md) for more information on working with multiple guards.

## Custom Migrators

If you're migrating from a different package or have custom requirements, you can create your own migrator by implementing the `MigratorInterface`:

```php
namespace App\Migrators;

use Cline\Warden\Contracts\MigratorInterface;

class CustomMigrator implements MigratorInterface
{
    public function migrate(): void
    {
        // Your migration logic here
    }
}
```

The interface is intentionally simple with a single `migrate()` method, giving you full control over the migration process.
