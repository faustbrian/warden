<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Database\Role;
use Tests\TestCase;

pest()->extend(TestCase::class)->in(__DIR__);

function role(string $name)
{
    return Role::query()->create(['name' => $name]);
}
