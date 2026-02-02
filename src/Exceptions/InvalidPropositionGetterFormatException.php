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
 * Exception thrown when proposition getter retrieves invalid format.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidPropositionGetterFormatException extends InvalidArgumentException implements WardenException
{
    public static function missingMethodKey(): self
    {
        return new self('Invalid proposition format - expected array with method key');
    }
}
