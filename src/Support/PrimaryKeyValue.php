<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Support;

use Cline\Warden\Enums\PrimaryKeyType;

/**
 * Value object representing a primary key configuration.
 *
 * Encapsulates both the type of primary key (auto-increment, ULID, UUID)
 * and the generated value for non-auto-increment types. Provides helper
 * methods to determine if a value should be explicitly set or left to
 * database auto-increment.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class PrimaryKeyValue
{
    /**
     * Create a new primary key value instance.
     *
     * @param PrimaryKeyType $type  The type of primary key
     * @param null|string    $value The generated value (null for auto-increment)
     */
    public function __construct(
        public PrimaryKeyType $type,
        public ?string $value,
    ) {}

    /**
     * Check if this is an auto-incrementing primary key.
     *
     * @return bool True if the primary key type is ID (auto-increment)
     */
    public function isAutoIncrementing(): bool
    {
        return $this->type === PrimaryKeyType::ID;
    }

    /**
     * Check if a value should be generated.
     *
     * @return bool True if the type is ULID or UUID
     */
    public function requiresValue(): bool
    {
        return $this->type !== PrimaryKeyType::ID;
    }
}
