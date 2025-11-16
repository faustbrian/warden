<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Support\CharDetector;
use Illuminate\Support\Str;

describe('CharDetector', function (): void {
    describe('UUID Detection', function (): void {
        test('detects valid lowercase UUID', function (): void {
            $uuid = '550e8400-e29b-41d4-a716-446655440000';
            expect(CharDetector::isUuid($uuid))->toBeTrue();
        });

        test('detects valid uppercase UUID', function (): void {
            $uuid = '550E8400-E29B-41D4-A716-446655440000';
            expect(CharDetector::isUuid($uuid))->toBeTrue();
        });

        test('detects Laravel generated UUID', function (): void {
            $uuid = (string) Str::uuid();
            expect(CharDetector::isUuid($uuid))->toBeTrue();
        });

        test('rejects invalid UUID format', function (): void {
            expect(CharDetector::isUuid('not-a-uuid'))->toBeFalse();
        });

        test('rejects ULID as UUID', function (): void {
            $ulid = (string) Str::ulid();
            expect(CharDetector::isUuid($ulid))->toBeFalse();
        });
    });

    describe('ULID Detection', function (): void {
        test('detects valid ULID', function (): void {
            $ulid = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
            expect(CharDetector::isUlid($ulid))->toBeTrue();
        });

        test('detects Laravel generated ULID', function (): void {
            $ulid = (string) Str::ulid();
            expect(CharDetector::isUlid($ulid))->toBeTrue();
        });

        test('rejects string with wrong length', function (): void {
            expect(CharDetector::isUlid('tooshort'))->toBeFalse();
        });

        test('rejects UUID as ULID', function (): void {
            $uuid = (string) Str::uuid();
            expect(CharDetector::isUlid($uuid))->toBeFalse();
        });

        test('rejects non-alphanumeric 26-char string', function (): void {
            expect(CharDetector::isUlid('abcdefghijklmnopqrstuvwx-'))->toBeFalse();
        });
    });

    describe('Combined Detection', function (): void {
        test('detects UUID as UuidOrUlid', function (): void {
            $uuid = (string) Str::uuid();
            expect(CharDetector::isUuidOrUlid($uuid))->toBeTrue();
        });

        test('detects ULID as UuidOrUlid', function (): void {
            $ulid = (string) Str::ulid();
            expect(CharDetector::isUuidOrUlid($ulid))->toBeTrue();
        });

        test('rejects plain string', function (): void {
            expect(CharDetector::isUuidOrUlid('admin'))->toBeFalse();
        });

        test('rejects role name', function (): void {
            expect(CharDetector::isUuidOrUlid('editor'))->toBeFalse();
        });
    });
});
