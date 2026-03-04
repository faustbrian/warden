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
 * Exception thrown when proposition setter receives invalid format.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidPropositionSetterFormatException extends InvalidArgumentException implements WardenException
{
    public static function mustBeArrayConfig(): self
    {
        return new self('Proposition must be array config with method and params keys');
    }
}
