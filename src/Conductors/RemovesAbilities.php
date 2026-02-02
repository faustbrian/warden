<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Conductors;

use Cline\Warden\Conductors\Concerns\DisassociatesAbilities;
use Illuminate\Database\Eloquent\Model;

use function assert;
use function config;
use function is_string;

/**
 * Removes granted abilities from authorities.
 *
 * This conductor handles the detachment of normal (non-forbidden) abilities
 * from an authority entity. It specifically targets abilities where the
 * 'forbidden' flag is false, leaving forbidden abilities intact.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class RemovesAbilities
{
    use DisassociatesAbilities;

    /**
     * The constraints to use for the detach abilities query.
     *
     * This constraint ensures only granted abilities (forbidden = false) are
     * removed when detaching, preserving any explicitly forbidden abilities
     * associated with the authority. This separation allows independent
     * management of permission grants and explicit denials.
     *
     * @var array<string, bool>
     */
    protected array $constraints = ['forbidden' => false];

    /**
     * The guard name for the abilities.
     *
     * Specifies which authentication guard these abilities apply to, enabling
     * support for multiple authentication systems within a single application.
     * Defaults to the configured Warden guard (typically 'web').
     */
    protected string $guardName;

    /**
     * Create a new abilities remover instance.
     *
     * @param null|Model|string $authority The entity from which to remove abilities.
     *                                     Can be a Model instance (typically a User or Role),
     *                                     a string identifier representing a role name that
     *                                     will be resolved to a Role model, or null to be
     *                                     set later via trait methods.
     * @param null|string       $guardName The guard name for the abilities. Defaults to the
     *                                     configured guard name if not provided.
     */
    public function __construct(
        protected readonly Model|string|null $authority = null,
        ?string $guardName = null,
    ) {
        $guard = $guardName ?? config('warden.guard', 'web');
        assert(is_string($guard));
        $this->guardName = $guard;
    }
}
