<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Http\Middleware;

use BackedEnum;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

use function abort;
use function abort_if;
use function array_map;
use function explode;
use function implode;

/**
 * Middleware to check if the current user has the required permission(s).
 *
 * Usage with static constructor:
 *   - Single permission: ->middleware(HasPermission::to(Permission::USERS_VIEW))
 *   - Multiple (any): ->middleware(HasPermission::toAny(Permission::USERS_VIEW, Permission::USERS_CREATE))
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class HasPermission
{
    /**
     * Create middleware string for a single permission.
     */
    public static function to(BackedEnum|string $permission): string
    {
        $value = $permission instanceof BackedEnum ? $permission->value : $permission;

        return 'permission:'.$value;
    }

    /**
     * Create middleware string for multiple permissions (any grants access).
     */
    public static function toAny(BackedEnum|string ...$permissions): string
    {
        $values = array_map(
            fn (BackedEnum|string $p): int|string => $p instanceof BackedEnum ? $p->value : $p,
            $permissions,
        );

        return 'permission:'.implode('|', $values);
    }

    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        abort_if($user === null, 403, 'Unauthenticated.');

        // Handle multiple permissions (any of them grants access)
        $permissions = explode('|', $permission);

        foreach ($permissions as $perm) {
            if ($user->can($perm)) {
                return $next($request);
            }
        }

        abort(403, 'You do not have the required permission to access this resource.');
    }
}
