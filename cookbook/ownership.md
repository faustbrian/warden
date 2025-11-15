# Ownership

Warden allows users to manage models they "own" using the ownership feature.

## Allowing Users to Own Models

Use the `toOwn` method to allow users to manage their own models:

```php
Warden::allow($user)->toOwn(Post::class);
```

When checking at the gate whether the user may perform an action on a given post, the post's `user_id` will be compared to the logged-in user's `id`. If they match, the gate will allow the action.

## Restricting Ownership to Specific Abilities

The above will grant all abilities on a user's owned models. You can restrict the abilities by following it up with a call to the `to` method:

```php
Warden::allow($user)->toOwn(Post::class)->to('view');

// Or pass it an array of abilities:
Warden::allow($user)->toOwn(Post::class)->to(['view', 'update']);
```

## Allowing Ownership of Everything

You can allow users to own all types of models in your application:

```php
Warden::allow($user)->toOwnEverything();

// And to restrict ownership to a given ability:
Warden::allow($user)->toOwnEverything()->to('view');
```

## Examples

Allow editors to edit their own posts:

```php
Warden::allow('editor')->toOwn(Post::class)->to('edit');
Warden::assign('editor')->to($user);
```

Allow a user to view and update their own documents:

```php
Warden::allow($user)->toOwn(Document::class)->to(['view', 'update']);
```

Allow authors to manage all their own content:

```php
Warden::allow('author')->toOwnEverything();
```

## Customizing Ownership

By default, Warden checks the model's `user_id` against the current user's primary key. See the Configuration cookbook for how to customize this behavior with different columns or custom logic.

## Checking Owned Models

When you check abilities at the gate, Warden will automatically check ownership:

```php
// Will check if $user owns $post
if ($user->can('edit', $post)) {
    // User can edit their own post
}
```

The ownership check happens automatically if you've granted the `toOwn` ability to the user or their role.
