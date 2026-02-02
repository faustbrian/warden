<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Warden\Exceptions;

use BadMethodCallException;

/**
 * Exception thrown when a model does not have the required roles() method.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class ModelMustHaveRolesMethodException extends BadMethodCallException implements WardenException
{
    public static function forAuthority(): self
    {
        return new self('Model must have roles() method');
    }
}
