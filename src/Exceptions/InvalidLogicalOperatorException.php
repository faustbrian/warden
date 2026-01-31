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
 * Abstract base exception for all logical operator validation errors.
 *
 * Consumers can catch this to handle any logical operator error.
 *
 * @author Brian Faust <brian@cline.sh>
 */
abstract class InvalidLogicalOperatorException extends InvalidArgumentException implements WardenException
{
    // Abstract base - no factory methods
}
