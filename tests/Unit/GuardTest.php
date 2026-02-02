<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Clipboard\CachedClipboard;
use Cline\Warden\Clipboard\Clipboard;
use Cline\Warden\Contracts\ClipboardInterface;
use Cline\Warden\Database\Ability;
use Cline\Warden\Database\Models;
use Cline\Warden\Guard;
use Illuminate\Auth\Access\Gate;
use Illuminate\Auth\Access\Response;
use Illuminate\Cache\ArrayStore;
use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Models\User;

beforeEach(function (): void {
    $this->user = User::query()->create();
    $this->clipboard = new Clipboard();
    $this->guard = new Guard($this->clipboard);
    $this->gate = new Gate(Container::getInstance(), fn () => $this->user);
});
afterEach(function (): void {
    Models::reset();
    Mockery::close();
});
test('gets the clipboard instance', function (): void {
    // Arrange - clipboard set in setUp
    // Act
    $result = $this->guard->getClipboard();

    // Assert
    expect($result)->toBe($this->clipboard);
    expect($result)->toBeInstanceOf(ClipboardInterface::class);
})->group('happy-path');
test('sets a new clipboard instance', function (): void {
    // Arrange
    $newClipboard = new Clipboard();

    // Act
    $result = $this->guard->setClipboard($newClipboard);

    // Assert
    expect($result)->toBe($this->guard);
    expect($this->guard->getClipboard())->toBe($newClipboard);
    $this->assertNotSame($this->clipboard, $this->guard->getClipboard());
})->group('happy-path');
test('replaces clipboard instance maintaining fluent interface', function (): void {
    // Arrange
    $clipboard1 = new Clipboard();
    $clipboard2 = new CachedClipboard(
        new ArrayStore(),
    );

    // Act
    $guard = $this->guard->setClipboard($clipboard1)->setClipboard($clipboard2);

    // Assert
    expect($guard)->toBe($this->guard);
    expect($this->guard->getClipboard())->toBe($clipboard2);
})->group('happy-path');
test('returns false when using non cached clipboard', function (): void {
    // Arrange - using regular Clipboard from setUp
    // Act
    $result = $this->guard->usesCachedClipboard();

    // Assert
    expect($result)->toBeFalse();
})->group('happy-path');
test('returns true when using cached clipboard', function (): void {
    // Arrange
    $cachedClipboard = new CachedClipboard(
        new ArrayStore(),
    );
    $this->guard->setClipboard($cachedClipboard);

    // Act
    $result = $this->guard->usesCachedClipboard();

    // Assert
    expect($result)->toBeTrue();
})->group('happy-path');
test('gets current slot value when called without arguments', function (): void {
    // Arrange - default slot is 'after'
    // Act
    $result = $this->guard->slot();

    // Assert
    expect($result)->toBe('after');
})->group('happy-path');
test('sets slot to before and returns guard instance', function (): void {
    // Arrange - default is 'after'
    // Act
    $result = $this->guard->slot('before');

    // Assert
    expect($result)->toBe($this->guard);
    expect($this->guard->slot())->toBe('before');
})->group('happy-path');
test('sets slot to after and returns guard instance', function (): void {
    // Arrange
    $this->guard->slot('before');

    // Act
    $result = $this->guard->slot('after');

    // Assert
    expect($result)->toBe($this->guard);
    expect($this->guard->slot())->toBe('after');
})->group('happy-path');
test('throws exception when setting invalid slot value', function (): void {
    // Arrange
    $invalidSlot = 'invalid';

    // Act & Assert
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('invalid is an invalid gate slot');
    $this->guard->slot($invalidSlot);
})->group('sad-path');
test('throws exception for empty string slot', function (): void {
    // Arrange
    $emptySlot = '';

    // Act & Assert
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage(' is an invalid gate slot');
    $this->guard->slot($emptySlot);
})->group('edge-case');
test('registers guard callbacks at gate and returns guard instance', function (): void {
    // Arrange - gate set in setUp
    // Act
    $result = $this->guard->registerAt($this->gate);

    // Assert
    expect($result)->toBe($this->guard);
})->group('happy-path');
test('before callback grants permission when slot is before and ability exists', function (): void {
    // Arrange
    $this->guard->slot('before');
    $this->guard->registerAt($this->gate);

    $ability = Ability::query()->create(['name' => 'edit-posts']);
    $this->user->abilities()->attach($ability);

    // Act
    $result = $this->gate->allows('edit-posts');

    // Assert
    expect($result)->toBeTrue();
})->group('happy-path');
test('before callback returns null when slot is after', function (): void {
    // Arrange
    $this->guard->slot('after');
    $this->guard->registerAt($this->gate);

    $ability = Ability::query()->create(['name' => 'edit-posts']);
    $this->user->abilities()->attach($ability);

    // Act
    $result = $this->gate->allows('edit-posts');

    // Assert
    expect($result)->toBeTrue();
})->group('happy-path');
test('after callback grants permission when slot is after and ability exists', function (): void {
    // Arrange
    $this->guard->slot('after');
    $this->guard->registerAt($this->gate);

    $ability = Ability::query()->create(['name' => 'edit-posts']);
    $this->user->abilities()->attach($ability);

    // Act
    $result = $this->gate->allows('edit-posts');

    // Assert
    expect($result)->toBeTrue();
})->group('happy-path');
test('after callback returns null when slot is before', function (): void {
    // Arrange
    $this->guard->slot('before');
    $this->guard->registerAt($this->gate);

    // No ability granted
    // Act
    $result = $this->gate->allows('edit-posts');

    // Assert
    expect($result)->toBeFalse();
})->group('happy-path');
test('before callback denies permission when ability is forbidden', function (): void {
    // Arrange
    $this->guard->slot('before');
    $this->guard->registerAt($this->gate);

    $ability = Ability::query()->create(['name' => 'edit-posts']);
    $this->user->abilities()->attach($ability, ['forbidden' => true]);

    // Act
    $result = $this->gate->allows('edit-posts');

    // Assert
    expect($result)->toBeFalse();
})->group('sad-path');
test('after callback denies permission when ability is forbidden', function (): void {
    // Arrange
    $this->guard->slot('after');
    $this->guard->registerAt($this->gate);

    $ability = Ability::query()->create(['name' => 'edit-posts']);
    $this->user->abilities()->attach($ability, ['forbidden' => true]);

    // Act
    $result = $this->gate->allows('edit-posts');

    // Assert
    expect($result)->toBeFalse();
})->group('sad-path');
test('before callback returns null when no permission exists', function (): void {
    // Arrange
    $this->guard->slot('before');
    $this->guard->registerAt($this->gate);

    // No ability granted or forbidden
    // Act
    $result = $this->gate->allows('edit-posts');

    // Assert
    expect($result)->toBeFalse();
})->group('happy-path');
test('after callback returns null when no permission exists', function (): void {
    // Arrange
    $this->guard->slot('after');
    $this->guard->registerAt($this->gate);

    // No ability granted or forbidden
    // Act
    $result = $this->gate->allows('edit-posts');

    // Assert
    expect($result)->toBeFalse();
})->group('happy-path');
test('before callback handles model instance argument without throwing error', function (): void {
    // Arrange
    $this->guard->slot('before');
    $this->guard->registerAt($this->gate);

    $targetUser = User::query()->create();

    // Act - Should not throw exception even without matching ability
    // This tests that the Guard properly handles Model arguments
    $result = $this->gate->allows('edit', $targetUser);

    // Assert - Will be false since no ability granted, but no exception thrown
    expect($result)->toBeFalse();
})->group('happy-path');
test('before callback handles string model class argument without throwing error', function (): void {
    // Arrange
    $this->guard->slot('before');
    $this->guard->registerAt($this->gate);

    // Act - Should not throw exception even without matching ability
    // This tests that the Guard properly handles string Model class arguments
    $result = $this->gate->allows('create', User::class);

    // Assert - Will be false since no ability granted, but no exception thrown
    expect($result)->toBeFalse();
})->group('happy-path');
test('before callback skips check when more than two arguments provided', function (): void {
    // Arrange
    $this->guard->slot('before');
    $this->guard->registerAt($this->gate);

    $targetUser = User::query()->create();

    // Act
    $result = $this->gate->allows('edit', [$targetUser, 'extra', 'arguments']);

    // Assert
    expect($result)->toBeFalse();
})->group('edge-case');
test('after callback skips check when more than two arguments provided', function (): void {
    // Arrange
    $this->guard->slot('after');
    $this->guard->registerAt($this->gate);

    $targetUser = User::query()->create();

    // Act
    $result = $this->gate->allows('edit', [$targetUser, 'extra', 'arguments']);

    // Assert
    expect($result)->toBeFalse();
})->group('edge-case');
test('before callback skips check when argument is not model or string', function (): void {
    // Arrange
    $this->guard->slot('before');
    $this->guard->registerAt($this->gate);

    // Act
    $result = $this->gate->allows('edit', [123]);

    // Assert
    expect($result)->toBeFalse();
})->group('edge-case');
test('after callback skips check when argument is not model or string', function (): void {
    // Arrange
    $this->guard->slot('after');
    $this->guard->registerAt($this->gate);

    // Act
    $result = $this->gate->allows('edit', [123]);

    // Assert
    expect($result)->toBeFalse();
})->group('edge-case');
test('after callback respects existing policy result', function (): void {
    // Arrange
    $this->guard->slot('after');
    $this->guard->registerAt($this->gate);

    // Define a policy that returns true
    $this->gate->define('custom-ability', fn (): true => true);

    // User has no abilities granted, but policy says yes
    // After callback should respect the policy result
    // Act
    $result = $this->gate->allows('custom-ability');

    // Assert
    expect($result)->toBeTrue();
})->group('happy-path');
test('after callback provides result when no policy exists', function (): void {
    // Arrange
    $this->guard->slot('after');
    $this->guard->registerAt($this->gate);

    $ability = Ability::query()->create(['name' => 'publish-posts']);
    $this->user->abilities()->attach($ability);

    // Act
    $result = $this->gate->allows('publish-posts');

    // Assert
    expect($result)->toBeTrue();
})->group('happy-path');
test('before callback can override policy when running before policies', function (): void {
    // Arrange
    $this->guard->slot('before');
    $this->guard->registerAt($this->gate);

    // Policy denies access
    $this->gate->define('restricted-action', fn (): false => false);

    // But Warden grants permission
    $ability = Ability::query()->create(['name' => 'restricted-action']);
    $this->user->abilities()->attach($ability);

    // Act
    $result = $this->gate->allows('restricted-action');

    // Assert
    expect($result)->toBeTrue();
    // Warden overrides policy
})->group('happy-path');
test('clipboard check returns response object with message when permission granted', function (): void {
    // Arrange
    $this->guard->slot('before');
    $this->guard->registerAt($this->gate);

    $ability = Ability::query()->create(['name' => 'test-ability']);
    $this->user->abilities()->attach($ability);

    // Act
    $response = $this->gate->inspect('test-ability');

    // Assert
    expect($response)->toBeInstanceOf(Response::class);
    expect($response->allowed())->toBeTrue();
    $this->assertStringContainsString('Bouncer granted permission via ability #', (string) $response->message());
    $this->assertStringContainsString((string) $ability->id, (string) $response->message());
})->group('happy-path');
test('works with cached clipboard implementation', function (): void {
    // Arrange
    $cachedClipboard = new CachedClipboard(
        new ArrayStore(),
    );
    $guard = new Guard($cachedClipboard);
    $guard->slot('before');
    $guard->registerAt($this->gate);

    $ability = Ability::query()->create(['name' => 'cached-ability']);
    $this->user->abilities()->attach($ability);

    // Act
    $result1 = $this->gate->allows('cached-ability');
    $result2 = $this->gate->allows('cached-ability');

    // Should hit cache
    // Assert
    expect($result1)->toBeTrue();
    expect($result2)->toBeTrue();
    expect($guard->usesCachedClipboard())->toBeTrue();
})->group('happy-path');
test('slot setting is chainable with other methods', function (): void {
    // Arrange
    $newClipboard = new Clipboard();

    // Act
    $result = $this->guard->slot('before')->setClipboard($newClipboard);

    // Assert
    expect($result)->toBe($this->guard);
    expect($this->guard->slot())->toBe('before');
    expect($this->guard->getClipboard())->toBe($newClipboard);
})->group('happy-path');
test('register at is chainable with slot configuration', function (): void {
    // Arrange
    $guard = new Guard(
        new Clipboard(),
    );

    // Act
    $result = $guard->slot('before')->registerAt($this->gate);

    // Assert
    expect($result)->toBe($guard);
    expect($guard->slot())->toBe('before');
})->group('happy-path');
test('throws exception for various invalid slot values', function (string $invalidValue): void {
    // Arrange - various invalid values provided by data provider
    // Act & Assert
    $this->expectException(InvalidArgumentException::class);
    $this->guard->slot($invalidValue);
})->with('provideThrows_exception_for_various_invalid_slot_valuesCases')->group('edge-case');

/**
 * Data provider for invalid slot values.
 *
 * @return Iterator<string, array<string>>
 */
dataset('provideThrows_exception_for_various_invalid_slot_valuesCases', function () {
    yield 'random string' => ['random'];

    yield 'uppercase BEFORE' => ['BEFORE'];

    yield 'uppercase AFTER' => ['AFTER'];

    yield 'mixed case Before' => ['Before'];

    yield 'numeric string' => ['123'];

    yield 'special chars' => ['@#$'];

    yield 'whitespace' => ['   '];

    yield 'before with space' => ['before '];

    yield 'after with space' => [' after'];
});
test('before callback handles null model argument', function (): void {
    // Arrange
    $this->guard->slot('before');
    $this->guard->registerAt($this->gate);

    $ability = Ability::query()->create(['name' => 'global-ability']);
    $this->user->abilities()->attach($ability);

    // Act
    $result = $this->gate->allows('global-ability', [null]);

    // Assert
    expect($result)->toBeTrue();
})->group('happy-path');
test('after callback handles null model argument', function (): void {
    // Arrange
    $this->guard->slot('after');
    $this->guard->registerAt($this->gate);

    $ability = Ability::query()->create(['name' => 'global-ability']);
    $this->user->abilities()->attach($ability);

    // Act
    $result = $this->gate->allows('global-ability', [null]);

    // Assert
    expect($result)->toBeTrue();
})->group('happy-path');
test('multiple guard instances can be registered at same gate', function (): void {
    // Arrange
    $guard1 = new Guard(
        new Clipboard(),
    );
    $guard2 = new Guard(
        new Clipboard(),
    );

    // Act
    $result1 = $guard1->registerAt($this->gate);
    $result2 = $guard2->registerAt($this->gate);

    // Assert
    expect($result1)->toBe($guard1);
    expect($result2)->toBe($guard2);
})->group('edge-case');
