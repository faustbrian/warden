<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Database\Titles;

use Illuminate\Database\Eloquent\Model;

/**
 * Generates human-readable titles for role models.
 *
 * This class converts role names into user-friendly display titles by applying
 * humanization rules (converting underscores/dashes to spaces, proper casing, etc.).
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class RoleTitle extends Title
{
    /**
     * Create a new role title generator for the given role model.
     *
     * Converts the role's internal name (e.g., "super_admin") into a human-readable
     * title (e.g., "Super admin") suitable for display in user interfaces.
     *
     * @param Model $role The role model to generate a title for
     */
    public function __construct(Model $role)
    {
        /** @phpstan-ignore-next-line Dynamic property access on Eloquent model */
        $this->title = $this->humanize($role->name);
    }
}
