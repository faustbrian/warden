# Multi-Guard Support

Warden fully supports Laravel's multiple authentication guards, allowing you to maintain separate permission systems for different parts of your application (web, API, RPC, etc.).

## Configuration

Set your default guard in `config/warden.php`:

```php
'guard' => env('WARDEN_GUARD', 'web'),
```

## Guard-Scoped Operations

### Creating Guard-Specific Instances

Use `Warden::guard()` to create guard-scoped instances:

```php
$webWarden = Warden::guard('web');
$apiWarden = Warden::guard('api');
$rpcWarden = Warden::guard('rpc');
```

### Assigning Roles Per Guard

Roles are isolated by guard - the same role name can exist across different guards:

```php
// Web guard admin
Warden::guard('web')->assign('admin')->to($user);

// API guard admin (separate role)
Warden::guard('api')->assign('admin')->to($user);

// RPC guard admin (separate role)
Warden::guard('rpc')->assign('admin')->to($user);
```

### Granting Abilities Per Guard

Abilities are also guard-scoped:

```php
// Web permissions
Warden::guard('web')->allow($user)->to('manage-posts');
Warden::guard('web')->allow('editor')->to('edit-posts');

// API permissions
Warden::guard('api')->allow($user)->to('read-data');
Warden::guard('api')->allow($user)->to('write-data');

// RPC permissions
Warden::guard('rpc')->allow($user)->to('call-methods');
```

### Forbidding Abilities Per Guard

Forbid abilities for specific guards:

```php
Warden::guard('web')->forbid($user)->to('delete-posts');
Warden::guard('api')->allow($user)->to('delete-posts'); // Still allowed via API
```

## Default Guard Behavior

When you don't specify a guard, Warden uses the configured default (typically `web`):

```php
// Uses default guard from config
Warden::assign('admin')->to($user);
Warden::allow($user)->to('manage-posts');
```

## Guard Isolation

Guards are completely isolated from each other:

```php
// Create web admin
Warden::guard('web')->assign('admin')->to($user);

// Query only web roles
$webRoles = Models::role()->forGuard('web')->get();

// Query only API roles
$apiRoles = Models::role()->forGuard('api')->get();

// No cross-contamination
expect($webRoles->contains('name', 'admin'))->toBeTrue();
expect($apiRoles->contains('name', 'admin'))->toBeFalse();
```

## Migrating from Other Packages

### From Spatie Permission

Spatie's `guard_name` is automatically preserved:

```php
$migrator = new SpatieMigrator(User::class);
$migrator->migrate();

// Roles maintain their original guard
$webAdmin = Models::role()
    ->where('name', 'admin')
    ->where('guard_name', 'web')
    ->first();

$apiAdmin = Models::role()
    ->where('name', 'admin')
    ->where('guard_name', 'api')
    ->first();
```

### From Bouncer

Bouncer doesn't have guard support, so specify the target guard:

```php
// Default to 'web'
$migrator = new BouncerMigrator(User::class);

// Or specify a guard
$apiMigrator = new BouncerMigrator(User::class, 'migration', 'api');
$rpcMigrator = new BouncerMigrator(User::class, 'migration', 'rpc');
```

## Common Patterns

### API + Web Application

```php
// Web admin with full permissions
$webWarden = Warden::guard('web');
$webWarden->assign('admin')->to($user);
$webWarden->allow('admin')->to('*');

// API with read-only access
$apiWarden = Warden::guard('api');
$apiWarden->assign('api-consumer')->to($user);
$apiWarden->allow('api-consumer')->to('read-data');
```

### Multi-Tenant with Guards

Combine guards with multi-tenancy:

```php
Warden::guard('web')
    ->assign('tenant-admin')
    ->within($organization)
    ->to($user);

Warden::guard('api')
    ->allow($user)
    ->within($organization)
    ->to('access-tenant-data');
```

### RPC Service Layer

Separate permissions for internal RPC calls:

```php
Warden::guard('rpc')->allow($serviceAccount)->to('internal-methods');
Warden::guard('rpc')->forbid($publicUser)->to('internal-methods');
```

## Querying by Guard

### Role Queries

```php
// All web roles
$webRoles = Models::role()->forGuard('web')->get();

// Specific role in guard
$webAdmin = Models::role()
    ->where('name', 'admin')
    ->where('guard_name', 'web')
    ->first();
```

### Ability Queries

```php
// All API abilities
$apiAbilities = Models::ability()->forGuard('api')->get();

// Specific ability in guard
$apiWrite = Models::ability()
    ->where('name', 'write-data')
    ->where('guard_name', 'api')
    ->first();
```

## Best Practices

1. **Explicit Guards in Production**: Always use `Warden::guard('name')` in production code to avoid confusion
2. **Consistent Naming**: Use descriptive guard names like `web`, `api`, `admin-api`, `rpc`
3. **Document Guard Usage**: Clearly document which guards are used where in your application
4. **Test Guard Isolation**: Write tests to ensure guards don't leak permissions
5. **Migration Planning**: Plan guard assignment when migrating from packages without guard support

## Database Schema

The guard_name column is added to both the `roles` and `abilities` tables:

```php
Schema::table('warden_roles', function (Blueprint $table) {
    $table->string('guard_name')->default('web');
});

Schema::table('warden_abilities', function (Blueprint $table) {
    $table->string('guard_name')->default('web');
});
```

Unique constraints ensure role names are unique per guard:

```php
$table->unique(['name', 'guard_name', 'scope'], 'roles_name_guard_unique');
```
