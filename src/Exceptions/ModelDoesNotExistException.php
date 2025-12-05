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
 * Exception thrown when a model instance has not been persisted to the database.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ModelDoesNotExistException extends InvalidArgumentException implements WardenException
{
    public static function useClassNameInstead(): self
    {
        return new self('The model does not exist. To edit access to all models, use the class name instead');
    }
}
