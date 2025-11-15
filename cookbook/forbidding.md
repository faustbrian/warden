# Forbidding Abilities

Warden allows you to `forbid` a given ability for more fine-grained control. At times you may wish to grant a user/role an ability that covers a wide range of actions, but then restrict a small subset of those actions.

## Basic Forbidding

Forbid a user from performing a specific action:

```php
Warden::forbid($user)->to('view', $classifiedDocument);
```

Forbid a role from performing actions:

```php
Warden::forbid('admin')->toManage(User::class);
```

## Use Cases

### Restricting Access to Specific Instances

You might allow a user to generally view all documents, but have a specific highly-classified document that they should not be allowed to view:

```php
Warden::allow($user)->to('view', Document::class);

Warden::forbid($user)->to('view', $classifiedDocument);
```

### Creating Limited Roles

You may wish to allow your `superadmin`s to do everything in your app, including adding/removing users. Then you may have an `admin` role that can do everything besides managing users:

```php
Warden::allow('superadmin')->everything();

Warden::allow('admin')->everything();
Warden::forbid('admin')->toManage(User::class);
```

### Banning Users

You may wish to occasionally ban users, removing their permission to all abilities. However, actually removing all of their roles & abilities would mean that when the ban is removed we'll have to figure out what their original roles and abilities were.

Using a forbidden ability means that they can keep all their existing roles and abilities, but still not be authorized for anything. We can accomplish this by creating a special `banned` role, for which we'll forbid everything:

```php
Warden::forbid('banned')->everything();
```

Then, whenever we want to ban a user, we'll assign them the `banned` role:

```php
Warden::assign('banned')->to($user);
```

To remove the ban, we'll simply retract the role from the user:

```php
Warden::retract('banned')->from($user);
```

## Unforbidding

To remove a forbidden ability, use the `unforbid` method:

```php
Warden::unforbid($user)->to('view', $classifiedDocument);
```

**Note:** this will remove any previously-forbidden ability. It will *not* automatically allow the ability if it's not already allowed by a different regular ability granted to this user/role.

## Examples

Forbid editing a specific post:

```php
Warden::forbid($user)->to('edit', $post);
```

Forbid a role from deleting any posts:

```php
Warden::forbid('moderator')->to('delete', Post::class);
```

Forbid everything for a banned role:

```php
Warden::forbid('banned')->everything();
```

Allow everything except user management:

```php
Warden::allow('admin')->everything();
Warden::forbid('admin')->toManage(User::class);
```

## Forbidden vs Disallow

The key difference between `forbid` and `disallow`:

- `disallow` removes abilities that were previously granted
- `forbid` explicitly denies access, even if a broader ability would normally allow it

Use `forbid` when you want to create exceptions to broader permissions. Use `disallow` when you want to remove permissions that were previously granted.
