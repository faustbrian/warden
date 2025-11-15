<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Database\Concerns;

use Cline\Warden\Enums\PrimaryKeyType;
use Cline\Warden\Support\PrimaryKeyGenerator;
use Illuminate\Database\Eloquent\Model;

use function config;

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
     * Initialize the HasWardenPrimaryKey trait.
     *
     * Configures the model's primary key behavior based on the configured type.
     * For ULID and UUID types, this sets incrementing to false and applies the
     * appropriate key type.
     */
    public function initializeHasWardenPrimaryKey(): void
    {
        $primaryKeyType = PrimaryKeyType::tryFrom(config('warden.primary_key_type', 'id')) ?? PrimaryKeyType::ID;

        match ($primaryKeyType) {
            PrimaryKeyType::ULID, PrimaryKeyType::UUID => $this->configureStringKey(),
            PrimaryKeyType::ID => null,
        };
    }

    /**
     * Get the columns that should receive a unique identifier.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return [$this->getKeyName()];
    }

    /**
     * Boot the HasWardenPrimaryKey trait.
     *
     * Registers a creating event listener that generates the primary key
     * when using ULID or UUID primary key types.
     */
    protected static function bootHasWardenPrimaryKey(): void
    {
        static::creating(function (Model $model): void {
            $primaryKey = PrimaryKeyGenerator::generate();

            if ($primaryKey->isAutoIncrementing()) {
                return;
            }

            $keyName = $model->getKeyName();

            if (!$model->getAttribute($keyName)) {
                $model->setAttribute($keyName, $primaryKey->value);
            }
        });
    }

    /**
     * Configure the model to use string-based primary keys (ULID/UUID).
     */
    protected function configureStringKey(): void
    {
        $this->incrementing = false;
        $this->keyType = 'string';
    }
}
