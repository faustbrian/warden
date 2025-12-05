<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Exceptions;

use function get_debug_type;

/**
 * Exception thrown when a logical operator is not a string.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class LogicalOperatorMustBeStringException extends InvalidLogicalOperatorException
{
    public static function fromValue(mixed $operator): self
    {
        return new self('Logical operator must be a string, '.get_debug_type($operator).' given');
    }
}
