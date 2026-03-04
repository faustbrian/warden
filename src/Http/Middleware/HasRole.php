<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Http\Middleware;

use BackedEnum;
use Cline\Warden\Facades\Warden;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use function abort;
use function abort_if;
use function array_map;
use function explode;
use function implode;

/**
 * Middleware to check if the current user has the required role(s).
 *
 * Usage with static constructor:
 *   - Single role: ->middleware(HasRole::to(Role::ADMIN))
 *   - Multiple (any): ->middleware(HasRole::toAny(Role::ADMIN, Role::CUSTOMER_SERVICE))
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class HasRole
{
    /**
     * Create middleware string for a single role.
     */
    public static function to(BackedEnum|string $role): string
    {
        $value = $role instanceof BackedEnum ? $role->value : $role;

        return 'role:'.$value;
    }

    /**
     * Create middleware string for multiple roles (any grants access).
     */
    public static function toAny(BackedEnum|string ...$roles): string
    {
        $values = array_map(
            fn (BackedEnum|string $r): int|string => $r instanceof BackedEnum ? $r->value : $r,
            $roles,
        );

        return 'role:'.implode('|', $values);
    }

    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        abort_if($user === null, 403, 'Unauthenticated.');

        // Handle multiple roles (any of them grants access)
        $roles = explode('|', $role);

        foreach ($roles as $r) {
            if (Warden::is($user)->a($r)) {
                return $next($request);
            }
        }

        abort(403, 'You do not have the required role to access this resource.');
    }
}
