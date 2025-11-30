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
 * Exception thrown when an incompatible value is assigned to a primary key.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidPrimaryKeyTypeException extends InvalidArgumentException
{
    public static function cannotAssignNonStringToUuid(mixed $existingValue): self
    {
        return new self(
            'Cannot assign non-string value to UUID primary key. '.
            'Got: '.gettype($existingValue),
        );
    }

    public static function cannotAssignNonStringToUlid(mixed $existingValue): self
    {
        return new self(
            'Cannot assign non-string value to ULID primary key. '.
            'Got: '.gettype($existingValue),
        );
    }
}
