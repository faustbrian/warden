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
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class ScopeWarden
{
    /**
     * Constructor.
     */
    public function __construct(
        /**
         * The Warden instance.
         *
         * @var Warden
         */
        private Warden $warden,
    ) {}

    /**
     * Set the proper Warden scope for the incoming request.
     *
     * @param  Request $request
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // Here you may use whatever mechanism you use in your app
        // to determine the current tenant. To demonstrate, the
        // $tenantId is set here from the user's account_id.
        $tenantId = $request->user()->account_id;

        $this->warden->scope()->to($tenantId);

        return $next($request);
    }
}
