# Conditional Permissions

Warden supports conditional permissions through **propositions**—rules that must evaluate to true for a permission to be granted. This enables complex authorization logic beyond simple "does the user have this ability" checks.

## Overview

Traditional permissions check only whether an ability is assigned to a user. Conditional permissions add runtime evaluation: "does the user have this ability AND do these conditions pass?"

Example use cases:
- Resource ownership: Users can edit posts they own
- Time-based access: Abilities only active during business hours
- Approval limits: Different users can approve different transaction amounts
- Department matching: Users can only access resources from their department

## Basic Usage

### Creating Abilities with Propositions

Use the `PropositionBuilder` to create conditional logic:

```php
use Cline\Warden\Support\PropositionBuilder;
use Cline\Warden\Database\Ability;
use App\Models\Post;

$builder = new PropositionBuilder();

// Create a proposition that checks resource ownership
$proposition = $builder->resourceOwnedBy();

// Create the ability with the proposition
$ability = Ability::create([
    'name' => 'edit',
    'guard_name' => 'web',
    'subject_type' => Post::class,
    'proposition' => $proposition,
]);

// Grant the ability to a user
$user->allow('edit', Post::class);
```

### Checking Permissions

Permission checks work exactly as before—the proposition is evaluated automatically:

```php
// If the user owns the post, this returns true
$user->can('edit', $ownPost); // true

// If the user doesn't own the post, this returns false
$user->can('edit', $otherPost); // false
```

## PropositionBuilder Helpers

The `PropositionBuilder` provides convenient methods for common authorization patterns:

### Resource Ownership

Check if a resource belongs to the user:

```php
$builder = new PropositionBuilder();

$proposition = $builder->resourceOwnedBy();
```

This compares `$resource->user_id` with `$user->id`. You can customize the keys:

```php
// Custom ownership field and user key
$proposition = $builder->resourceOwnedBy(
    authorityKey: 'user',
    resourceKey: 'model',
    ownershipField: 'owner_id'
);
```

### Time-Based Access

Restrict abilities to specific time windows:

```php
// Only allow access during business hours
$proposition = $builder->timeBetween('09:00', '17:00');
```

### Numeric Limits

Check if a value is within a threshold:

```php
// User can approve transactions within their approval limit
$proposition = $builder->withinLimit('transaction_amount', 'user_approval_limit');
```

### Value Matching

Check if two values are equal:

```php
// User's department must match the resource's department
$proposition = $builder->matches('user_department', 'resource_department');
```

## Complex Conditions

### Combining Multiple Conditions

Use `allOf()` for AND logic:

```php
$builder = new PropositionBuilder();

// User must be verified AND over 18
$proposition = $builder->allOf(
    $builder['user']['verified']->equalTo(true),
    $builder['user']['age']->greaterThan(18)
);
```

Use `anyOf()` for OR logic:

```php
$builder = new PropositionBuilder();

// User must be admin OR resource owner
$proposition = $builder->anyOf(
    $builder['user']['is_admin']->equalTo(true),
    $builder->resourceOwnedBy()
);
```

### Custom Propositions

Access any context value using array syntax:

```php
$builder = new PropositionBuilder();

// Custom condition: post must be published
$proposition = $builder['resource']['status']->equalTo('published');

// Multiple custom conditions
$proposition = $builder->allOf(
    $builder['resource']['status']->equalTo('active'),
    $builder['user']['department']->equalTo('engineering'),
    $builder['resource']['priority']->lessThan(5)
);
```

## Available Operators

The underlying [cline/ruler](https://github.com/cline/ruler) package provides 50+ operators:

**Comparison:**
- `equalTo()`, `notEqualTo()`
- `greaterThan()`, `greaterThanOrEqualTo()`
- `lessThan()`, `lessThanOrEqualTo()`

**String:**
- `stringContains()`, `stringContainsInsensitive()`
- `startsWith()`, `startsWithInsensitive()`
- `endsWith()`, `endsWithInsensitive()`

**Logical:**
- `logicalAnd()`, `logicalOr()`, `logicalNot()`

**Collections:**
- `in()`, `notIn()`
- `containsSubset()`, `doesNotContainSubset()`

See the [ruler documentation](https://github.com/cline/ruler) for the complete list.

## Context Variables

When evaluating propositions, Warden provides these context variables:

- `authority`: The user being authorized (the user calling `can()`)
- `resource`: The model being accessed (the second parameter to `can()`)
- `now`: Current timestamp (for time-based checks)

Access nested properties using array syntax:

```php
$builder['authority']['id']          // User's primary key
$builder['authority']['department']  // User's department
$builder['resource']['user_id']      // Resource's owner ID
$builder['resource']['status']       // Resource's status field
```

## Backward Compatibility

Abilities without propositions work exactly as before:

```php
// Traditional ability (no proposition)
$ability = Ability::create([
    'name' => 'view',
    'guard_name' => 'web',
    'subject_type' => Post::class,
]);

$user->allow('view', Post::class);

// Permission granted based solely on assignment
$user->can('view', $anyPost); // true
```

## Advanced Examples

### Approval Workflow

```php
$builder = new PropositionBuilder();

// Managers can approve up to $10k, directors up to $100k
$proposition = $builder->withinLimit('amount', 'approval_limit');

$ability = Ability::create([
    'name' => 'approve-transaction',
    'guard_name' => 'web',
    'proposition' => $proposition,
]);

// When checking, pass transaction amount in context
$user->can('approve-transaction', null); // evaluated at runtime with user's limit
```

### Multi-Condition Access

```php
$builder = new PropositionBuilder();

// Complex: user owns post OR (post is published AND user is subscriber)
$proposition = $builder->anyOf(
    $builder->resourceOwnedBy(),
    $builder->allOf(
        $builder['resource']['status']->equalTo('published'),
        $builder['authority']['subscription']->equalTo('active')
    )
);

$ability = Ability::create([
    'name' => 'view',
    'guard_name' => 'web',
    'subject_type' => Post::class,
    'proposition' => $proposition,
]);
```

## Performance Considerations

- Propositions are stored as JSON configuration in the database and rebuilt at runtime
- Evaluation happens during permission checks and is very fast (microseconds)
- Avoid extremely complex propositions with deep nesting
- Cache is still effective—cached abilities include their propositions
- Propositions only evaluate when the ability is already assigned to the user
- For PropositionBuilder methods, you can pass array config directly: `['method' => 'resourceOwnedBy', 'params' => []]`
- Complex propositions built with operators fall back to PHP serialization for storage

## Testing

Test conditional permissions like any other:

```php
use Cline\Warden\Support\PropositionBuilder;

test('users can only edit their own posts', function () {
    $user = User::factory()->create();
    $ownPost = Post::factory()->create(['user_id' => $user->id]);
    $otherPost = Post::factory()->create(['user_id' => 999]);

    $builder = new PropositionBuilder();
    $proposition = $builder->resourceOwnedBy();

    Ability::create([
        'name' => 'edit',
        'guard_name' => 'web',
        'subject_type' => Post::class,
        'proposition' => $proposition,
    ]);

    $user->allow('edit', Post::class);

    expect($user->can('edit', $ownPost))->toBeTrue();
    expect($user->can('edit', $otherPost))->toBeFalse();
});
```

## Debugging

To inspect a proposition, access it from the ability:

```php
$ability = Ability::where('name', 'edit')->first();
dd($ability->proposition); // Shows the full proposition structure
```

The proposition is a `Cline\Ruler\Core\Proposition` instance that can be serialized, stored, and evaluated.
