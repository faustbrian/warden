<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Clipboard\CachedClipboard;
use Cline\Warden\Clipboard\Clipboard;
use Illuminate\Cache\NullStore;
use Tests\Concerns\TestsClipboards;
use Tests\Fixtures\Models\User;

// Create provider closures that will work in Pest context
dataset('bouncerProvider', [
    'basic clipboard' => fn (): Closure => fn ($authoriesCount = 1, $authority = User::class): array => TestsClipboards::provideBouncer(
        new Clipboard(),
        $authoriesCount,
        $authority,
    ),
    'null cached clipboard' => fn (): Closure => fn ($authoriesCount = 1, $authority = User::class): array => TestsClipboards::provideBouncer(
        new CachedClipboard(
            new NullStore(),
        ),
        $authoriesCount,
        $authority,
    ),
]);
