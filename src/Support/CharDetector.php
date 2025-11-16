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
 * @author Brian Faust <brian@cline.sh>
 */
final class CharDetector
{
    /**
     * Check if string matches UUID format.
     */
    public static function isUuid(string $value): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value);
    }

    /**
     * Check if string matches ULID format (26 alphanumeric characters).
     */
    public static function isUlid(string $value): bool
    {
        return mb_strlen($value) === 26 && ctype_alnum($value);
    }

    /**
     * Check if string is either UUID or ULID format.
     */
    public static function isUuidOrUlid(string $value): bool
    {
        if (self::isUuid($value)) {
            return true;
        }

        return self::isUlid($value);
    }
}
