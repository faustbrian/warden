# Configuration

Warden ships with sensible defaults, so most of the time there should be no need for any configuration. For finer-grained control, Warden can be customized by calling various configuration methods on the `Warden` class.

If you only use one or two of these config options, you can stick them into your main `AppServiceProvider`'s `boot` method. If they start growing, you may create a separate `WardenServiceProvider` class in your `app/Providers` directory (remember to register it in the `providers` config array).

## Cache

By default, all queries executed by Warden are cached for the current request. For better performance, you may want to use cross-request caching:

```php
Warden::cache();
```

**Warning:** if you enable cross-request caching, you are responsible to refresh the cache whenever you make changes to user's roles/abilities.

On the contrary, you may at times wish to completely disable the cache, even within the same request:

```php
Warden::dontCache();
```

This is particularly useful in unit tests, when you want to run assertions against roles/abilities that have just been granted.

## Primary Keys and Morph Types

Warden supports different primary key types and polymorphic relationship types through configuration in your `config/warden.php` file:

### Primary Key Type

Configure the type of primary keys used for Warden tables:

```php
'primary_key_type' => env('WARDEN_PRIMARY_KEY', 'id'),
```

Supported values:
- `'id'` - Auto-incrementing integers (default)
- `'ulid'` - Sortable unique identifiers
- `'uuid'` - Standard universally unique identifiers

### Actor Morph Type

Configure the polymorphic relationship type for actor (user/role) ownership:

```php
'actor_morph_type' => env('WARDEN_ACTOR_MORPH_TYPE', 'morph'),
```

Supported values:
- `'morph'` - Standard integer IDs (default)
- `'ulidMorph'` - ULID primary keys
- `'uuidMorph'` - UUID primary keys

### Context Morph Type

Configure the polymorphic relationship type for context-aware permissions:

```php
'context_morph_type' => env('WARDEN_CONTEXT_MORPH_TYPE', 'morph'),
```

Supported values:
- `'morph'` - Standard integer IDs (default)
- `'ulidMorph'` - ULID primary keys
- `'uuidMorph'` - UUID primary keys

**Note:** These configurations must be set before running migrations, as they determine the database schema structure.

## Migrators

Warden includes migrators to help you transition from other popular Laravel permission packages. Configure the table names for the package you're migrating from:

### Bouncer Migration Configuration

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

### Spatie Migration Configuration

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

See the [Migrating from Other Packages](migrating-from-other-packages.md) guide for complete migration instructions.

## Guard

Configure the default authentication guard for roles and abilities:

```php
'guard' => env('WARDEN_GUARD', 'web'),
```

This setting determines which guard is used when you don't explicitly specify one. For applications using multiple authentication guards (web, api, rpc), you can create guard-scoped Warden instances:

```php
// Uses default guard from config
Warden::allow($user)->to('manage-posts');

// Explicitly use web guard
Warden::guard('web')->allow($user)->to('manage-posts');

// Explicitly use api guard
Warden::guard('api')->allow($user)->to('read-data');
```

See the [Multi-Guard Support](multi-guard-support.md) guide for complete details on using multiple guards.

## Tables

To change the database table names used by Warden, pass an associative array to the `tables` method. The keys should be Warden's default table names, and the values should be the table names you wish to use. You do not have to pass in all table names; only the ones you wish to change.

```php
Warden::tables([
    'abilities' => 'my_abilities',
    'permissions' => 'granted_abilities',
]);
```

Warden's published migration uses the table names from this configuration, so be sure to have these in place before actually running the migration.

## Custom Models

You can easily extend Warden's built-in `Role` and `Ability` models.

### Extending Warden's Models

```php
namespace App\Models;

use Cline\Warden\Database\Ability as WardenAbility;

class Ability extends WardenAbility
{
    // custom code
}
```

```php
namespace App\Models;

use Cline\Warden\Database\Role as WardenRole;

class Role extends WardenRole
{
    // custom code
}
```

### Using Traits Instead

Alternatively, you can use Warden's `IsAbility` and `IsRole` traits without actually extending any of Warden's models:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Cline\Warden\Database\Concerns\IsAbility;

class Ability extends Model
{
    use IsAbility;

    // custom code
}
```

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Cline\Warden\Database\Concerns\IsRole;

class Role extends Model
{
    use IsRole;

    // custom code
}
```

If you use the traits instead of extending Warden's models, be sure to set the proper `$table` name and `$fillable` fields yourself.

### Registering Custom Models

Regardless of which method you use, the next step is to tell Warden to use your custom models:

```php
Warden::useAbilityModel(\App\Models\Ability::class);
Warden::useRoleModel(\App\Models\Role::class);
```

**Note:** Eloquent determines the foreign key of relationships based on the parent model name. To keep things simple, name your custom classes the same as Warden's: `Ability` and `Role`, respectively.

If you need to use different names, be sure to either update your migration file or override the relationship methods to explicitly set their foreign keys.

## User Model

By default, Warden automatically uses the user model of the default auth guard.

If you're using Warden with a non-default guard, and it uses a different user model, you should let Warden know about the user model you want to use:

```php
Warden::useUserModel(\App\Admin::class);
```

## Ownership

In Warden, the concept of ownership is used to allow users to perform actions on models they "own".

### Default Ownership Column

By default, Warden will check the model's `user_id` against the current user's primary key. If needed, this can be set to a different attribute:

```php
Warden::ownedVia('userId');
```

### Per-Model Ownership Columns

If different models use different columns for ownership, you can register them separately:

```php
Warden::ownedVia(Post::class, 'created_by');
Warden::ownedVia(Order::class, 'entered_by');
```

### Custom Ownership Logic

For greater control, you can pass a closure with your custom logic:

```php
Warden::ownedVia(Game::class, function ($game, $user) {
    return $game->team_id == $user->team_id;
});
```

## Examples

Enable cross-request caching:

```php
use Cline\Warden\Facades\Warden;

public function boot()
{
    Warden::cache();
}
```

Configure custom table names:

```php
public function boot()
{
    Warden::tables([
        'abilities' => 'permissions',
        'roles' => 'user_roles',
    ]);
}
```

Register custom models:

```php
public function boot()
{
    Warden::useAbilityModel(\App\Models\Ability::class);
    Warden::useRoleModel(\App\Models\Role::class);
}
```

Configure ownership:

```php
public function boot()
{
    Warden::ownedVia('created_by');
    Warden::ownedVia(Post::class, 'author_id');
    Warden::ownedVia(Comment::class, 'user_id');
}
```

Complete configuration example:

```php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Cline\Warden\Facades\Warden;

class WardenServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Warden::cache();

        Warden::tables([
            'abilities' => 'permissions',
        ]);

        Warden::ownedVia(Post::class, 'author_id');
        Warden::ownedVia(Order::class, 'created_by');
    }
}
```
