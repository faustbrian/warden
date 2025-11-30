<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Database\Concerns;

use Cline\Warden\Enums\PrimaryKeyType;
use Cline\Warden\Exceptions\InvalidPrimaryKeyTypeException;
use Cline\Warden\Support\PrimaryKeyGenerator;
use Illuminate\Database\Eloquent\Model;

use function config;
use function in_array;
use function is_string;

/**
 * Configures primary key type based on Warden configuration.
 *
 * This trait dynamically applies the appropriate primary key strategy (auto-increment,
 * ULID, or UUID) based on the configured primary_key_type setting. When non-integer
 * keys are used, it handles ID generation automatically.
 *
 * @author Brian Faust <brian@cline.sh>
 */
trait HasWardenPrimaryKey
{
    /**
     * Get the value indicating whether the IDs are incrementing.
     *
     * Returns false for ULID/UUID primary keys, true for auto-increment integers.
     * Overrides the model's default incrementing behavior based on Warden's
     * primary_key_type configuration.
     *
     * @return bool False if using ULID/UUID keys, true for auto-increment
     */
    public function getIncrementing(): bool
    {
        if (in_array($this->getKeyName(), $this->uniqueIds(), true)) {
            return false;
        }

        return $this->incrementing;
    }

    /**
     * Get the data type of the primary key.
     *
     * Returns 'string' for ULID/UUID keys, or the model's default key type
     * for auto-increment integers. Used by Eloquent for proper query parameter
     * binding and model serialization.
     *
     * @return string The PHP type of the primary key ('int' or 'string')
     */
    public function getKeyType(): string
    {
        if (in_array($this->getKeyName(), $this->uniqueIds(), true)) {
            return 'string';
        }

        return $this->keyType;
    }

    /**
     * Generate a new UUID/ULID for the model.
     *
     * Creates a unique identifier string based on the configured primary_key_type.
     * Returns null for auto-increment keys. Called automatically by Laravel's
     * HasUuids/HasUlids traits during model creation.
     *
     * @return null|string The generated unique ID, or null for auto-increment keys
     */
    public function newUniqueId(): ?string
    {
        return PrimaryKeyGenerator::generate()->value;
    }

    /**
     * Get the columns that should receive a unique identifier.
     *
     * Returns an array containing the primary key column name if using ULID/UUID,
     * or an empty array for auto-increment keys. Integrates with Laravel's unique
     * ID generation system for automatic key assignment during model creation.
     *
     * @return list<string> Array of column names requiring unique IDs, or empty for auto-increment
     */
    public function uniqueIds(): array
    {
        /** @var int|string $configValue */
        $configValue = config('warden.primary_key_type', 'id');
        $primaryKeyType = PrimaryKeyType::tryFrom($configValue) ?? PrimaryKeyType::ID;

        return match ($primaryKeyType) {
            PrimaryKeyType::ULID, PrimaryKeyType::UUID => [$this->getKeyName()],
            PrimaryKeyType::ID => [],
        };
    }

    /**
     * Boot the HasWardenPrimaryKey trait.
     *
     * Registers a creating event listener that generates the primary key
     * when using ULID or UUID primary key types. Validates that existing
     * values are compatible with the configured key type, throwing exceptions
     * for type mismatches.
     */
    protected static function bootHasWardenPrimaryKey(): void
    {
        static::creating(function (Model $model): void {
            $primaryKey = PrimaryKeyGenerator::generate();

            if ($primaryKey->isAutoIncrementing()) {
                return;
            }

            $keyName = $model->getKeyName();
            $existingValue = $model->getAttribute($keyName);

            // Generate UUID/ULID if no value exists
            if (!$existingValue) {
                $model->setAttribute($keyName, $primaryKey->value);

                return;
            }

            // Validate that the existing value is compatible with the key type
            if ($primaryKey->type === PrimaryKeyType::UUID && !is_string($existingValue)) {
                throw InvalidPrimaryKeyTypeException::cannotAssignNonStringToUuid($existingValue);
            }

            if ($primaryKey->type === PrimaryKeyType::ULID && !is_string($existingValue)) {
                throw InvalidPrimaryKeyTypeException::cannotAssignNonStringToUlid($existingValue);
            }
        });
    }
}
