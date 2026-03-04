<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\Enums;

/**
 * @author Brian Faust <brian@cline.sh>
 */
enum GuardEnum: string
{
    case Web = 'web';
    case Api = 'api';
    case Rpc = 'rpc';
}
