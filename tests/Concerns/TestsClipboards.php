<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Concerns;

use Cline\Warden\Clipboard\CachedClipboard;
use Cline\Warden\Clipboard\Clipboard;
use Cline\Warden\Contracts\ClipboardInterface;
use Illuminate\Cache\NullStore;
use Illuminate\Container\Container;
use Tests\Fixtures\Models\User;
use Tests\TestCase;

use function array_map;
use function array_merge;
use function range;

/**
 * @author Brian Faust <brian@cline.sh>
 */
trait TestsClipboards
{
    /**
     * Provides a bouncer instance (and users) for each clipboard, respectively.
     */
    public static function bouncerProvider(): array
    {
        return [
            'basic clipboard' => [
                fn ($authoriesCount = 1, $authority = User::class): array => static::provideBouncer(
                    new Clipboard(),
                    $authoriesCount,
                    $authority,
                ),
            ],
            'null cached clipboard' => [
                fn ($authoriesCount = 1, $authority = User::class): array => static::provideBouncer(
                    new CachedClipboard(
                        new NullStore(),
                    ),
                    $authoriesCount,
                    $authority,
                ),
            ],
        ];
    }

    /**
     * Provide the bouncer instance (with its user) using the given clipboard.
     *
     * @param \Cline\Warden\Clipboard $clipboard
     * @param int                     $authoriesCount
     * @param string                  $authority
     */
    public static function provideBouncer($clipboard, $authoriesCount, $authority): array
    {
        // Register the specific clipboard type in the container
        Container::getInstance()
            ->instance(ClipboardInterface::class, $clipboard);

        $authorities = array_map(
            fn () => $authority::create(),
            range(0, $authoriesCount),
        );

        $warden = TestCase::bouncer($authorities[0]);

        return array_merge([$warden], $authorities);
    }
}
