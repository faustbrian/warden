# Getting Started

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
