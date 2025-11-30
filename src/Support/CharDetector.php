<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Support;

use function ctype_alnum;
use function mb_strlen;
use function preg_match;

/**
 * Detects identifier formats for primary keys and IDs.
 *
 * Provides static utility methods to identify UUID and ULID string formats,
 * enabling appropriate handling of different primary key types in authorization
 * contexts where the format may be unknown or variable.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class CharDetector
{
    /**
     * Check if string matches UUID version 4 format.
     *
     * Validates against the canonical 8-4-4-4-12 hexadecimal format with hyphens.
     *
     * @param  string $value The string to check
     * @return bool   True if the string is a valid UUID format
     */
    public static function isUuid(string $value): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value);
    }

    /**
     * Check if string matches ULID format.
     *
     * Validates that the string is exactly 26 alphanumeric characters, conforming
     * to the ULID specification for lexicographically sortable unique identifiers.
     *
     * @param  string $value The string to check
     * @return bool   True if the string is a valid ULID format
     */
    public static function isUlid(string $value): bool
    {
        return mb_strlen($value) === 26 && ctype_alnum($value);
    }

    /**
     * Check if string is either UUID or ULID format.
     *
     * Convenience method for detecting either identifier format in contexts
     * where both are acceptable primary key types.
     *
     * @param  string $value The string to check
     * @return bool   True if the string matches UUID or ULID format
     */
    public static function isUuidOrUlid(string $value): bool
    {
        if (self::isUuid($value)) {
            return true;
        }

        return self::isUlid($value);
    }
}
