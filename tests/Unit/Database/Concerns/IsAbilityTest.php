<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Warden\Constraints\Constraint;
use Cline\Warden\Constraints\Group as ConstraintGroup;
use Cline\Warden\Database\Ability;
use Cline\Warden\Database\Models;
use Cline\Warden\Database\Role;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Models\Account;
use Tests\Fixtures\Models\User;

test('applies tenant scope during creation', function (): void {
    // Arrange
    Models::scope()->to(42);

    // Act
    $ability = Ability::query()->create(['name' => 'test-ability']);

    // Assert
    expect($ability->scope)->toEqual(42);
})->group('happy-path');
test('generates automatic title when not provided', function (): void {
    // Arrange
    $attributes = ['name' => 'edit-posts'];

    // Act
    $ability = Ability::query()->create($attributes);

    // Assert
    expect($ability->title)->not->toBeNull();
    expect($ability->title)->toBeString();
})->group('happy-path');
test('preserves explicit title when provided', function (): void {
    // Arrange
    $attributes = ['name' => 'edit-posts', 'title' => 'Custom Title'];

    // Act
    $ability = Ability::query()->create($attributes);

    // Assert
    expect($ability->title)->toEqual('Custom Title');
})->group('happy-path');
test('creates and persists ability for model instance', function (): void {
    // Arrange
    $account = Account::query()->create(['name' => 'Test Account']);

    // Act
    $ability = Ability::createForModel($account, 'edit');

    // Assert
    expect($ability->exists)->toBeTrue();
    expect($ability->name)->toEqual('edit');
    expect($ability->subject_type)->toEqual(Account::class);
    expect($ability->subject_id)->toEqual($account->id);
    $this->assertDatabaseHas(Models::table('abilities'), [
        'id' => $ability->id,
        'subject_type' => Account::class,
        'subject_id' => $account->id,
    ]);
})->group('happy-path');
test('creates and persists ability for model class', function (): void {
    // Arrange
    $className = Account::class;

    // Act
    $ability = Ability::createForModel($className, 'create');

    // Assert
    expect($ability->exists)->toBeTrue();
    expect($ability->name)->toEqual('create');
    expect($ability->subject_type)->toEqual(Account::class);
    expect($ability->subject_id)->toBeNull();
    $this->assertDatabaseHas(Models::table('abilities'), [
        'id' => $ability->id,
        'subject_type' => Account::class,
    ]);
})->group('happy-path');
test('creates and persists global wildcard ability', function (): void {
    // Arrange
    $model = '*';

    // Act
    $ability = Ability::createForModel($model, 'admin');

    // Assert
    expect($ability->exists)->toBeTrue();
    expect($ability->name)->toEqual('admin');
    expect($ability->subject_type)->toEqual('*');
    $this->assertDatabaseHas(Models::table('abilities'), [
        'id' => $ability->id,
        'subject_type' => '*',
    ]);
})->group('happy-path');
test('creates ability with full attribute array', function (): void {
    // Arrange
    $account = Account::query()->create(['name' => 'Test Account']);
    $attributes = [
        'name' => 'delete',
        'title' => 'Delete Account',
        'only_owned' => true,
    ];

    // Act
    $ability = Ability::createForModel($account, $attributes);

    // Assert
    expect($ability->exists)->toBeTrue();
    expect($ability->name)->toEqual('delete');
    expect($ability->title)->toEqual('Delete Account');
    expect($ability->only_owned)->toBeTrue();
})->group('happy-path');
test('builds unpersisted ability for model instance', function (): void {
    // Arrange
    $account = Account::query()->create(['name' => 'Test Account']);

    // Act
    $ability = Ability::makeForModel($account, 'edit');

    // Assert
    expect($ability->exists)->toBeFalse();
    expect($ability->name)->toEqual('edit');
    expect($ability->subject_type)->toEqual(Account::class);
    expect($ability->subject_id)->toEqual($account->id);
})->group('happy-path');
test('builds unpersisted ability for model class', function (): void {
    // Arrange
    $className = Account::class;

    // Act
    $ability = Ability::makeForModel($className, 'create');

    // Assert
    expect($ability->exists)->toBeFalse();
    expect($ability->name)->toEqual('create');
    expect($ability->subject_type)->toEqual(Account::class);
    expect($ability->subject_id)->toBeNull();
})->group('happy-path');
test('builds unpersisted global wildcard ability', function (): void {
    // Arrange
    $model = '*';

    // Act
    $ability = Ability::makeForModel($model, 'admin');

    // Assert
    expect($ability->exists)->toBeFalse();
    expect($ability->name)->toEqual('admin');
    expect($ability->subject_type)->toEqual('*');
})->group('happy-path');
test('converts string attributes to name array', function (): void {
    // Arrange
    $model = Account::class;
    $nameString = 'view';

    // Act
    $ability = Ability::makeForModel($model, $nameString);

    // Assert
    expect($ability->name)->toEqual('view');
    expect($ability->subject_type)->toEqual(Account::class);
})->group('happy-path');
test('returns false when no constraints defined', function (): void {
    // Arrange
    $ability = Ability::makeForModel(Account::class, 'edit');

    // Act
    $hasConstraints = $ability->hasConstraints();

    // Assert
    expect($hasConstraints)->toBeFalse();
})->group('happy-path');
test('returns true when constraints are defined', function (): void {
    // Arrange
    $ability = Ability::makeForModel(Account::class, 'edit');
    $ability->setConstraints(ConstraintGroup::withAnd()->add(Constraint::where('active', true)));

    // Act
    $hasConstraints = $ability->hasConstraints();

    // Assert
    expect($hasConstraints)->toBeTrue();
})->group('happy-path');
test('returns empty group when no constraints defined', function (): void {
    // Arrange
    $ability = Ability::makeForModel(Account::class, 'edit');

    // Act
    $constraints = $ability->getConstraints();

    // Assert
    expect($constraints)->toBeInstanceOf(ConstraintGroup::class);
})->group('happy-path');
test('returns configured constrainer with proper data', function (): void {
    // Arrange
    $ability = Ability::makeForModel(Account::class, 'edit');
    $group = ConstraintGroup::withAnd()->add(Constraint::where('active', true));
    $ability->setConstraints($group);
    $ability->save();

    // Act
    $ability->refresh();

    $constraints = $ability->getConstraints();

    // Assert
    expect($constraints)->toBeInstanceOf(ConstraintGroup::class);
    expect($constraints->check(
        new Account(['active' => true]),
        new User(),
    ))->toBeTrue();
    expect($constraints->check(
        new Account(['active' => false]),
        new User(),
    ))->toBeFalse();
})->group('happy-path');
test('stores constrainer in options json column', function (): void {
    // Arrange
    $ability = Ability::makeForModel(Account::class, 'edit');
    $group = ConstraintGroup::withAnd()->add(Constraint::where('active', true));

    // Act
    $result = $ability->setConstraints($group);

    // Assert
    expect($result)->toBe($ability);
    expect($ability->hasConstraints())->toBeTrue();
    expect($ability->options)->toHaveKey('constraints');
})->group('happy-path');
test('merges constraints with existing options', function (): void {
    // Arrange
    $ability = Ability::makeForModel(Account::class, 'edit');
    $ability->options = ['custom_key' => 'custom_value'];

    $group = ConstraintGroup::withAnd()->add(Constraint::where('active', true));

    // Act
    $ability->setConstraints($group);

    // Assert
    expect($ability->options)->toHaveKey('constraints');
    expect($ability->options)->toHaveKey('custom_key');
    expect($ability->options['custom_key'])->toEqual('custom_value');
})->group('happy-path');
test('returns polymorphic many to many roles relationship', function (): void {
    // Arrange
    $ability = Ability::createForModel(Account::class, 'edit');

    // Act
    $relation = $ability->roles();

    // Assert
    expect($relation)->toBeInstanceOf(MorphToMany::class);
    expect(Models::classname(Role::class))->toBe($relation->getRelated()::class);
})->group('happy-path');
test('roles relationship includes forbidden and scope pivot columns', function (): void {
    // Arrange
    $ability = Ability::createForModel(Account::class, 'edit');
    $relation = $ability->roles();

    // Act
    $pivotColumns = $relation->getPivotColumns();

    // Assert
    expect($pivotColumns)->toContain('forbidden');
    expect($pivotColumns)->toContain('scope');
    expect($pivotColumns)->toContain('context_id');
    expect($pivotColumns)->toContain('context_type');
})->group('happy-path');
test('returns polymorphic many to many users relationship', function (): void {
    // Arrange
    config(['warden.user_model' => User::class]);
    $ability = Ability::createForModel(Account::class, 'edit');

    // Act
    $relation = $ability->users();

    // Assert
    expect($relation)->toBeInstanceOf(MorphToMany::class);
    expect(Models::classname(User::class))->toBe($relation->getRelated()::class);
})->group('happy-path');
test('users relationship includes forbidden and scope pivot columns', function (): void {
    // Arrange
    config(['warden.user_model' => User::class]);
    $ability = Ability::createForModel(Account::class, 'edit');
    $relation = $ability->users();

    // Act
    $pivotColumns = $relation->getPivotColumns();

    // Assert
    expect($pivotColumns)->toContain('forbidden');
    expect($pivotColumns)->toContain('scope');
    expect($pivotColumns)->toContain('context_id');
    expect($pivotColumns)->toContain('context_type');
})->group('happy-path');
test('returns polymorphic subject relationship', function (): void {
    // Arrange
    $account = Account::query()->create(['name' => 'Test Account']);
    $ability = Ability::createForModel($account, 'edit');

    // Act
    $relation = $ability->subject();
    $subject = $ability->subject;

    // Assert
    expect($relation)->toBeInstanceOf(MorphTo::class);
    expect($subject)->toBeInstanceOf(Account::class);
    expect($subject->id)->toEqual($account->id);
})->group('happy-path');
test('returns polymorphic context relationship', function (): void {
    // Arrange
    $account = Account::query()->create(['name' => 'Context Account']);
    $ability = new Ability([
        'name' => 'edit',
    ]);
    $ability->context_type = Account::class;
    $ability->context_id = $account->id;
    $ability->save();

    // Act
    $ability->refresh();

    $relation = $ability->context();
    $context = $ability->context;

    // Assert
    expect($relation)->toBeInstanceOf(MorphTo::class);
    expect($context)->toBeInstanceOf(Account::class);
    expect($context->id)->toEqual($account->id);
})->group('happy-path');
test('decodes json options to array', function (): void {
    // Arrange
    $ability = Ability::makeForModel(Account::class, 'edit');
    $ability->options = ['key' => 'value', 'nested' => ['data' => 123]];
    $ability->save();

    // Act
    $ability->refresh();

    $options = $ability->options;

    // Assert
    expect($options)->toBeArray();
    expect($options)->toHaveKey('key');
    expect($options['key'])->toEqual('value');
    expect($options['nested'])->toBeArray();
    expect($options['nested']['data'])->toEqual(123);
})->group('happy-path');
test('returns empty array when options is null', function (): void {
    // Arrange
    $ability = Ability::makeForModel(Account::class, 'edit');

    // Act
    $options = $ability->options;

    // Assert
    expect($options)->toBeArray();
    expect($options)->toBeEmpty();
})->group('happy-path');
test('generates identifier with name type id and owned flag', function (): void {
    // Arrange
    $account = Account::query()->create(['name' => 'Test Account']);
    $ability = Ability::makeForModel($account, [
        'name' => 'edit',
        'only_owned' => true,
    ]);
    $ability->save();

    // Act
    $identifier = $ability->identifier;

    // Assert
    expect($identifier)->toBeString();
    $this->assertStringContainsString('edit', $identifier);
    $this->assertStringContainsString(mb_strtolower(Account::class), $identifier);
    $this->assertStringContainsString((string) $account->id, $identifier);
    $this->assertStringContainsString('owned', $identifier);
})->group('happy-path');
test('generates identifier with name only', function (): void {
    // Arrange
    $ability = new Ability(['name' => 'simple-ability']);
    $ability->subject_type = null;
    $ability->subject_id = null;
    $ability->save();

    // Act
    $ability->refresh();

    $identifier = $ability->identifier;

    // Assert
    expect($identifier)->toEqual('simple-ability');
})->group('happy-path');
test('generates identifier with name and type', function (): void {
    // Arrange
    $ability = Ability::createForModel(Account::class, 'create');

    // Act
    $identifier = $ability->identifier;

    // Assert
    expect($identifier)->toStartWith('create-');
    $this->assertStringContainsString(mb_strtolower(Account::class), (string) $identifier);
    $this->assertStringNotContainsString('owned', (string) $identifier);
})->group('happy-path');
test('slug attribute returns same as identifier', function (): void {
    // Arrange
    $ability = Ability::createForModel(Account::class, 'view');

    // Act
    $slug = $ability->slug;
    $identifier = $ability->identifier;

    // Assert
    expect($slug)->toEqual($identifier);
})->group('happy-path');
test('filters abilities by single name', function (): void {
    // Arrange
    Ability::query()->create(['name' => 'edit']);
    Ability::query()->create(['name' => 'view']);
    Ability::query()->create(['name' => 'delete']);

    // Act
    $results = Ability::query()->byName('edit')->get();

    // Assert
    expect($results->count())->toBeGreaterThanOrEqual(1);
    expect($results->contains('name', 'edit'))->toBeTrue();
})->group('happy-path');
test('filters abilities by array of names', function (): void {
    // Arrange
    Ability::query()->create(['name' => 'edit']);
    Ability::query()->create(['name' => 'view']);
    Ability::query()->create(['name' => 'delete']);

    // Act
    $results = Ability::query()->byName(['edit', 'view'])->get();

    // Assert
    expect($results->count())->toBeGreaterThanOrEqual(2);
    expect($results->contains('name', 'edit'))->toBeTrue();
    expect($results->contains('name', 'view'))->toBeTrue();
})->group('happy-path');
test('includes wildcard abilities in non strict mode', function (): void {
    // Arrange
    Ability::query()->create(['name' => 'edit']);
    Ability::query()->create(['name' => '*']);

    // Act
    $results = Ability::query()->byName('edit', false)->get();

    // Assert
    expect($results)->toHaveCount(2);
    expect($results->contains('name', 'edit'))->toBeTrue();
    expect($results->contains('name', '*'))->toBeTrue();
})->group('happy-path');
test('excludes wildcard abilities in strict mode', function (): void {
    // Arrange
    Ability::query()->create(['name' => 'edit']);
    Ability::query()->create(['name' => '*']);

    // Act
    $results = Ability::query()->byName('edit', true)->get();

    // Assert
    expect($results)->toHaveCount(1);
    expect($results->first()->name)->toEqual('edit');
})->group('happy-path');
test('filters abilities with null subject type', function (): void {
    // Arrange
    Ability::query()->create(['name' => 'simple']);
    Ability::createForModel(Account::class, 'scoped');

    // Act
    $results = Ability::query()->simpleAbility()->get();

    // Assert
    expect($results)->toHaveCount(1);
    expect($results->first()->name)->toEqual('simple');
    expect($results->first()->subject_type)->toBeNull();
})->group('happy-path');
test('filters abilities for specific model instance', function (): void {
    // Arrange
    $account = Account::query()->create(['name' => 'Test Account']);
    Ability::createForModel($account, 'edit');
    Ability::createForModel(Account::class, 'create');

    // Act
    $results = Ability::query()->forModel($account)->get();

    // Assert
    expect($results->count())->toBeGreaterThanOrEqual(1);
})->group('happy-path');
test('filters abilities for model class', function (): void {
    // Arrange
    Ability::createForModel(Account::class, 'create');
    Ability::createForModel(User::class, 'view');

    // Act
    $results = Ability::query()->forModel(Account::class)->get();

    // Assert
    expect($results->count())->toBeGreaterThanOrEqual(1);
    expect($results->contains(fn ($ability): bool => $ability->subject_type === Account::class))->toBeTrue();
})->group('happy-path');
test('creates ability with null subject id for non existent model', function (): void {
    // Arrange
    $account = new Account(['name' => 'Not Saved']);

    // Act
    $ability = Ability::makeForModel($account, 'edit');

    // Assert
    expect($ability->subject_type)->toEqual(Account::class);
    expect($ability->subject_id)->toBeNull();
})->group('sad-path');
test('returns empty group when constraints data is invalid', function (): void {
    // Arrange
    $ability = Ability::makeForModel(Account::class, 'edit');
    $ability->options = ['constraints' => null];

    // Act
    $constraints = $ability->getConstraints();

    // Assert
    expect($constraints)->toBeInstanceOf(ConstraintGroup::class);
})->group('sad-path');
test('generates identifier without type when subject type is null', function (): void {
    // Arrange
    $ability = Ability::query()->create(['name' => 'test']);
    $ability->subject_type = null;
    $ability->save();

    // Act
    $ability->refresh();

    $identifier = $ability->identifier;

    // Assert
    expect($identifier)->toEqual('test');
})->group('edge-case');
test('generates identifier without id when subject id is null', function (): void {
    // Arrange
    $ability = Ability::createForModel(Account::class, 'create');

    // Act
    $identifier = $ability->identifier;

    // Assert
    expect($identifier)->toStartWith('create-');
    $this->assertStringContainsString(mb_strtolower(Account::class), (string) $identifier);

    // Identifier should only have create-classname, not create-classname-number
    $parts = explode('-', (string) $identifier);
    expect(count($parts))->toBeLessThanOrEqual(2);
    // create and classname parts only
})->group('edge-case');
test('generates identifier without owned flag when only owned is false', function (): void {
    // Arrange
    $ability = Ability::createForModel(Account::class, [
        'name' => 'edit',
        'only_owned' => false,
    ]);

    // Act
    $identifier = $ability->identifier;

    // Assert
    $this->assertStringNotContainsString('owned', (string) $identifier);
})->group('edge-case');
test('generates identifier without owned flag when only owned is null', function (): void {
    // Arrange
    $ability = Ability::createForModel(Account::class, 'edit');
    $ability->only_owned = false;
    $ability->save();

    // Act
    $ability->refresh();

    $identifier = $ability->identifier;

    // Assert
    $this->assertStringNotContainsString('owned', (string) $identifier);
})->group('edge-case');
test('generates identifier in lowercase format', function (): void {
    // Arrange
    $ability = new Ability(['name' => 'UPPERCASE-NAME']);
    $ability->subject_type = null;
    $ability->subject_id = null;
    $ability->save();

    // Act
    $ability->refresh();

    $identifier = $ability->identifier;

    // Assert
    expect($identifier)->toEqual(mb_strtolower($identifier));
})->group('edge-case');
test('does not add duplicate wildcard when name is already wildcard', function (): void {
    // Arrange
    Ability::query()->create(['name' => '*']);

    // Act
    $results = Ability::query()->byName('*', false)->get();

    // Assert
    expect($results)->toHaveCount(1);
    expect($results->first()->name)->toEqual('*');
})->group('edge-case');
test('subject relationship returns null when subject id is null', function (): void {
    // Arrange
    $ability = Ability::createForModel(Account::class, 'create');

    // Act
    $subject = $ability->subject;

    // Assert
    expect($subject)->toBeNull();
})->group('edge-case');
test('context relationship returns null when context id is null', function (): void {
    // Arrange
    $ability = Ability::query()->create(['name' => 'test']);

    // Act
    $context = $ability->context;

    // Assert
    expect($context)->toBeNull();
})->group('edge-case');
test('returns empty array when options is empty string', function (): void {
    // Arrange
    $ability = Ability::query()->create(['name' => 'test']);

    // Directly set empty options in database
    $ability->saveQuietly(['options' => '']);

    // Act
    $ability->refresh();

    $options = $ability->options;

    // Assert
    expect($options)->toBeArray();
    expect($options)->toBeEmpty();
})->group('edge-case');
test('sets constraints when options is initially null', function (): void {
    // Arrange
    $ability = Ability::makeForModel(Account::class, 'edit');

    // Options accessor returns array, so it will never be null after access
    $group = ConstraintGroup::withAnd()->add(Constraint::where('active', true));

    // Act
    $ability->setConstraints($group);

    // Assert
    expect($ability->options)->toBeArray();
    expect($ability->options)->toHaveKey('constraints');
})->group('edge-case');
test('excludes wildcard abilities in strict mode for model', function (): void {
    // Arrange
    Ability::createForModel('*', 'admin');
    Ability::createForModel(Account::class, 'edit');

    // Act
    $results = Ability::query()->forModel(Account::class, true)->get();

    // Assert
    expect($results->count())->toBeGreaterThanOrEqual(1);
    expect($results->contains('subject_type', '*'))->toBeFalse();
})->group('edge-case');
test('handles transition from instance to class correctly', function (): void {
    // Arrange
    $account = Account::query()->create(['name' => 'Test Account']);

    // Act
    $instanceAbility = Ability::createForModel($account, 'instance-ability');
    $classAbility = Ability::createForModel(Account::class, 'class-ability');

    // Assert
    expect($instanceAbility->subject_id)->not->toBeNull();
    expect($classAbility->subject_id)->toBeNull();
    expect($instanceAbility->subject_id)->toEqual($account->id);
    expect($classAbility->subject_type)->toEqual(Account::class);
})->group('edge-case');
test('generates correct identifier for various attribute combinations', function (array $data): void {
    // Arrange
    $ability = new Ability();

    foreach ($data['attributes'] as $key => $value) {
        $ability->setAttribute($key, $value);
    }

    // Act
    $identifier = $ability->identifier;

    // Assert
    if (array_key_exists('expected', $data)) {
        expect($identifier)->toEqual($data['expected']);
    }

    if (array_key_exists('contains', $data)) {
        foreach ($data['contains'] as $substring) {
            $this->assertStringContainsString($substring, (string) $identifier);
        }
    }

    if (array_key_exists('not_contains', $data)) {
        foreach ($data['not_contains'] as $substring) {
            $this->assertStringNotContainsString($substring, (string) $identifier);
        }
    }
})->with('provideGenerates_correct_identifier_for_various_attribute_combinationsCases')->group('edge-case');

/**
 * Data provider for identifier attribute combinations.
 */
dataset('provideGenerates_correct_identifier_for_various_attribute_combinationsCases', function () {
    yield 'name only' => [[
        'attributes' => ['name' => 'test', 'subject_type' => null, 'subject_id' => null, 'only_owned' => false],
        'expected' => 'test',
    ]];

    yield 'name and type' => [[
        'attributes' => ['name' => 'edit', 'subject_type' => Account::class, 'subject_id' => null, 'only_owned' => false],
        'contains' => ['edit', mb_strtolower(Account::class)],
        'not_contains' => ['owned'],
    ]];

    yield 'name, type, and id' => [[
        'attributes' => ['name' => 'view', 'subject_type' => Account::class, 'subject_id' => 123, 'only_owned' => false],
        'contains' => ['view', mb_strtolower(Account::class), '123'],
        'not_contains' => ['owned'],
    ]];

    yield 'name, type, id, and owned' => [[
        'attributes' => ['name' => 'delete', 'subject_type' => Account::class, 'subject_id' => 456, 'only_owned' => true],
        'contains' => ['delete', mb_strtolower(Account::class), '456', 'owned'],
    ]];
});
