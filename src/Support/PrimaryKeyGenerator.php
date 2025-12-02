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
     * Reads the 'warden.primary_key_type' config value and generates an appropriate
     * identifier. Returns a ULID (lexicographically sortable) or UUID (v4 random)
     * string in lowercase format, or null for traditional auto-increment integer IDs.
     * The generated value is wrapped in a PrimaryKeyValue object that includes both
     * the type and value for consistent handling across the application.
     *
     * @return PrimaryKeyValue Value object containing the primary key type and generated value
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
     * Adds a generated 'id' field to pivot table data when using ULID or UUID
     * primary keys. Auto-increment IDs don't need explicit values since the
     * database generates them. This ensures pivot records have proper primary
     * keys when syncing roles and abilities in many-to-many relationships.
     *
     * @param  array<string, mixed> $data Pivot attributes to be inserted into the database
     * @return array<string, mixed> Enriched attributes with generated 'id' field if using ULID/UUID
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
     * Generates unique primary key values for each ID in a bulk attachment operation.
     * Each entry receives its own ULID or UUID when applicable, ensuring every pivot
     * record has a unique identifier. This is essential for sync operations that need
     * to attach multiple roles or abilities at once while maintaining referential integrity.
     *
     * @param  array<int>                       $ids  Array of IDs to create pivot records for (e.g., role IDs)
     * @param  array<string, mixed>             $data Base pivot attributes shared across all records
     * @return array<int, array<string, mixed>> Array keyed by ID, each containing enriched pivot data with unique primary key
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
