# Multi-Tenancy

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
