<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Conductors;

use BackedEnum;
use Cline\Warden\Contracts\ClipboardInterface;
use Cline\Warden\Database\Role;
use Cline\Warden\Support\Helpers;
use Illuminate\Database\Eloquent\Model;

use function array_map;
use function array_values;

/**
 * Fluent role checker providing human-readable role verification methods.
 *
 * Offers grammatically natural methods (a, an, notA, notAn, all) for checking
 * role membership. Immutable by design - all state is provided at construction
 * and subsequent method calls delegate to the clipboard for actual verification.
 *
 * @see     AssignsRoles For role assignment
 *
 * @author  Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class ChecksRoles
{
    /**
     * Create a new role checker instance.
     *
     * @param Model              $authority The authority model (user or role) to check role membership for.
     *                                      All subsequent role checks will be performed against this authority,
     *                                      verifying whether it has been assigned the specified roles through
     *                                      the authorization system's role assignment mechanisms.
     * @param ClipboardInterface $clipboard The clipboard instance providing access to cached or fresh role data.
     *                                      Handles the actual role lookup logic and applies appropriate scope
     *                                      constraints for multi-tenancy support when configured in the system.
     */
    public function __construct(
        private Model $authority,
        private ClipboardInterface $clipboard,
    ) {}

    /**
     * Check if the authority has any of the given roles.
     *
     * Uses "or" logic - returns true if the authority possesses at least one
     * of the specified roles. Provides grammatically correct phrasing for
     * role checks: $user->is()->a('admin', 'moderator').
     *
     * @param  BackedEnum|string ...$roles Variable list of role names or BackedEnums to check
     * @return bool              True if the authority has at least one of the roles, false otherwise
     */
    public function a(BackedEnum|string ...$roles): bool
    {
        /** @var array<int, int|Role|string> $normalized */
        $normalized = array_map(Helpers::normalizeEnumValue(...), array_values($roles));

        return $this->clipboard->checkRole($this->authority, $normalized, 'or');
    }

    /**
     * Check if the authority doesn't have any of the given roles.
     *
     * Uses "not" logic - returns true only if the authority possesses none
     * of the specified roles. Useful for excluding users with certain roles:
     * $user->is()->notA('banned', 'suspended').
     *
     * @param  BackedEnum|string ...$roles Variable list of role names or BackedEnums to check exclusion for
     * @return bool              True if the authority has none of the roles, false if they have any
     */
    public function notA(BackedEnum|string ...$roles): bool
    {
        /** @var array<int, int|Role|string> $normalized */
        $normalized = array_map(Helpers::normalizeEnumValue(...), array_values($roles));

        return $this->clipboard->checkRole($this->authority, $normalized, 'not');
    }

    /**
     * Grammatical alias to the "a" method for vowel-starting roles.
     *
     * Provides natural English phrasing when checking roles that start with
     * vowels: $user->is()->an('admin', 'editor') instead of is()->a('admin').
     * Functionally identical to the a() method.
     *
     * @param  BackedEnum|string ...$roles Variable list of role names or BackedEnums to check
     * @return bool              True if the authority has at least one of the roles, false otherwise
     */
    public function an(BackedEnum|string ...$roles): bool
    {
        /** @var array<int, int|Role|string> $normalized */
        $normalized = array_map(Helpers::normalizeEnumValue(...), array_values($roles));

        return $this->clipboard->checkRole($this->authority, $normalized, 'or');
    }

    /**
     * Grammatical alias to the "notA" method for vowel-starting roles.
     *
     * Provides natural English phrasing when excluding roles that start with
     * vowels: $user->is()->notAn('admin', 'editor'). Functionally identical
     * to the notA() method.
     *
     * @param  BackedEnum|string ...$roles Variable list of role names or BackedEnums to check exclusion for
     * @return bool              True if the authority has none of the roles, false if they have any
     */
    public function notAn(BackedEnum|string ...$roles): bool
    {
        /** @var array<int, int|Role|string> $normalized */
        $normalized = array_map(Helpers::normalizeEnumValue(...), array_values($roles));

        return $this->clipboard->checkRole($this->authority, $normalized, 'not');
    }

    /**
     * Check if the authority has all of the given roles.
     *
     * Uses "and" logic - returns true only if the authority possesses every
     * specified role. Useful for requiring multiple roles simultaneously:
     * $user->is()->all('member', 'verified', 'premium').
     *
     * @param  BackedEnum|int|Role|string ...$roles Variable list of role IDs, names, BackedEnums, or Role instances that must all be present
     * @return bool                       True if the authority has all of the roles, false if missing any
     */
    public function all(BackedEnum|int|Role|string ...$roles): bool
    {
        /** @var array<int, int|Role|string> $normalized */
        $normalized = array_map(Helpers::normalizeEnumValue(...), array_values($roles));

        return $this->clipboard->checkRole($this->authority, $normalized, 'and');
    }
}
