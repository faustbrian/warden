<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Exceptions;

use RuntimeException;

/**
 * Exception thrown when the gate instance has not been set.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class GateNotConfiguredException extends RuntimeException implements WardenException
{
    public static function notSet(): self
    {
        return new self('The gate instance has not been set.');
    }
}
