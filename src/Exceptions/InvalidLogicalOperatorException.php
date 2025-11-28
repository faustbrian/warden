<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Exceptions;

use InvalidArgumentException;

use function get_debug_type;
use function gettype;

/**
 * Exception thrown when an invalid logical operator is provided.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidLogicalOperatorException extends InvalidArgumentException
{
    public static function mustBeString(mixed $operator): self
    {
        return new self('Logical operator must be a string, '.get_debug_type($operator).' given');
    }

    public static function mustBeStringGotType(mixed $operator): self
    {
        return new self('Logical operator must be a string, got '.gettype($operator));
    }
}
