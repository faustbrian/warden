<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Warden as WardenClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\TestsClipboards;
use Tests\Fixtures\Models\Account;
use Tests\Fixtures\Policies\AccountPolicyForAfter;

uses(TestsClipboards::class);

describe('Before Policies (Policy Integration)', function (): void {
    describe('Happy Paths', function (): void {
        test('respects warden permissions when runBeforePolicies enabled even if policy forbids', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();
            setUpWithPolicy($warden);
            $account = Account::query()->create(['name' => 'false']);

            // Act
            $warden->allow($user)->to('view', $account);

            // Assert
            expect($warden->cannot('view', $account))->toBeTrue();

            // Act
            $warden->runBeforePolicies();

            // Assert
            expect($warden->can('view', $account))->toBeTrue();
        })->with('bouncerProvider');

        test('respects warden forbid when runBeforePolicies enabled even if policy allows', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();
            setUpWithPolicy($warden);
            $account = Account::query()->create(['name' => 'true']);

            // Act
            $warden->forbid($user)->to('view', $account);

            // Assert
            expect($warden->can('view', $account))->toBeTrue();

            // Act
            $warden->runBeforePolicies();

            // Assert
            expect($warden->cannot('view', $account))->toBeTrue();
        })->with('bouncerProvider');

        test('passes authorization when warden allows with runBeforePolicies enabled', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();
            setUpWithPolicy($warden);
            $account = Account::query()->create(['name' => 'ignored by policy']);

            // Act
            $warden->allow($user)->to('view', $account);

            // Assert
            expect($warden->can('view', $account))->toBeTrue();

            // Act
            $warden->runBeforePolicies();

            // Assert
            expect($warden->can('view', $account))->toBeTrue();
        })->with('bouncerProvider');
    });

    describe('Sad Paths', function (): void {
        test('fails authorization when warden does not allow with runBeforePolicies enabled', function ($provider): void {
            // Arrange
            [$warden, $user] = $provider();
            setUpWithPolicy($warden);
            $account = Account::query()->create(['name' => 'ignored by policy']);

            // Assert
            expect($warden->cannot('view', $account))->toBeTrue();

            // Act
            $warden->runBeforePolicies();

            // Assert
            expect($warden->cannot('view', $account))->toBeTrue();
        })->with('bouncerProvider');
    });
});

/**
 * Set up the given Bouncer instance with the test policy.
 *
 * @param Silber\Buoncer\Bouncer $warden
 */
function setUpWithPolicy(WardenClass $warden): void
{
    $warden->gate()->policy(Account::class, AccountPolicyForAfter::class);
}
