<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\TestsClipboards;

uses(TestsClipboards::class);

describe('Reported Issues (Regressions)', function (): void {
    describe('Regressions', function (): void {
        test('forbids ability on specific model while allowing others', function ($provider): void {
            // Arrange
            [$warden, $user1, $user2, $user3] = $provider(3);

            // Act
            $warden->allow($user1)->everything();
            $warden->forbid($user1)->to('edit', $user2);

            // Assert
            expect($warden->cannot('edit', $user2))->toBeTrue();
            expect($warden->can('edit', $user3))->toBeTrue();
        })->with('bouncerProvider');
    });
});
