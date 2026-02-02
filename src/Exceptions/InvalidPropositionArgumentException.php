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
 * Exception thrown when invalid arguments are provided to proposition methods.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidPropositionArgumentException extends InvalidArgumentException implements WardenException
{
    public static function requiresAtLeastOne(): self
    {
        return new self('At least one proposition is required');
    }
}
