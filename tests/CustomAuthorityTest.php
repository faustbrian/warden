<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\TestsClipboards;
use Tests\Fixtures\Models\Account;

uses(TestsClipboards::class);

describe('Custom Authority (Account Model)', function (): void {
    describe('Happy Paths', function (): void {
        test('grants and removes abilities for custom authority model', function ($provider): void {
            // Arrange
            [$warden, $account] = $provider(1, Account::class);

            // Act
            $warden->allow($account)->to('edit-site');

            // Assert
            expect($warden->can('edit-site'))->toBeTrue();

            // Act
            $warden->disallow($account)->to('edit-site');

            // Assert
            expect($warden->cannot('edit-site'))->toBeTrue();
        })->with('bouncerProvider');

        test('assigns and retracts roles for custom authority model', function ($provider): void {
            // Arrange
            [$warden, $account] = $provider(1, Account::class);

            // Act
            $warden->allow('admin')->to('edit-site');
            $warden->assign('admin')->to($account);

            $editor = $warden->role()->create(['name' => 'editor']);
            $warden->allow($editor)->to('edit-site');
            $warden->assign($editor)->to($account);

            // Assert
            expect($warden->can('edit-site'))->toBeTrue();

            // Act
            $warden->retract('admin')->from($account);
            $warden->retract($editor)->from($account);

            // Assert
            expect($warden->cannot('edit-site'))->toBeTrue();
        })->with('bouncerProvider');

        test('removes role abilities when role is disallowed', function ($provider): void {
            // Arrange
            [$warden, $account] = $provider(1, Account::class);

            // Act
            $warden->allow('admin')->to('edit-site');
            $warden->disallow('admin')->to('edit-site');
            $warden->assign('admin')->to($account);

            // Assert
            expect($warden->cannot('edit-site'))->toBeTrue();
        })->with('bouncerProvider');

        test('checks single role assignment for custom authority model', function ($provider): void {
            // Arrange
            [$warden, $account] = $provider(1, Account::class);

            // Assert
            expect($warden->is($account)->notA('moderator'))->toBeTrue();
            expect($warden->is($account)->notAn('editor'))->toBeTrue();
            expect($warden->is($account)->an('admin'))->toBeFalse();

            // Arrange
            $warden = $this->bouncer($account = Account::query()->create());

            // Act
            $warden->assign('moderator')->to($account);
            $warden->assign('editor')->to($account);

            // Assert
            expect($warden->is($account)->a('moderator'))->toBeTrue();
            expect($warden->is($account)->an('editor'))->toBeTrue();
            expect($warden->is($account)->notAn('editor'))->toBeFalse();
            expect($warden->is($account)->an('admin'))->toBeFalse();
        })->with('bouncerProvider');

        test('checks multiple role assignments for custom authority model', function ($provider): void {
            // Arrange
            [$warden, $account] = $provider(1, Account::class);

            // Assert
            expect($warden->is($account)->notAn('editor', 'moderator'))->toBeTrue();
            expect($warden->is($account)->notAn('admin', 'moderator'))->toBeTrue();

            // Arrange
            $warden = $this->bouncer($account = Account::query()->create());

            // Act
            $warden->assign('moderator')->to($account);
            $warden->assign('editor')->to($account);

            // Assert
            expect($warden->is($account)->a('subscriber', 'moderator'))->toBeTrue();
            expect($warden->is($account)->an('admin', 'editor'))->toBeTrue();
            expect($warden->is($account)->all('editor', 'moderator'))->toBeTrue();
            expect($warden->is($account)->notAn('editor', 'moderator'))->toBeFalse();
            expect($warden->is($account)->all('admin', 'moderator'))->toBeFalse();
        })->with('bouncerProvider');
    });
});
