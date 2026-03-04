<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Exceptions;

/**
 * Exception thrown when a logical operator value is not supported.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class UnsupportedLogicalOperatorException extends InvalidLogicalOperatorException
{
    public static function forOperator(string $operator): self
    {
        return new self($operator.' is an invalid logical operator');
    }
}
