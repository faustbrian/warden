<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Database\Ability;
use Cline\Warden\Database\Role;
use Cline\Warden\Database\Titles\AbilityTitle;
use Cline\Warden\Database\Titles\RoleTitle;
use Cline\Warden\Database\Titles\Title;
use PHPUnit\Framework\Attributes\Test;

describe('Title', function (): void {
    describe('Happy Paths', function (): void {
        test('creates role title instance from role model', function (): void {
            // Arrange
            $role = Role::query()->create(['name' => 'admin']);

            // Act
            $title = RoleTitle::from($role);

            // Assert
            expect($title)->toBeInstanceOf(RoleTitle::class);
            expect($title)->toBeInstanceOf(Title::class);
        });

        test('creates ability title instance from ability model', function (): void {
            // Arrange
            $ability = Ability::query()->create(['name' => 'edit-posts']);

            // Act
            $title = AbilityTitle::from($ability);

            // Assert
            expect($title)->toBeInstanceOf(AbilityTitle::class);
            expect($title)->toBeInstanceOf(Title::class);
        });

        test('returns generated title string from to string', function (): void {
            // Arrange
            $role = Role::query()->create(['name' => 'super_admin']);

            // Act
            $title = RoleTitle::from($role);
            $result = $title->toString();

            // Assert
            expect($result)->toBeString();
            expect($result)->toBe('Super admin');
        });

        test('capitalizes first letter of simple string', function (): void {
            // Arrange
            $role = Role::query()->create(['name' => 'admin']);

            // Act
            $title = RoleTitle::from($role);

            // Assert
            expect($title->toString())->toBe('Admin');
        });

        test('converts underscores to spaces in role name', function (): void {
            // Arrange
            $role = Role::query()->create(['name' => 'site_admin']);

            // Act
            $title = RoleTitle::from($role);

            // Assert
            expect($title->toString())->toBe('Site admin');
        });

        test('converts dashes to spaces in role name', function (): void {
            // Arrange
            $role = Role::query()->create(['name' => 'site-admin']);

            // Act
            $title = RoleTitle::from($role);

            // Assert
            expect($title->toString())->toBe('Site admin');
        });

        test('preserves spaces in original string', function (): void {
            // Arrange
            $role = Role::query()->create(['name' => 'site admin']);

            // Act
            $title = RoleTitle::from($role);

            // Assert
            expect($title->toString())->toBe('Site admin');
        });

        test('converts camel case to spaced words', function (): void {
            // Arrange
            $role = Role::query()->create(['name' => 'siteAdmin']);

            // Act
            $title = RoleTitle::from($role);

            // Assert
            expect($title->toString())->toBe('Site admin');
        });

        test('converts studly case to spaced words', function (): void {
            // Arrange
            $role = Role::query()->create(['name' => 'SiteAdmin']);

            // Act
            $title = RoleTitle::from($role);

            // Assert
            expect($title->toString())->toBe('Site admin');
        });

        test('adds space before hash symbol in string', function (): void {
            // Arrange
            $role = Role::query()->create(['name' => 'admin#1']);

            // Act
            $title = RoleTitle::from($role);

            // Assert
            $this->assertStringContainsString(' #', $title->toString());
        });

        test('handles complex ability title generation', function (): void {
            // Arrange
            $ability = Ability::query()->create(['name' => 'ban-users']);

            // Act
            $title = AbilityTitle::from($ability);

            // Assert
            expect($title->toString())->toBeString();
            expect($title->toString())->toBe('Ban users');
        });
    });

    describe('Sad Paths', function (): void {
        test('handles empty string input gracefully', function (): void {
            // Arrange
            $role = Role::query()->create(['name' => '']);

            // Act
            $title = RoleTitle::from($role);

            // Assert
            expect($title->toString())->toBeString();
            expect($title->toString())->toBe('');
        });

        test('handles string with only special characters', function (): void {
            // Arrange
            $role = Role::query()->create(['name' => '___']);

            // Act
            $title = RoleTitle::from($role);

            // Assert
            expect($title->toString())->toBeString();
            // Three underscores become spaces, trimmed by snake_case conversion
        });
    });

    describe('Edge Cases', function (): void {
        test('collapses multiple consecutive underscores', function (): void {
            // Arrange
            $role = Role::query()->create(['name' => 'super___admin']);

            // Act
            $title = RoleTitle::from($role);

            // Assert
            expect($title->toString())->toBe('Super admin');
            $this->assertStringNotContainsString('  ', $title->toString());
        });

        test('collapses multiple consecutive dashes', function (): void {
            // Arrange
            $role = Role::query()->create(['name' => 'super---admin']);

            // Act
            $title = RoleTitle::from($role);

            // Assert
            expect($title->toString())->toBe('Super admin');
            $this->assertStringNotContainsString('  ', $title->toString());
        });

        test('handles mixed underscores and dashes', function (): void {
            // Arrange
            $role = Role::query()->create(['name' => 'site_admin-user']);

            // Act
            $title = RoleTitle::from($role);

            // Assert
            expect($title->toString())->toBe('Site admin user');
        });

        test('adds space before hash in middle of string', function (): void {
            // Arrange
            $role = Role::query()->create(['name' => 'user#admin']);

            // Act
            $title = RoleTitle::from($role);

            // Assert
            $this->assertStringContainsString(' #', $title->toString());
        });

        test('handles hash at start of string', function (): void {
            // Arrange
            $role = Role::query()->create(['name' => '#admin']);

            // Act
            $title = RoleTitle::from($role);

            // Assert
            expect($title->toString())->toBeString();

            // Hash at start shouldn't get space before it
            expect($title->toString())->toStartWith('#');
        });

        test('handles multiple hash symbols correctly', function (): void {
            // Arrange
            $role = Role::query()->create(['name' => 'admin#1#2']);

            // Act
            $title = RoleTitle::from($role);

            // Assert
            expect($title->toString())->toBeString();
            $this->assertStringContainsString(' #', $title->toString());
        });

        test('handles numeric string values', function (): void {
            // Arrange
            $role = Role::query()->create(['name' => '123']);

            // Act
            $title = RoleTitle::from($role);

            // Assert
            expect($title->toString())->toEqual('123');
        });

        test('handles alphanumeric combinations', function (): void {
            // Arrange
            $role = Role::query()->create(['name' => 'admin123user']);

            // Act
            $title = RoleTitle::from($role);

            // Assert
            expect($title->toString())->toBeString();
            $this->assertStringContainsString('admin', mb_strtolower($title->toString()));
            $this->assertStringContainsString('user', mb_strtolower($title->toString()));
        });

        test('handles unicode characters properly', function (): void {
            // Arrange
            $role = Role::query()->create(['name' => 'café_admin']);

            // Act
            $title = RoleTitle::from($role);

            // Assert
            $this->assertStringContainsString('café', mb_strtolower($title->toString()));
            $this->assertStringContainsString('admin', mb_strtolower($title->toString()));
        });

        test('handles single character input', function (): void {
            // Arrange
            $role = Role::query()->create(['name' => 'a']);

            // Act
            $title = RoleTitle::from($role);

            // Assert
            expect($title->toString())->toBe('A');
        });

        test('handles all uppercase strings with spacing', function (): void {
            // Arrange
            $role = Role::query()->create(['name' => 'ADMIN']);

            // Act
            $title = RoleTitle::from($role);

            // Assert
            // ADMIN becomes a_d_m_i_n (snake_case), then A d m i n (spaces+ucfirst)
            expect($title->toString())->toBe('A d m i n');
        });

        test('humanizes various string formats correctly', function (string $input, string $expected): void {
            // Arrange
            $role = Role::query()->create(['name' => $input]);

            // Act
            $title = RoleTitle::from($role);

            // Assert
            expect($title->toString())->toBe($expected);
        })->with('provideHumanizes_various_string_formats_correctlyCases');

        test('factory method returns correct static type', function (): void {
            // Arrange
            $role = Role::query()->create(['name' => 'admin']);
            $ability = Ability::query()->create(['name' => 'edit']);

            // Act
            $roleTitle = RoleTitle::from($role);
            $abilityTitle = AbilityTitle::from($ability);

            // Assert
            expect($roleTitle)->toBeInstanceOf(RoleTitle::class);
            expect($abilityTitle)->toBeInstanceOf(AbilityTitle::class);
            $this->assertNotInstanceOf(AbilityTitle::class, $roleTitle);
            $this->assertNotInstanceOf(RoleTitle::class, $abilityTitle);
        });

        test('handles very long input strings', function (): void {
            // Arrange
            $longName = str_repeat('super_', 40).'admin';
            $role = Role::query()->create(['name' => $longName]);

            // Act
            $title = RoleTitle::from($role);

            // Assert
            expect($title->toString())->toBeString();
            expect($title->toString())->toStartWith('Super ');
            expect($title->toString())->toEndWith(' admin');
        });
    });
});

/**
 * Data provider for various string format humanization tests.
 *
 * @return iterable<string, array{string, string}>
 */
dataset('provideHumanizes_various_string_formats_correctlyCases', function () {
    yield 'simple lowercase' => ['admin', 'Admin'];

    yield 'with underscore' => ['super_admin', 'Super admin'];

    yield 'with dash' => ['super-admin', 'Super admin'];

    yield 'with space' => ['super admin', 'Super admin'];

    yield 'camelCase' => ['superAdmin', 'Super admin'];

    yield 'StudlyCase' => ['SuperAdmin', 'Super admin'];

    yield 'multiple underscores' => ['site_admin_user', 'Site admin user'];

    yield 'multiple dashes' => ['site-admin-user', 'Site admin user'];

    yield 'mixed separators' => ['site_admin-user', 'Site admin user'];

    yield 'with number' => ['admin2', 'Admin2'];

    yield 'trailing underscore' => ['admin_', 'Admin '];

    yield 'leading underscore' => ['_admin', ' admin'];

    yield 'ALL_CAPS_UNDERSCORE' => ['ALL_CAPS', 'A l l c a p s'];
});
