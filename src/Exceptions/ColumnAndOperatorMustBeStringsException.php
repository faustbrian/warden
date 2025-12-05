<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Exceptions;

/**
 * Exception thrown when value constraint parameters are not strings.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ColumnAndOperatorMustBeStringsException extends ConstraintParameterException
{
    public static function forValueConstraint(): self
    {
        return new self('Column and operator must be strings');
    }
}
