<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Http\Middleware;

use Cline\Warden\Warden;
use Closure;
use Illuminate\Http\Request;

/**
 * Middleware for applying Warden tenant scoping to HTTP requests.
 *
 * This middleware sets the appropriate Warden scope for multi-tenant applications
 * before processing the request. The scope typically corresponds to a tenant identifier
 * (e.g., organization ID, account ID) that isolates roles and permissions per tenant.
 *
 * This is a reference implementation that should be customized for your specific
 * multi-tenancy strategy (subdomain-based, path-based, header-based, etc.).
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class ScopeWarden
{
    /**
     * Create a new middleware instance.
     *
     * @param Warden $warden The Warden instance used to configure tenant scoping for
     *                       role and permission isolation. The scope is applied before
     *                       each request is processed, ensuring authorization checks
     *                       respect tenant boundaries throughout the request lifecycle.
     */
    public function __construct(
        private Warden $warden,
    ) {}

    /**
     * Set the appropriate Warden scope for the incoming request.
     *
     * Determines the current tenant from the request context and applies it to Warden's
     * scope, ensuring all subsequent permission checks are isolated to that tenant.
     *
     * This reference implementation extracts the tenant ID from the authenticated user's
     * account_id attribute. You should customize this logic based on your application's
     * multi-tenancy strategy:
     *
     * - Subdomain-based: Extract from `$request->getHost()`
     * - Path-based: Extract from route parameters
     * - Header-based: Read from custom request header
     * - User attribute: Use `$request->user()->tenant_id`
     *
     * @param  Request $request The incoming HTTP request containing tenant context
     * @param  Closure $next    The next middleware in the pipeline
     * @return mixed   Response from the next middleware or controller
     */
    public function handle($request, Closure $next)
    {
        // Example: Extract tenant ID from authenticated user's account_id.
        // Customize this logic based on your multi-tenancy implementation.
        /** @phpstan-ignore-next-line Example code - customize based on your user model */
        $tenantId = $request->user()->account_id;

        // Apply the tenant scope to Warden for the duration of this request
        /** @phpstan-ignore-next-line Example code - customize scope method based on your implementation */
        $this->warden->scope()->to($tenantId);

        return $next($request);
    }
}
