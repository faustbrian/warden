<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden;

use Cline\Warden\Contracts\CachedClipboardInterface;
use Cline\Warden\Contracts\ClipboardInterface;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

use function assert;
use function count;
use function in_array;
use function is_string;
use function throw_unless;

/**
 * Integrates Warden's permission system with Laravel's Gate.
 *
 * The Guard acts as a bridge between Warden's clipboard (permission storage/checking)
 * and Laravel's authorization gate, registering callbacks that intercept authorization
 * checks to apply Warden's role and ability rules. It can be configured to run either
 * before or after policy checks.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Guard
{
    use HandlesAuthorization;

    /**
     * The gate callback timing slot.
     *
     * Controls when clipboard checks run relative to policy checks.
     * Valid values: 'before' (run before policies) or 'after' (run after policies).
     * Running 'before' allows Warden to override policies; 'after' makes policies
     * take precedence over Warden rules.
     */
    private string $slot = 'after';

    /**
     * Create a new guard instance.
     *
     * @param ClipboardInterface $clipboard The clipboard instance containing permission data
     *                                      and providing permission checking logic. Handles
     *                                      querying and caching of role and ability data,
     *                                      providing the core permission checking logic that
     *                                      the guard integrates with the gate.
     */
    public function __construct(
        private ClipboardInterface $clipboard,
    ) {}

    /**
     * Retrieve the current clipboard instance.
     *
     * @return ClipboardInterface The active clipboard instance
     */
    public function getClipboard(): ClipboardInterface
    {
        return $this->clipboard;
    }

    /**
     * Replace the clipboard instance.
     *
     * Allows runtime swapping of the clipboard implementation, useful for
     * switching between cached and non-cached clipboards or custom implementations.
     *
     * @param  ClipboardInterface $clipboard The new clipboard instance to use
     * @return $this
     */
    public function setClipboard(ClipboardInterface $clipboard): self
    {
        $this->clipboard = $clipboard;

        return $this;
    }

    /**
     * Check if the current clipboard supports caching.
     *
     * @return bool True if using a CachedClipboard implementation
     */
    public function usesCachedClipboard(): bool
    {
        return $this->clipboard instanceof CachedClipboardInterface;
    }

    /**
     * Get or set the callback timing slot.
     *
     * When called without arguments, returns the current slot. When called with
     * a slot value, sets the slot and returns the guard instance for chaining.
     *
     * @param null|string $slot The slot to set ('before' or 'after'), or null to get current
     *
     * @throws InvalidArgumentException If slot value is not 'before' or 'after'
     *
     * @return self|string Current slot when getting, or guard instance when setting
     */
    public function slot(?string $slot = null): string|self
    {
        if (null === $slot) {
            return $this->slot;
        }

        throw_unless(in_array($slot, ['before', 'after'], true), InvalidArgumentException::class, $slot.' is an invalid gate slot');

        $this->slot = $slot;

        return $this;
    }

    /**
     * Register guard callbacks with the Laravel gate.
     *
     * Hooks into both the gate's before() and after() callbacks to integrate
     * Warden's permission checks into Laravel's authorization flow. The actual
     * execution timing is controlled by the slot configuration.
     *
     * @param  Gate  $gate The gate instance to register callbacks with
     * @return $this
     */
    public function registerAt(Gate $gate): self
    {
        $gate->before(function (Model $authority, string $ability, array $arguments = []): mixed {
            // @phpstan-ignore-next-line function.alreadyNarrowedType,instanceof.alwaysTrue - Runtime safety check
            assert($authority instanceof Model);

            return $this->runBeforeCallback($authority, $ability, $arguments);
        });

        $gate->after(function (Model $authority, string $ability, mixed $result, array $arguments = []): mixed {
            // @phpstan-ignore-next-line function.alreadyNarrowedType,instanceof.alwaysTrue - Runtime safety check
            assert($authority instanceof Model);

            return $this->runAfterCallback($authority, $ability, $result, $arguments);
        });

        return $this;
    }

    /**
     * Execute the gate's "before" callback.
     *
     * Runs only when slot is set to 'before'. Skips checks for authorization
     * calls with more than 2 arguments (unsupported signature). Delegates to
     * clipboard for the actual permission check.
     *
     * @param  Model                    $authority The user model being authorized
     * @param  string                   $ability   The ability name being checked
     * @param  array<int|string, mixed> $arguments Additional arguments passed to the gate
     * @return mixed                    Null to continue gate checks, bool/Response to short-circuit
     */
    private function runBeforeCallback(Model $authority, string $ability, array $arguments = []): mixed
    {
        if ($this->slot !== 'before') {
            return null;
        }

        if (count($arguments) > 2) {
            return null;
        }

        $model = $arguments[0] ?? null;

        if ($model !== null && !($model instanceof Model) && !is_string($model)) {
            return null;
        }

        return $this->checkAtClipboard($authority, $ability, $model);
    }

    /**
     * Execute the gate's "after" callback.
     *
     * Runs only when slot is set to 'after' and no previous authorization result
     * exists. Skips checks for authorization calls with more than 2 arguments.
     * Delegates to clipboard for the actual permission check.
     *
     * @param  Model                    $authority The user model being authorized
     * @param  string                   $ability   The ability name being checked
     * @param  mixed                    $result    The result from previous checks (policy, etc.)
     * @param  array<int|string, mixed> $arguments Additional arguments passed to the gate
     * @return mixed                    Null to continue, bool/Response to provide final result
     */
    private function runAfterCallback(Model $authority, string $ability, mixed $result, array $arguments = []): mixed
    {
        if (null !== $result) {
            return $result;
        }

        if ($this->slot !== 'after') {
            return null;
        }

        if (count($arguments) > 2) {
            return null;
        }

        $model = $arguments[0] ?? null;

        if ($model !== null && !($model instanceof Model) && !is_string($model)) {
            return null;
        }

        return $this->checkAtClipboard($authority, $ability, $model);
    }

    /**
     * Perform the actual permission check via the clipboard.
     *
     * Queries the clipboard for the ability and returns an authorization response.
     * Returns null if no explicit permission exists (allowing other checks to continue),
     * false if permission is explicitly forbidden, or an "allow" response if granted.
     *
     * @param  Model             $authority The user model being authorized
     * @param  string            $ability   The ability name being checked
     * @param  null|Model|string $model     Optional model instance or class being authorized against
     * @return mixed             Authorization response: Response for granted, false for forbidden, null for no match
     */
    private function checkAtClipboard(Model $authority, string $ability, Model|string|null $model): mixed
    {
        if ($id = $this->clipboard->checkGetId($authority, $ability, $model)) {
            return $this->allow('Bouncer granted permission via ability #'.$id);
        }

        // If the response from "checkGetId" is "false", then this ability
        // has been explicity forbidden. We'll return false so the gate
        // doesn't run any further checks. Otherwise we return null.
        return $id;
    }
}
