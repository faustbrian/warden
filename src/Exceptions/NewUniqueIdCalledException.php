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
 * Exception thrown when newUniqueId is called for numeric morph types.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class NewUniqueIdCalledException extends RuntimeException
{
    public static function forNumericMorphType(): self
    {
        return new self('newUniqueId should not be called for numeric morph type');
    }
}
