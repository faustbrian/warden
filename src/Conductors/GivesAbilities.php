<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Conductors;

use Cline\Warden\Conductors\Concerns\AssociatesAbilities;
use Illuminate\Database\Eloquent\Model;

use function assert;
use function config;
use function is_string;

/**
 * Grants abilities to authorities (users or roles).
 *
 * This conductor handles the process of assigning abilities to an authority entity,
 * which can be either a Model instance or a string identifier representing a role.
 * The abilities granted through this conductor are normal (non-forbidden) permissions.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class GivesAbilities
{
    use AssociatesAbilities;

    /**
     * Whether the associated abilities should be forbidden abilities.
     *
     * This flag controls the mode of ability association performed by the
     * AssociatesAbilities trait. When false (default), abilities are granted
     * as normal permissions. When true, abilities are explicitly denied.
     */
    protected bool $forbidding = false;

    /**
     * The context model within which permissions are granted.
     *
     * When set, abilities are scoped to a specific organizational context
     * and only valid within that boundary (e.g., a Team, Organization, or
     * Project). This enables multi-tenancy and compartmentalized permissions.
     */
    protected ?Model $context = null;

    /**
     * The guard name for the abilities.
     */
    protected string $guardName;

    /**
     * Create a new abilities granter instance.
     *
     * @param null|Model|string $authority The entity receiving the abilities. Can be a Model
     *                                     instance (typically a User or Role model), a string
     *                                     identifier representing a role name that will be resolved
     *                                     to a Role model, or null to be set later via trait methods.
     *                                     This authority will have the specified abilities granted to them.
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

    /**
     * Set the context for context-aware permissions.
     *
     * Enables permissions that are only valid within a specific organizational
     * context (e.g., team, workspace, organization). Returns the conductor
     * instance for method chaining.
     *
     * ```php
     * Bouncer::allow($user)->within($team)->to('view', 'invoices');
     * ```
     *
     * @param  Model $context The context model instance (e.g., Team, Organization)
     * @return $this
     */
    public function within(Model $context): self
    {
        $this->context = $context;

        return $this;
    }
}
