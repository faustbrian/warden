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

/**
 * Conductor for forbidding (explicitly denying) abilities to authorities.
 *
 * Creates forbidden ability associations that take precedence over allowed abilities
 * in authorization checks. When an authority has a forbidden ability, access is denied
 * regardless of any allowing permissions that might exist. Uses the AssociatesAbilities
 * trait with the forbidding flag set to true.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ForbidsAbilities
{
    use AssociatesAbilities;

    /**
     * Whether the associated abilities should be marked as forbidden.
     *
     * Always true for this conductor. Instructs AssociatesAbilities trait to
     * create permission records with forbidden flag set, enabling explicit denials.
     *
     * @var bool
     */
    protected $forbidding = true;

    /**
     * The context model within which permissions are forbidden.
     *
     * @var null|Model
     */
    protected $context;

    /**
     * Create a new ability forbidding conductor.
     *
     * @param null|Model|string $authority The authority to forbid abilities from. Can be an authority model
     *                                     (user or role) for targeted denials, a role name string that will
     *                                     be found or created, or null to forbid abilities globally to everyone.
     *                                     Forbidden abilities always override allowed abilities in checks.
     */
    public function __construct(
        protected $authority = null,
    ) {}

    /**
     * Set the context for context-aware forbidden permissions.
     *
     * @param  Model $context The context model instance
     * @return self  Fluent interface for method chaining
     */
    public function within(Model $context): self
    {
        $this->context = $context;

        return $this;
    }
}
