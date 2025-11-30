# Removing Permissions

Warden can retract roles and remove abilities that were previously granted.

## Retracting Roles from Users

Warden can retract a previously-assigned role from a user:

```php
Warden::retract('admin')->from($user);
```

Or do it directly on the user:

```php
$user->retract('admin');
```

## Removing Abilities from Users

Warden can remove an ability previously granted to a user:

```php
Warden::disallow($user)->to('ban-users');
```

Or directly on the user:

```php
$user->disallow('ban-users');
```

**Note:** if the user has a role that allows them to `ban-users`, they will still have that ability. To disallow it, either remove the ability from the role or retract the role from the user.

## Removing Abilities from Roles

If the ability has been granted through a role, tell Warden to remove the ability from the role instead:

```php
Warden::disallow('admin')->to('ban-users');
```

## Removing Model-Specific Abilities

To remove an ability for a specific model type, pass in its name as a second argument:

```php
Warden::disallow($user)->to('delete', Post::class);
```

**Warning:** if the user has an ability to `delete` a specific `$post` instance, the code above will *not* remove that ability. You will have to remove the ability separately by passing in the actual `$post` as a second argument.

To remove an ability for a specific model instance, pass in the actual model:

```php
Warden::disallow($user)->to('delete', $post);
```

## Removing Ownership Abilities

Remove ownership abilities the same way:

```php
Warden::disallow($user)->toOwn(Post::class);
Warden::disallow($user)->toOwnEverything();
```

## Removing Managing Abilities

Remove managing abilities:

```php
Warden::disallow($user)->toManage(Post::class);
Warden::disallow($user)->toManage($post);
```

## Important Notes

The `disallow` method only removes abilities that were previously given to this user/role. If you want to disallow a subset of what a more-general ability has allowed, use the `forbid` method instead (see the Forbidding cookbook).

## Examples

Remove editor role from a user:

```php
Warden::retract('editor')->from($user);
```

Remove ability to create posts:

```php
Warden::disallow($user)->to('create', Post::class);
```

Remove ability to edit a specific post:

```php
Warden::disallow($user)->to('edit', $post);
```

Remove the ban-users ability from the admin role:

```php
Warden::disallow('admin')->to('ban-users');
```
