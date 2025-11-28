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
 * Exception thrown when a model identifier cannot be resolved to a valid key.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class InvalidModelIdentifierException extends InvalidArgumentException
{
    public static function cannotResolve(): self
    {
        return new self('Invalid model identifier');
    }
}
