<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Conductors\Lazy;

use Cline\Warden\Conductors\ForbidsAbilities;
use Cline\Warden\Conductors\GivesAbilities;
use Cline\Warden\Conductors\RemovesAbilities;
use Cline\Warden\Conductors\UnforbidsAbilities;
use Illuminate\Database\Eloquent\Model;

use function array_merge;

/**
 * Lazy evaluation wrapper for ownership-based ability restrictions.
 *
 * This class provides a fluent interface for granting abilities that are restricted
 * to resources owned by the authority. The actual ability assignment is deferred until
 * object destruction, allowing the `to()` method to refine which specific abilities
 * should be ownership-restricted before the final operation executes.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class HandlesOwnership
{
    /**
     * The abilities to which ownership is restricted.
     *
     * Defaults to '*' meaning all abilities on the model require ownership.
     * Can be refined to specific ability names or an array of abilities.
     *
     * @var array<string>|string
     *
     * @codeCoverageIgnore Coverage tool cannot track property default values
     */
    private $ability = '*';

    /**
     * Create a new ownership handler instance.
     *
     * @param ForbidsAbilities|GivesAbilities|RemovesAbilities|UnforbidsAbilities $conductor  The underlying conductor that will execute
     *                                                                                        the actual ability assignment with ownership
     *                                                                                        constraints when this object is destroyed.
     *                                                                                        Determines whether abilities are granted,
     *                                                                                        forbidden, or removed.
     * @param Model|string                                                        $model      The model class name or instance to which
     *                                                                                        ownership applies. Abilities will only be
     *                                                                                        granted on model instances owned by the
     *                                                                                        authority (typically determined by matching
     *                                                                                        a foreign key on the model to the authority's ID).
     * @param array<string, mixed>                                                $attributes additional pivot table attributes to store
     *                                                                                        with the ownership-restricted ability, such
     *                                                                                        as custom constraints, expiration dates, or
     *                                                                                        metadata that will be merged with the ownership flag
     */
    public function __construct(
        private readonly ForbidsAbilities|GivesAbilities|RemovesAbilities|UnforbidsAbilities $conductor,
        private readonly Model|string $model,
        private array $attributes = [],
    ) {}

    /**
     * Execute the deferred ownership-restricted ability assignment.
     *
     * Automatically called when the object goes out of scope, ensuring abilities
     * are granted with the 'only_owned' constraint set to true. This flag indicates
     * that the authority can only perform the abilities on model instances they own.
     */
    public function __destruct()
    {
        $this->conductor->to(
            $this->ability,
            $this->model,
            $this->attributes + ['only_owned' => true],
        );
    }

    /**
     * Refine which abilities should be ownership-restricted.
     *
     * By default, all abilities on the model require ownership. Use this method
     * to limit the ownership requirement to specific abilities, such as 'update'
     * or 'delete', while allowing other abilities to work on any instance.
     *
     * @param array<string>|string $ability    Single ability name or array of ability names
     *                                         that should require ownership (e.g., 'update',
     *                                         ['update', 'delete']). Abilities not specified
     *                                         here may be granted without ownership constraints.
     * @param array<string, mixed> $attributes Additional attributes to merge with existing
     *                                         pivot table attributes for this assignment,
     *                                         such as custom constraints or metadata
     */
    public function to(array|string $ability, array $attributes = []): void
    {
        $this->ability = $ability;
        $this->attributes = array_merge($this->attributes, $attributes);
    }
}
