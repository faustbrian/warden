<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Exceptions;

use Throwable;

/**
 * Marker interface for all Warden package exceptions.
 *
 * Consumers can catch this interface to handle any exception
 * thrown by the Warden package.
 *
 * @author Brian Faust <brian@cline.sh>
 */
interface WardenException extends Throwable
{
    // Marker interface - no methods required
}
