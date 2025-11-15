# Roles and Abilities

Adding roles and abilities to users is extremely easy. You do not have to create a role or an ability in advance. Simply pass the name of the role/ability, and Warden will create it if it doesn't exist.

## Creating Roles and Abilities

Let's create a role called `admin` and give it the ability to `ban-users` from our site:

```php
Warden::allow('admin')->to('ban-users');
```

That's it. Behind the scenes, Warden will create both a `Role` model and an `Ability` model for you.

If you want to add additional attributes to the role/ability, such as a human-readable title, you can manually create them using the `role` and `ability` methods:

```php
$admin = Warden::role()->firstOrCreate([
    'name' => 'admin',
    'title' => 'Administrator',
]);

$ban = Warden::ability()->firstOrCreate([
    'name' => 'ban-users',
    'title' => 'Ban users',
]);

Warden::allow($admin)->to($ban);
```

## Assigning Roles to Users

To give the `admin` role to a user, simply tell Warden that the given user should be assigned the admin role:

```php
Warden::assign('admin')->to($user);
```

Alternatively, you can call the `assign` method directly on the user:

```php
$user->assign('admin');
```

## Giving Abilities Directly to Users

Sometimes you might want to give a user an ability directly, without using a role:

```php
Warden::allow($user)->to('ban-users');
```

You can accomplish the same directly off of the user:

```php
$user->allow('ban-users');
```

## Assigning Roles to Multiple Users

You can assign roles to multiple users by ID:

```php
Warden::assign('admin')->to([1, 2, 3]);
```

## Re-syncing Roles and Abilities

Re-sync a user's abilities:

```php
Warden::sync($user)->abilities($abilities);
```

Re-sync a user's roles:

```php
Warden::sync($user)->roles($roles);
```

## Granting Everything

Allow a user or role to do everything:

```php
Warden::allow($user)->everything();
Warden::allow('admin')->everything();
```

Allow a specific ability on everything:

```php
Warden::allow($user)->to('view')->everything();
```

## Managing Models

Allow a user to manage a model (all abilities):

```php
Warden::allow($user)->toManage(Post::class);
Warden::allow($user)->toManage($post);
```

## Context-Aware Permissions

Sometimes you need permissions that are only valid within a specific context, such as a team, organization, or workspace. Warden supports context-aware permissions through the `within()` method:

### Basic Context-Aware Abilities

Grant an ability that only applies within a specific context:

```php
// Simple ability within a team context
Warden::allow($user)->within($team)->to('view-invoices');

// Model class ability within an organization context
Warden::allow($user)->within($organization)->to('edit', Post::class);

// Specific model instance ability within a workspace context
Warden::allow($user)->within($workspace)->to('delete', $invoice);
```

### Context-Aware Roles

You can also assign roles within a specific context:

```php
// User is an admin, but only within this team
Warden::assign('admin')->within($team)->to($user);

// User is an editor, but only within this organization
Warden::assign('editor')->within($organization)->to($user);
```

### Context-Aware Forbidden Abilities

Forbid abilities within a specific context:

```php
// User cannot delete invoices within this team
Warden::forbid($user)->within($team)->to('delete-invoices');

// User cannot edit this specific post within this organization
Warden::forbid($user)->within($organization)->to('edit', $post);
```

### Independence from Global Permissions

Context-aware permissions are completely independent from global permissions. A user can have different abilities in different contexts:

```php
// Global permission - can view all invoices
Warden::allow($user)->to('view-invoices');

// Team-specific permission - can edit invoices in Team A
Warden::allow($user)->within($teamA)->to('edit-invoices');

// Different team permission - can delete invoices in Team B
Warden::allow($user)->within($teamB)->to('delete-invoices');
```

In the example above:
- The user can view invoices globally (everywhere)
- The user can edit invoices only within Team A
- The user can delete invoices only within Team B

### Configuration

Context morphs use the same polymorphic relationship types as actors. You can configure the morph type in your `config/warden.php`:

```php
'context_morph_type' => env('WARDEN_CONTEXT_MORPH_TYPE', 'morph'),
```

Supported values:
- `'morph'` - Standard integer IDs (default)
- `'ulidMorph'` - ULID primary keys
- `'uuidMorph'` - UUID primary keys
