<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Contracts;

/**
 * Defines the contract for permission data migration operations.
 *
 * Enables migration of authorization data between different storage formats,
 * versions, or systems. Implementations handle the transformation and transfer
 * of abilities, roles, and permissions while maintaining data integrity.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface MigratorInterface
{
    /**
     * Execute the migration process.
     *
     * Performs the migration of authorization data from one format or system
     * to another. The specific source and destination are determined by the
     * implementation. Typically called during package upgrades, data exports,
     * or system integrations.
     */
    public function migrate(): void;
}
