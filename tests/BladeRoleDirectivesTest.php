<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\WardenServiceProvider;
use Illuminate\Support\Facades\Blade;
use Tests\Fixtures\Models\User;

it('supports role blade directive', function (): void {
    $this->app->register(WardenServiceProvider::class);

    $user = User::query()->create();
    $user->assign('admin');
    $this->actingAs($user);

    $output = Blade::render(<<<'BLADE'
@role('admin')
admin
@else
guest
@endrole
BLADE);

    expect(mb_trim($output))->toBe('admin');
});

it('supports unlessrole blade directive', function (): void {
    $this->app->register(WardenServiceProvider::class);

    $user = User::query()->create();
    $user->assign('admin');
    $this->actingAs($user);

    $output = Blade::render(<<<'BLADE'
@unlessrole('editor')
visible
@endrole
BLADE);

    expect(mb_trim($output))->toBe('visible');
});
