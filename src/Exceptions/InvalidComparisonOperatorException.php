<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Exceptions;

use InvalidArgumentException;

/**
 * Exception thrown when an invalid comparison operator is used in constraints.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidComparisonOperatorException extends InvalidArgumentException
{
    public static function unsupported(string $operator): self
    {
        return new self('Invalid operator: '.$operator);
    }
}
