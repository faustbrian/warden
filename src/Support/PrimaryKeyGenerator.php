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
 * Used for creating pivot table entries with appropriate primary key formats.
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
        /** @var int|string $configValue */
        $configValue = config('warden.primary_key_type', 'id');
        $primaryKeyType = PrimaryKeyType::tryFrom($configValue) ?? PrimaryKeyType::ID;

        $value = match ($primaryKeyType) {
            PrimaryKeyType::ULID => Str::lower((string) Str::ulid()),
            PrimaryKeyType::UUID => Str::lower((string) Str::uuid()),
            PrimaryKeyType::ID => null,
        };

        return new PrimaryKeyValue($primaryKeyType, $value);
    }

    /**
     * Enrich pivot data with primary key if required.
     *
     * @param  array<string, mixed> $data Pivot attributes
     * @return array<string, mixed> Enriched attributes with 'id' if needed
     */
    public static function enrichPivotData(array $data): array
    {
        $primaryKey = self::generate();

        if ($primaryKey->requiresValue()) {
            $data['id'] = $primaryKey->value;
        }

        return $data;
    }

    /**
     * Enrich pivot data for multiple IDs with unique primary keys.
     *
     * @param  array<int>                       $ids  IDs to attach
     * @param  array<string, mixed>             $data Base pivot attributes
     * @return array<int, array<string, mixed>> Data keyed by ID with unique primary keys
     */
    public static function enrichPivotDataForIds(array $ids, array $data): array
    {
        $result = [];

        foreach ($ids as $id) {
            $result[$id] = self::enrichPivotData($data);
        }

        return $result;
    }
}
