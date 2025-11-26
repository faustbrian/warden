<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Exceptions;

use RuntimeException;

/**
 * Exception thrown when attempting to use User model before configuration.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class UserModelNotConfiguredException extends RuntimeException
{
    public static function callSetUsersModelFirst(): self
    {
        return new self('User model not configured. Call Models::setUsersModel() first.');
    }
}
