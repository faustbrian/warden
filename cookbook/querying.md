# Querying Users

Warden provides query scopes to filter users by their roles.

## Query Users by Role

You can query your users by whether they have a given role:

```php
$users = User::whereIs('admin')->get();
```

## Query Users by Multiple Roles

Pass in multiple roles to query for users that have *any* of the given roles:

```php
$users = User::whereIs('superadmin', 'admin')->get();
```

## Query Users with All Roles

To query for users who have *all* of the given roles, use the `whereIsAll` method:

```php
$users = User::whereIsAll('sales', 'marketing')->get();
```

## Query Users Not Having Roles

Query for users who don't have specific roles:

```php
$users = User::whereIsNot('banned')->get();
```

## Examples

Get all administrators:

```php
$admins = User::whereIs('admin')->get();
```

Get users who are either editors or moderators:

```php
$staff = User::whereIs('editor', 'moderator')->get();
```

Get users who have both sales and marketing roles:

```php
$salesMarketing = User::whereIsAll('sales', 'marketing')->get();
```

Combine with other query methods:

```php
$activeAdmins = User::whereIs('admin')
    ->where('active', true)
    ->orderBy('name')
    ->get();
```

Get paginated list of editors:

```php
$editors = User::whereIs('editor')->paginate(20);
```

Count users with a specific role:

```php
$adminCount = User::whereIs('admin')->count();
```

## Querying Roles and Abilities

Query roles directly:

```php
use Cline\Warden\Database\Role;

$roles = Role::all();
$adminRole = Role::where('name', 'admin')->first();
```

Query abilities:

```php
use Cline\Warden\Database\Ability;

$abilities = Ability::all();
$banAbility = Ability::where('name', 'ban-users')->first();
```

Get users for a specific role:

```php
$role = Role::where('name', 'admin')->first();
$admins = $role->users;
```

Get abilities for a role:

```php
$role = Role::where('name', 'editor')->first();
$abilities = $role->abilities;
```
