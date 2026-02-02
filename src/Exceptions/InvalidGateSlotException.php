<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Exceptions;

use InvalidArgumentException;

use function sprintf;

/**
 * Exception thrown when an invalid gate slot is specified.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidGateSlotException extends InvalidArgumentException implements WardenException
{
    public static function invalidSlot(string $slot): self
    {
        return new self(sprintf('%s is an invalid gate slot', $slot));
    }
}
