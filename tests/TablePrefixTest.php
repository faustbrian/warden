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

describe('Table Prefix Support', function (): void {
    describe('Happy Paths', function (): void {
        test('executes ability queries correctly with table prefix', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            // Act
            $warden->allow($user)->everything();

            // Assert
            expect($warden->can('do-something'))->toBeTrue();
        })->with('bouncerProvider');

        test('executes role queries correctly with table prefix', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();

            // Act
            $warden->assign('artisan')->to($user);

            // Assert
            expect($warden->is($user)->an('artisan'))->toBeTrue();
        })->with('bouncerProvider');
    });
});
