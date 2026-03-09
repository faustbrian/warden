## Table of Contents

1. [Getting Started](#doc-docs-readme) (`docs/README.md`)
2. [Roles and Abilities](#doc-docs-roles-and-abilities) (`docs/roles-and-abilities.md`)
3. [Model Restrictions](#doc-docs-model-restrictions) (`docs/model-restrictions.md`)
4. [Ownership](#doc-docs-ownership) (`docs/ownership.md`)
5. [Removing Permissions](#doc-docs-removing-permissions) (`docs/removing-permissions.md`)
6. [Forbidding](#doc-docs-forbidding) (`docs/forbidding.md`)
7. [Checking Permissions](#doc-docs-checking-permissions) (`docs/checking-permissions.md`)
8. [Querying](#doc-docs-querying) (`docs/querying.md`)
9. [Authorization](#doc-docs-authorization) (`docs/authorization.md`)
10. [Multi-Tenancy](#doc-docs-multi-tenancy) (`docs/multi-tenancy.md`)
11. [Configuration](#doc-docs-configuration) (`docs/configuration.md`)
12. [Console Commands](#doc-docs-console-commands) (`docs/console-commands.md`)
13. [Conditional Permissions](#doc-docs-conditional-permissions) (`docs/conditional-permissions.md`)
14. [Migrating From Other Packages](#doc-docs-migrating-from-other-packages) (`docs/migrating-from-other-packages.md`)
15. [Multi Guard Support](#doc-docs-multi-guard-support) (`docs/multi-guard-support.md`)
<a id="doc-docs-readme"></a>

An elegant, framework-agnostic approach to managing roles and abilities for any app using Eloquent models.

## Requirements

Warden v1.0.2 requires PHP 8.2+ and Laravel/Eloquent 11+.

## Installation in Laravel

Install Warden with composer:

```bash
composer require cline/warden
```

## Add the Trait

Add Warden's trait to your user model:

```php
use Cline\Warden\Database\HasRolesAndAbilities;

class User extends Model
{
    use HasRolesAndAbilities;
}
```

## Run Migrations

First publish the migrations into your app's `migrations` directory:

```bash
php artisan vendor:publish --tag="warden.migrations"
```

Then run the migrations:

```bash
php artisan migrate
```

**Migrating from another package?** Check out the [Migrating from Other Packages](#doc-docs-migrating-from-other-packages) guide for instructions on migrating from Spatie Laravel Permission or Silber Bouncer.

**Using multiple authentication guards?** See the [Multi-Guard Support](#doc-docs-multi-guard-support) guide to learn how to maintain separate permission systems for web, API, and RPC guards.

**Need conditional permissions?** See the [Conditional Permissions](#doc-docs-conditional-permissions) guide to learn how to use propositions for complex authorization rules like resource ownership, time-based access, and approval limits.

## Using the Facade

Whenever you use the `Warden` facade in your code, remember to add this line to your namespace imports at the top of the file:

```php
use Cline\Warden\Facades\Warden;
```

## Quick Start

Once installed, you can tell Warden what you want to allow at the gate:

```php
// Give a user the ability to create posts
Warden::allow($user)->to('create', Post::class);

// Alternatively, do it through a role
Warden::allow('admin')->to('create', Post::class);
Warden::assign('admin')->to($user);

// You can also grant an ability only to a specific model
Warden::allow($user)->to('edit', $post);
```

When you check abilities at Laravel's gate, Warden will automatically be consulted. If Warden sees an ability that has been granted to the current user (whether directly, or through a role) it'll authorize the check.

## Enabling Cache

By default, Warden's queries are cached for the current request. For better performance, you may want to enable cross-request caching:

```php
Warden::cache();
```

**Warning:** if you enable cross-request caching, you are responsible to refresh the cache whenever you make changes to user's roles/abilities.

## Installation in Non-Laravel Apps

Install Warden with composer:

```bash
composer require cline/warden
```

Set up the database with the Eloquent Capsule component:

```php
use Illuminate\Database\Capsule\Manager as Capsule;

$capsule = new Capsule;

$capsule->addConnection([/* connection config */]);

$capsule->setAsGlobal();
```

Run the migrations by either using a tool like vagabond to run Laravel migrations outside of a Laravel app, or run the raw SQL directly in your database.

Add Warden's trait to your user model:

```php
use Illuminate\Database\Eloquent\Model;
use Cline\Warden\Database\HasRolesAndAbilities;

class User extends Model
{
    use HasRolesAndAbilities;
}
```

Create an instance of Warden:

```php
use Cline\Warden\Warden;

$warden = Warden::create();

// If you are in a request with a current user
// that you'd wish to check permissions for,
// pass that user to the "create" method:
$warden = Warden::create($user);
```

If you're using dependency injection in your app, you may register the `Warden` instance as a singleton in the container:

```php
use Cline\Warden\Warden;
use Illuminate\Container\Container;

Container::getInstance()->singleton(Warden::class, function () {
    return Warden::create();
});
```

You can now inject `Warden` into any class that needs it.

The `create` method creates a `Warden` instance with sensible defaults. To fully customize it, use the `make` method to get a factory instance:

```php
use Cline\Warden\Warden;

$warden = Warden::make()
         ->withCache($customCacheInstance)
         ->create();
```

Set which model is used as the user model throughout your app:

```php
$warden->useUserModel(User::class);
```

<a id="doc-docs-roles-and-abilities"></a>

Adding roles and abilities to users is extremely easy. You do not have to create a role or an ability in advance. Simply pass the name of the role/ability, and Warden will create it if it doesn't exist.

## Creating Roles and Abilities

Let's create a role called `admin` and give it the ability to `ban-users` from our site:

```php
Warden::allow('admin')->to('ban-users');
```

That's it. Behind the scenes, Warden will create both a `Role` model and an `Ability` model for you.

**Note:** Roles and abilities are scoped to the configured guard (default: `web`). For applications using multiple guards, see [Multi-Guard Support](#doc-docs-multi-guard-support).

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

## Boundary-Scoped Permissions

Sometimes you need permissions that are only valid within a specific boundary, such as a team, organization, or workspace. Warden supports boundary-scoped permissions through the `within()` method:

### Basic Boundary-Scoped Abilities

Grant an ability that only applies within a specific boundary:

```php
// Simple ability within a team boundary
Warden::allow($user)->within($team)->to('view-invoices');

// Model class ability within an organization boundary
Warden::allow($user)->within($organization)->to('edit', Post::class);

// Specific model instance ability within a workspace boundary
Warden::allow($user)->within($workspace)->to('delete', $invoice);
```

### Boundary-Scoped Roles

You can also assign roles within a specific boundary:

```php
// User is an admin, but only within this team
Warden::assign('admin')->within($team)->to($user);

// User is an editor, but only within this organization
Warden::assign('editor')->within($organization)->to($user);
```

### Boundary-Scoped Forbidden Abilities

Forbid abilities within a specific boundary:

```php
// User cannot delete invoices within this team
Warden::forbid($user)->within($team)->to('delete-invoices');

// User cannot edit this specific post within this organization
Warden::forbid($user)->within($organization)->to('edit', $post);
```

### Independence from Global Permissions

Boundary-scoped permissions are completely independent from global permissions. A user can have different abilities in different boundaries:

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

Boundary morphs use the same polymorphic relationship types as actors. You can configure the morph type in your `config/warden.php`:

```php
'boundary_morph_type' => env('WARDEN_BOUNDARY_MORPH_TYPE', 'morph'),
```

Supported values:
- `'morph'` - Standard integer IDs (default)
- `'ulidMorph'` - ULID primary keys
- `'uuidMorph'` - UUID primary keys

**Note:** This configuration must be set before running migrations, as it determines the database schema structure.

<a id="doc-docs-model-restrictions"></a>

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

<a id="doc-docs-ownership"></a>

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

<a id="doc-docs-removing-permissions"></a>

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

<a id="doc-docs-forbidding"></a>

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

<a id="doc-docs-checking-permissions"></a>

Generally speaking, you should not have a need to check roles directly. It is better to allow a role certain abilities, then check for those abilities instead. If what you need is very general, you can create very broad abilities. For example, an `access-dashboard` ability is always better than checking for `admin` or `editor` roles directly.

For the rare occasion that you do want to check a role, that functionality is available.

## Checking Roles

Warden can check if a user has a specific role:

```php
Warden::is($user)->a('moderator');
```

If the role you're checking starts with a vowel, you might want to use the `an` alias method:

```php
Warden::is($user)->an('admin');
```

## Checking for Absence of Roles

You can check if a user *doesn't* have a specific role:

```php
Warden::is($user)->notA('moderator');

Warden::is($user)->notAn('admin');
```

## Checking Multiple Roles

Check if a user has one of many roles:

```php
Warden::is($user)->a('moderator', 'editor');
```

Check if the user has all of the given roles:

```php
Warden::is($user)->all('editor', 'moderator');
```

Check if a user has none of the given roles:

```php
Warden::is($user)->notAn('editor', 'moderator');
```

## Checking Roles on the User Model

These checks can also be done directly on the user:

```php
$user->isAn('admin');
$user->isA('subscriber');

$user->isNotAn('admin');
$user->isNotA('subscriber');

$user->isAll('editor', 'moderator');
```

## Getting All Roles

Get all roles for a user:

```php
$roles = $user->getRoles();
```

## Getting All Abilities

Get all abilities for a user:

```php
$abilities = $user->getAbilities();
```

This will return a collection of the user's allowed abilities, including any abilities granted to the user through their roles.

You can also get a list of abilities that have been explicitly forbidden:

```php
$forbiddenAbilities = $user->getForbiddenAbilities();
```

## Examples

Check if user is an admin:

```php
if (Warden::is($user)->an('admin')) {
    // User is an admin
}
```

Check if user is not a subscriber:

```php
if ($user->isNotA('subscriber')) {
    // User is not a subscriber
}
```

Check if user has multiple roles:

```php
if ($user->isAll('editor', 'moderator')) {
    // User has both editor and moderator roles
}
```

Get all of a user's abilities:

```php
$abilities = $user->getAbilities();

foreach ($abilities as $ability) {
    echo $ability->name;
}
```

<a id="doc-docs-querying"></a>

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

<a id="doc-docs-authorization"></a>

Warden works seamlessly with Laravel's authorization features including the Gate, policies, and blade directives.

## Using Laravel's Gate

Authorizing users is handled directly at Laravel's Gate, or on the user model (`$user->can($ability)`).

When you check abilities at Laravel's gate, Warden will automatically be consulted. If Warden sees an ability that has been granted to the current user (whether directly, or through a role) it'll authorize the check.

**Note:** Abilities are checked against the configured guard. For applications using multiple guards, see [Multi-Guard Support](#doc-docs-multi-guard-support) to ensure proper guard isolation.

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

**Note:** When using multi-tenancy scopes, this will only refresh the cache for the user in the current scope's boundary. To clear cached data for the same user in a different scope boundary, it must be called from within that scope.

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

<a id="doc-docs-multi-tenancy"></a>

Warden fully supports multi-tenant apps, allowing you to seamlessly integrate Warden's roles and abilities for all tenants within the same app.

## The Scope Middleware

To get started, first publish the scope middleware into your app:

```bash
php artisan vendor:publish --tag="warden.middleware"
```

The middleware will now be published to `app/Http/Middleware/ScopeWarden.php`. This middleware is where you tell Warden which tenant to use for the current request.

For example, assuming your users all have an `account_id` attribute, this is what your middleware would look like:

```php
public function handle($request, Closure $next)
{
    $tenantId = $request->user()->account_id;

    Warden::scope()->to($tenantId);

    return $next($request);
}
```

You are free to modify this middleware to fit your app's needs, such as pulling the tenant information from a subdomain.

## Registering the Middleware

Register the middleware in your HTTP Kernel:

```php
protected $middlewareGroups = [
    'web' => [
        // Keep the existing middleware here, and add this:
        \App\Http\Middleware\ScopeWarden::class,
    ]
];
```

All of Warden's queries will now be scoped to the given tenant.

## Customizing Warden's Scope

Depending on your app's setup, you may not actually want *all* of the queries to be scoped to the current tenant.

### Scope Only Relationships

For example, you may have a fixed set of roles/abilities that are the same for all tenants, and only allow your users to control which users are assigned which roles, and which roles have which abilities.

To achieve this, you can tell Warden's scope to only scope the relationships between Warden's models, but not the models themselves:

```php
Warden::scope()->to($tenantId)->onlyRelations();
```

### Don't Scope Role Abilities

Furthermore, your app might not even allow its users to control which abilities a given role has. In that case, tell Warden's scope to exclude role abilities from the scope, so that those relationships stay global across all tenants:

```php
Warden::scope()->to($tenantId)->onlyRelations()->dontScopeRoleAbilities();
```

## Custom Scope Implementation

If your needs are even more specialized than what's outlined above, you can create your own `Scope` with whatever custom logic you need:

```php
use Cline\Warden\Contracts\Scope;

class MyScope implements Scope
{
    // Whatever custom logic your app needs
}
```

Then, in a service provider, register your custom scope:

```php
Warden::scope(new MyScope);
```

Warden will call the methods on the `Scope` interface at various points in its execution. You are free to handle them according to your specific needs.

## Different Scopes for Different Sections

You can use Warden's scope to section off different parts of the site, creating a silo for each one of them with its own set of roles & abilities.

Create a `ScopeWarden` middleware that takes an `$identifier` and sets it as the current scope:

```php
use Warden;
use Closure;

class ScopeWarden
{
    public function handle($request, Closure $next, $identifier)
    {
        Warden::scope()->to($identifier);

        return $next($request);
    }
}
```

Register this new middleware as a route middleware in your HTTP Kernel class:

```php
protected $routeMiddleware = [
    // Keep the other route middleware, and add this:
    'scope-warden' => \App\Http\Middleware\ScopeWarden::class,
];
```

In your route service provider, apply this middleware with a different identifier for the public routes and the dashboard routes:

```php
Route::middleware(['web', 'scope-warden:1'])
     ->namespace($this->namespace)
     ->group(base_path('routes/public.php'));

Route::middleware(['web', 'scope-warden:2'])
     ->namespace($this->namespace)
     ->group(base_path('routes/dashboard.php'));
```

All roles and abilities will now be separately scoped for each section of your site.

## Examples

Basic tenant scoping by account:

```php
public function handle($request, Closure $next)
{
    Warden::scope()->to($request->user()->account_id);

    return $next($request);
}
```

Tenant scoping by subdomain:

```php
public function handle($request, Closure $next)
{
    $subdomain = explode('.', $request->getHost())[0];

    Warden::scope()->to($subdomain);

    return $next($request);
}
```

Scope only relationships:

```php
Warden::scope()->to($tenantId)->onlyRelations();
```

Scope relationships but keep role abilities global:

```php
Warden::scope()->to($tenantId)->onlyRelations()->dontScopeRoleAbilities();
```

<a id="doc-docs-configuration"></a>

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

### Boundary Morph Type

Configure the polymorphic relationship type for boundary-scoped permissions:

```php
'boundary_morph_type' => env('WARDEN_BOUNDARY_MORPH_TYPE', 'morph'),
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

See the [Migrating from Other Packages](#doc-docs-migrating-from-other-packages) guide for complete migration instructions.

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

See the [Multi-Guard Support](#doc-docs-multi-guard-support) guide for complete details on using multiple guards.

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

<a id="doc-docs-console-commands"></a>

## warden:clean

The `warden:clean` command deletes unused abilities. Running this command will delete 2 types of unused abilities.

### Unassigned Abilities

Abilities that are not assigned to anyone. For example:

```php
Warden::allow($user)->to('view', Plan::class);

Warden::disallow($user)->to('view', Plan::class);
```

At this point, the "view plans" ability is not assigned to anyone, so it'll get deleted.

**Note:** depending on the context of your app, you may not want to delete these. If you let your users manage abilities in your app's UI, you probably *don't* want to delete unassigned abilities.

### Orphaned Abilities

Model abilities whose models have been deleted:

```php
Warden::allow($user)->to('delete', $plan);

$plan->delete();
```

Since the plan no longer exists, the ability is no longer of any use, so it'll get deleted.

## Usage

Run the command to delete both types of unused abilities:

```bash
php artisan warden:clean
```

## Flags

If you only want to delete one type of unused ability, run it with one of the following flags:

```bash
php artisan warden:clean --unassigned
```

```bash
php artisan warden:clean --orphaned
```

## Scheduling

To automatically run this command periodically, add it to your console kernel's schedule:

```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('warden:clean')->weekly();
}
```

Or run it daily:

```php
$schedule->command('warden:clean')->daily();
```

Or run it monthly:

```php
$schedule->command('warden:clean')->monthly();
```

## Examples

Clean all unused abilities:

```bash
php artisan warden:clean
```

Clean only unassigned abilities:

```bash
php artisan warden:clean --unassigned
```

Clean only orphaned abilities:

```bash
php artisan warden:clean --orphaned
```

Schedule weekly cleanup in `app/Console/Kernel.php`:

```php
use Illuminate\Console\Scheduling\Schedule;

protected function schedule(Schedule $schedule)
{
    $schedule->command('warden:clean')->weekly();
}
```

<a id="doc-docs-conditional-permissions"></a>

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

<a id="doc-docs-migrating-from-other-packages"></a>

Warden provides migrators to help you transition from other popular Laravel permission packages. These migrators preserve all your existing roles, abilities, permissions, and assignments while converting them to Warden's schema.

## Supported Packages

- [Spatie Laravel Permission](#migrating-from-spatie-laravel-permission)
- [Silber Bouncer](#migrating-from-silber-bouncer)

## Migrating from Spatie Laravel Permission

### Prerequisites

Before migrating, ensure you have:

1. Installed Warden alongside Spatie Laravel Permission
2. Published and run Warden's migrations
3. Added the `HasRolesAndAbilities` trait to your User model

### Configuration

Configure the Spatie table names in `config/warden.php`:

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

### Running the Migration

```php
use Cline\Warden\Migrators\SpatieMigrator;
use App\Models\User;

$migrator = new SpatieMigrator(User::class);
$migrator->migrate();
```

### What Gets Migrated

The Spatie migrator transfers:

- **Roles**: All roles with their names and guard names
- **Permissions**: All permissions converted to Warden abilities (preserving guard_name)
- **Model Role Assignments**: User-to-role associations
- **Model Permissions**: Direct user permissions
- **Role Permissions**: Permissions assigned to roles

**Important:** The Spatie migrator preserves the `guard_name` from your existing Spatie roles and permissions. This ensures guard isolation is maintained during migration. See [Multi-Guard Support](#doc-docs-multi-guard-support) for details.

### Migration Scenarios Covered

- ✅ Users with only roles
- ✅ Users with only direct permissions
- ✅ Users with both roles AND direct permissions
- ✅ Roles with permissions but no users assigned
- ✅ Multiple users sharing the same role
- ✅ Users with multiple roles

### Example

If you have this Spatie setup:

```php
// Spatie
$user->assignRole('admin');
$user->givePermissionTo('edit-articles');
$admin = Role::findByName('admin');
$admin->givePermissionTo('manage-users');
```

After migration, you'll have:

```php
// Warden equivalent (already migrated, no need to run this)
$user->hasRole('admin'); // true
$user->can('edit-articles'); // true (direct permission)
$user->can('manage-users'); // true (via admin role)
```

## Migrating from Silber Bouncer

### Prerequisites

Before migrating, ensure you have:

1. Installed Warden alongside Silber Bouncer
2. Published and run Warden's migrations
3. Added the `HasRolesAndAbilities` trait to your User model

### Configuration

Configure the Bouncer table names in `config/warden.php`:

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

### Running the Migration

```php
use Cline\Warden\Migrators\BouncerMigrator;
use App\Models\User;

// Default guard (uses 'guard' config value)
$migrator = new BouncerMigrator(User::class);
$migrator->migrate();

// Specify a custom guard
$apiMigrator = new BouncerMigrator(User::class, 'migration', 'api');
$apiMigrator->migrate();
```

**Note:** Since Bouncer doesn't have built-in guard support, you can specify which guard the migrated roles and abilities should use. See [Multi-Guard Support](#doc-docs-multi-guard-support) for more information.

### What Gets Migrated

The Bouncer migrator transfers:

- **Roles**: All roles with their names and titles
- **Abilities**: All abilities with complete metadata (title, only_owned, options, entity scoping)
- **User Role Assignments**: User-to-role associations
- **User Permissions**: Direct user permissions (both allowed and forbidden)
- **Role Permissions**: Permissions assigned to roles (both allowed and forbidden)

### Ability Metadata Preserved

Bouncer abilities have rich metadata that Warden preserves:

- `title`: Human-readable ability title
- `only_owned`: Whether the ability only applies to owned resources
- `options`: JSON options for fine-grained control
- `entity_id`/`entity_type`: Entity scoping (migrated to `subject_id`/`subject_type`)

### Migration Scenarios Covered

- ✅ Users with only roles
- ✅ Users with only direct permissions
- ✅ Users with both roles AND direct permissions
- ✅ Forbidden permissions (on both users and roles)
- ✅ Roles with permissions but no users assigned
- ✅ Multiple users sharing the same role
- ✅ Users with multiple roles
- ✅ Roles without titles (title defaults to role name)

### Example

If you have this Bouncer setup:

```php
// Bouncer
Bouncer::assign('admin')->to($user);
Bouncer::allow($user)->to('edit-articles');
Bouncer::allow('admin')->to('manage-users');
Bouncer::forbid($user)->to('delete-posts');
```

After migration, you'll have:

```php
// Warden equivalent (already migrated, no need to run this)
$user->hasRole('admin'); // true
$user->can('edit-articles'); // true (direct permission)
$user->can('manage-users'); // true (via admin role)
$user->cannot('delete-posts'); // true (forbidden)
```

## Post-Migration Steps

After successfully migrating:

1. **Test thoroughly**: Verify all permissions work as expected
2. **Update code**: Replace old package facades/helpers with Warden equivalents
3. **Remove old package**: Once confident, uninstall the previous package
4. **Clean up**: Drop the old package's tables if no longer needed

## Migration in Production

For production environments:

1. **Backup your database** before migrating
2. **Test migration in staging** first
3. **Plan for downtime** or use a maintenance window
4. **Monitor logs** during and after migration
5. **Have a rollback plan** ready

## Troubleshooting

### Missing Users

The migrator skips assignments for non-existent users. Check logs for:

```
User not found: 12345
```

### Missing Roles/Abilities

The migrator skips assignments referencing non-existent roles or abilities. Check logs for:

```
Role not found: 98765
Ability not found: 45678
```

### Guard Name Issues

**For Spatie migrations**: Guard names are preserved automatically from your Spatie tables.

**For Bouncer migrations**: Bouncer doesn't have guard support, so specify the target guard when creating the migrator:

```php
// Migrate to 'api' guard instead of default 'web'
$migrator = new BouncerMigrator(User::class, 'migration', 'api');
```

See [Multi-Guard Support](#doc-docs-multi-guard-support) for more information on working with multiple guards.

## Custom Migrators

If you're migrating from a different package or have custom requirements, you can create your own migrator by implementing the `MigratorInterface`:

```php
namespace App\Migrators;

use Cline\Warden\Contracts\MigratorInterface;

class CustomMigrator implements MigratorInterface
{
    public function migrate(): void
    {
        // Your migration logic here
    }
}
```

The interface is intentionally simple with a single `migrate()` method, giving you full control over the migration process.

<a id="doc-docs-multi-guard-support"></a>

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
