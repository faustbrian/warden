# Authorization

Warden works seamlessly with Laravel's authorization features including the Gate, policies, and blade directives.

## Using Laravel's Gate

Authorizing users is handled directly at Laravel's Gate, or on the user model (`$user->can($ability)`).

When you check abilities at Laravel's gate, Warden will automatically be consulted. If Warden sees an ability that has been granted to the current user (whether directly, or through a role) it'll authorize the check.

## Warden Passthrough Methods

For convenience, the `Warden` class provides these passthrough methods that call directly into Laravel's `Gate`:

```php
Warden::can($ability);
Warden::can($ability, $model);

Warden::canAny($abilities);
Warden::canAny($abilities, $model);

Warden::cannot($ability);
Warden::cannot($ability, $model);

Warden::authorize($ability);
Warden::authorize($ability, $model);
```

## Using the User Model

Check abilities directly on the user model:

```php
if ($user->can('edit', $post)) {
    // User can edit the post
}

if ($user->cannot('delete', $post)) {
    // User cannot delete the post
}
```

## Blade Directives

Warden does not add its own blade directives. Since Warden works directly with Laravel's gate, simply use its `@can` directive to check for the current user's abilities:

```blade
@can('update', $post)
    <a href="{{ route('post.update', $post) }}">Edit Post</a>
@endcan
```

Check for multiple abilities:

```blade
@can('edit', $post)
    <a href="{{ route('post.edit', $post) }}">Edit</a>
@endcan

@cannot('delete', $post)
    <p>You cannot delete this post.</p>
@endcannot
```

## Checking Roles in Blade

Since checking for roles directly is generally not recommended, Warden does not ship with a separate directive for that. If you still insist on checking for roles, you can do so using the general `@if` directive:

```blade
@if ($user->isAn('admin'))
    <a href="{{ route('admin.dashboard') }}">Admin Dashboard</a>
@endif
```

## Using Policies

Warden works perfectly with Laravel's authorization policies. Your code always takes precedence: if your code allows an action, Warden will not interfere.

Create a policy:

```php
namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function update(User $user, Post $post)
    {
        return $user->id === $post->user_id;
    }
}
```

Register the policy in `AuthServiceProvider`:

```php
protected $policies = [
    Post::class => PostPolicy::class,
];
```

Now both your policy and Warden will be consulted:

```php
// Will check both PostPolicy and Warden permissions
if ($user->can('update', $post)) {
    // User can update
}
```

## Refreshing the Cache

All queries executed by Warden are cached for the current request. If you enable cross-request caching, the cache will persist across different requests.

Whenever you need, you can fully refresh Warden's cache:

```php
Warden::refresh();
```

**Note:** fully refreshing the cache for all users uses cache tags if they're available. Not all cache drivers support this. If your driver does not support cache tags, calling `refresh` might be a little slow, depending on the amount of users in your system.

Alternatively, you can refresh the cache only for a specific user:

```php
Warden::refreshFor($user);
```

**Note:** When using multi-tenancy scopes, this will only refresh the cache for the user in the current scope's context. To clear cached data for the same user in a different scope context, it must be called from within that scope.

## Examples

Check ability using Warden facade:

```php
if (Warden::can('ban-users')) {
    // Current user can ban users
}
```

Check ability on a model:

```php
if (Warden::can('edit', $post)) {
    // Current user can edit this post
}
```

Check any of multiple abilities:

```php
if (Warden::canAny(['edit', 'delete'], $post)) {
    // Current user can edit or delete
}
```

Authorize and throw exception if not allowed:

```php
Warden::authorize('delete', $post);
```

Use in blade templates:

```blade
@can('create', App\Models\Post::class)
    <a href="{{ route('posts.create') }}">Create Post</a>
@endcan

@can('edit', $post)
    <a href="{{ route('posts.edit', $post) }}">Edit</a>
@endcan
```
