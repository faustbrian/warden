<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Exceptions;

/**
 * Exception thrown when an operator is not a string.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class OperatorMustBeStringException extends ConstraintParameterException
{
    public static function forConstraint(): self
    {
        return new self('Operator must be a string');
    }
}
