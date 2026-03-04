<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Exceptions;

use InvalidArgumentException;

use function sprintf;

/**
 * Exception thrown when a specified model class does not exist.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ModelClassDoesNotExistException extends InvalidArgumentException implements WardenException
{
    public static function forClass(string $model): self
    {
        return new self(sprintf('Class %s does not exist', $model));
    }
}
