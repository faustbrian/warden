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
 * Middleware to check if the current user has the required role OR permission.
 *
 * Usage with static constructor:
 *   - Single: ->middleware(HasRoleOrPermission::to(Role::ADMIN))
 *   - Multiple (any): ->middleware(HasRoleOrPermission::toAny(Role::ADMIN, Permission::USERS_VIEW))
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class HasRoleOrPermission
{
    /**
     * Create middleware string for a single role or permission.
     */
    public static function to(BackedEnum|string $roleOrPermission): string
    {
        $value = $roleOrPermission instanceof BackedEnum ? $roleOrPermission->value : $roleOrPermission;

        return 'role_or_permission:'.$value;
    }

    /**
     * Create middleware string for multiple roles/permissions (any grants access).
     */
    public static function toAny(BackedEnum|string ...$rolesOrPermissions): string
    {
        $values = array_map(
            fn (BackedEnum|string $r): int|string => $r instanceof BackedEnum ? $r->value : $r,
            $rolesOrPermissions,
        );

        return 'role_or_permission:'.implode('|', $values);
    }

    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next, string $roleOrPermission): Response
    {
        $user = $request->user();

        abort_if($user === null, 403, 'Unauthenticated.');

        // Handle multiple roles/permissions (any of them grants access)
        $items = explode('|', $roleOrPermission);

        foreach ($items as $item) {
            if (Warden::is($user)->a($item) || $user->can($item)) {
                return $next($request);
            }
        }

        abort(403, 'You do not have the required role or permission to access this resource.');
    }
}
