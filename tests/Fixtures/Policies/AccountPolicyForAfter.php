<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\Policies;

/**
 * @author Brian Faust <brian@cline.sh>
 */
final class AccountPolicyForAfter
{
    public function view($user, $account): mixed
    {
        if ($account->name === 'true') {
            return true;
        }

        if ($account->name === 'false') {
            return false;
        }

        return null;
    }
}
