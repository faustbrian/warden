<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Database;

use Cline\Warden\Database\Concerns\HasAbilities;
use Cline\Warden\Database\Concerns\HasRoles;

/**
 * Combines role and ability management in a single trait.
 *
 * This convenience trait composes HasAbilities and HasRoles, providing the
 * complete Warden authorization feature set to a model. Apply this to User
 * models or other entities that need both role-based and permission-based
 * access control capabilities.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @see HasAbilities For direct ability assignment and checking
 * @see HasRoles For role assignment and checking
 *
 * @phpstan-ignore-next-line trait.unused - Used in workbench models (User, Account, Organization)
 */
trait HasRolesAndAbilities
{
    use HasAbilities;
    use HasRoles;
}
