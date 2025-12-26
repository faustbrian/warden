<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Database\Scope\Scope;
use PHPUnit\Framework\Attributes\Test;

describe('Scope', function (): void {
    describe('Happy Paths', function (): void {
        test('appends scalar scope value to cache key', function (): void {
            // Arrange
            $scope = new Scope();
            $scope->to(42);

            $baseKey = 'cache-key';

            // Act
            $result = $scope->appendToCacheKey($baseKey);

            // Assert
            expect($result)->toBe('cache-key-42');
        });

        test('appends string scope value to cache key', function (): void {
            // Arrange
            $scope = new Scope();
            $scope->to('tenant-uuid-123');

            $baseKey = 'cache-key';

            // Act
            $result = $scope->appendToCacheKey($baseKey);

            // Assert
            expect($result)->toBe('cache-key-tenant-uuid-123');
        });

        test('serializes non scalar array scope value for cache key', function (): void {
            // Arrange
            $scope = new Scope();
            $scopeValue = ['tenant_id' => 42, 'organization_id' => 7];
            $scope->to($scopeValue);
            $baseKey = 'cache-key';

            // Act
            $result = $scope->appendToCacheKey($baseKey);

            // Assert
            $expectedSuffix = serialize($scopeValue);
            expect($result)->toBe('cache-key-'.$expectedSuffix);
            $this->assertStringContainsString('cache-key-', $result);
        });

        test('serializes non scalar object scope value for cache key', function (): void {
            // Arrange
            $scope = new Scope();
            $scopeValue = (object) ['tenant_id' => 42, 'organization_id' => 7];
            $scope->to($scopeValue);
            $baseKey = 'cache-key';

            // Act
            $result = $scope->appendToCacheKey($baseKey);

            // Assert
            $expectedSuffix = serialize($scopeValue);
            expect($result)->toBe('cache-key-'.$expectedSuffix);
            $this->assertStringContainsString('cache-key-', $result);
        });

        test('sets scope value', function (): void {
            // Arrange
            $scope = new Scope();

            // Act
            $scope->to(123);

            // Assert
            expect($scope->get())->toBe(123);
        });

        test('removes scope value', function (): void {
            // Arrange
            $scope = new Scope();
            $scope->to(123);

            // Act
            $scope->remove();

            // Assert
            expect($scope->get())->toBeNull();
        });

        test('configures relationship only scoping', function (): void {
            // Arrange
            $scope = new Scope();

            // Act
            $result = $scope->onlyRelations(true);

            // Assert
            expect($result)->toBe($scope);
        });

        test('configures role abilities scoping', function (): void {
            // Arrange
            $scope = new Scope();

            // Act
            $result = $scope->dontScopeRoleAbilities();

            // Assert
            expect($result)->toBe($scope);
        });
    });

    describe('Edge Cases', function (): void {
        test('returns original cache key when scope is null', function (): void {
            // Arrange
            $scope = new Scope();
            $baseKey = 'cache-key';

            // Act
            $result = $scope->appendToCacheKey($baseKey);

            // Assert
            expect($result)->toBe('cache-key');
        });
    });
});
