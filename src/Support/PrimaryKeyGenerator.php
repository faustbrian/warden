<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Support;

use Cline\Warden\Enums\PrimaryKeyType;
use Illuminate\Support\Str;

use function config;

/**
 * Generates primary key values based on configuration.
 *
 * Centralizes the logic for reading the primary key type from configuration
 * and generating appropriate values (ULID, UUID, or null for auto-increment).
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class PrimaryKeyGenerator
{
    /**
     * Generate a primary key value based on application configuration.
     *
     * Reads the 'warden.primary_key_type' config value and generates
     * a ULID or UUID string accordingly. Returns null for auto-increment IDs.
     *
     * @return PrimaryKeyValue Value object containing type and generated value
     */
    public static function generate(): PrimaryKeyValue
    {
        $primaryKeyType = PrimaryKeyType::tryFrom(config('warden.primary_key_type', 'id')) ?? PrimaryKeyType::ID;

        $value = match ($primaryKeyType) {
            PrimaryKeyType::ULID => (string) Str::ulid(),
            PrimaryKeyType::UUID => (string) Str::uuid(),
            PrimaryKeyType::ID => null,
        };

        return new PrimaryKeyValue($primaryKeyType, $value);
    }
}
