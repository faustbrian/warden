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
 * Exception thrown when attempting to unset variables on PropositionBuilder.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class CannotUnsetPropositionBuilderVariablesException extends BadMethodCallException implements WardenException
{
    public static function variablesManagedByRuleBuilder(): self
    {
        return new self('Cannot unset variables on PropositionBuilder');
    }
}
