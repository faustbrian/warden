# Model Restrictions

Sometimes you might want to restrict an ability to a specific model type or instance.

## Restricting to a Model Class

Pass the model name as a second argument to restrict an ability to all instances of that model type:

```php
Warden::allow($user)->to('edit', Post::class);
```

This allows the user to edit any `Post` model.

## Restricting to a Specific Instance

If you want to restrict the ability to a specific model instance, pass in the actual model instead:

```php
Warden::allow($user)->to('edit', $post);
```

This allows the user to edit only that specific `$post` instance.

## Examples

Allow a user to view all documents:

```php
Warden::allow($user)->to('view', Document::class);
```

Allow a user to delete a specific post:

```php
Warden::allow($user)->to('delete', $post);
```

Allow a role to create posts:

```php
Warden::allow('editor')->to('create', Post::class);
```

## Checking Model-Specific Abilities

When checking at the gate, pass the model or model class:

```php
// Check if user can edit any post
$boolean = Warden::can('edit', Post::class);

// Check if user can edit a specific post
$boolean = Warden::can('edit', $post);
```

On the user model:

```php
if ($user->can('edit', $post)) {
    // User can edit this specific post
}
```

## Combining with Roles

You can grant model-specific abilities to roles:

```php
// Allow editors to create any post
Warden::allow('editor')->to('create', Post::class);

// Then assign the role
Warden::assign('editor')->to($user);
```
