<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Exceptions;

/**
 * Exception thrown when a column name is not a string.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ColumnNameMustBeStringException extends ConstraintParameterException
{
    public static function forWhereColumn(): self
    {
        return new self('Column name must be a string');
    }
}
