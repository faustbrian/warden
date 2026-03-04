<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Exceptions;

use InvalidArgumentException;

use function gettype;

/**
 * Exception thrown when a non-string value is assigned to a UUID primary key.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class CannotAssignNonStringToUuidException extends InvalidArgumentException implements WardenException
{
    public static function forValue(mixed $existingValue): self
    {
        return new self(
            'Cannot assign non-string value to UUID primary key. '.
            'Got: '.gettype($existingValue),
        );
    }
}
