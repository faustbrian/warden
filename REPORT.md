# Code Quality Review Summary

## **Strengths**
- Excellent PHPDoc coverage
- Strong type safety with PHPStan annotations
- Good separation of concerns
- Well-organized trait system in Conductors

---

## **Critical Issues in src/**

### **1. Excessive PHPStan Suppressions (30+ occurrences)**
**Location**: Throughout codebase, especially Migrators
**Issue**: Heavy reliance on `@phpstan-ignore-next-line` masks real type safety issues

```php
// src/Migrators/BouncerMigrator.php - Lines 102, 105, 108, 113, etc.
/** @phpstan-ignore-next-line property.nonObject (stdClass from DB query) */
```

**Fix**: Use proper typing with DTOs or typed stdClass alternatives:
```php
// Create DTO for migration data
final class BouncerPermissionData {
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $entity_type,
        // ...
    ) {}
}
```

---

### **2. Factory.php: Inconsistent Variable Naming**
**Location**: `src/Factory.php:83`

```php
$bouncer = new Warden($guard)->setGate($gate); // Wrong variable name
```

**Fix**: Rename to match actual class:
```php
$warden = new Warden($guard)->setGate($gate);
```

---

### **3. Guard.php: Confusing Slot Getter/Setter Overload**
**Location**: `src/Guard.php:110-121`

```php
public function slot(?string $slot = null): string|self
{
    if (null === $slot) {
        return $this->slot;
    }
    // ...
    return $this;
}
```

**Problem**: Single method does two different things with different return types

**Fix**: Split into separate methods:
```php
public function getSlot(): string
{
    return $this->slot;
}

public function setSlot(string $slot): self
{
    throw_unless(in_array($slot, ['before', 'after'], true), InvalidArgumentException::class, $slot.' is an invalid gate slot');
    $this->slot = $slot;
    return $this;
}
```

---

### **4. FindsAndCreatesAbilities Trait: Complex Nested Logic**
**Location**: `src/Conductors/Concerns/FindsAndCreatesAbilities.php:97`

```php
return $map->map(fn ($entity, $ability) => $this->getAbilityIds($ability, $entity, $attributes))
    ->collapse()
    ->merge($this->getAbilityIdsFromArray($list, $attributes))
    ->all();
```

**Issue**: 309-line trait with deeply nested logic, hard to test/maintain

**Fix**: Extract to dedicated service class:
```php
final class AbilityResolver
{
    public function resolveIds(mixed $abilities, mixed $model = null): array
    public function resolveFromMap(array $map): array
    public function resolveFromArray(array $abilities): array
    // ...
}
```

---

### **5. Conductors: Duplication Across 8 Files**
**Pattern**: Every conductor repeats `guardName` initialization:

```php
public function __construct(
    protected readonly Model|string|null $authority = null,
    ?string $guardName = null,
) {
    $guard = $guardName ?? config('warden.guard', 'web');
    assert(is_string($guard));
    $this->guardName = $guard;
}
```

**Fix**: Extract to abstract base class:
```php
abstract class AbstractConductor
{
    protected readonly string $guardName;

    public function __construct(
        protected readonly Model|string|null $authority = null,
        ?string $guardName = null,
    ) {
        $guard = $guardName ?? config('warden.guard', 'web');
        assert(is_string($guard));
        $this->guardName = $guard;
    }
}
```

---

### **6. ModelRegistry: God Object Anti-Pattern**
**Location**: `src/Database/ModelRegistry.php` (568 lines)

**Issues**:
- Manages 4 different registries (models, tables, ownership, keyMap)
- 20+ public methods
- Multiple responsibilities (model factory, table mapping, ownership, morph keys)

**Fix**: Split into focused services:
```php
final class ModelFactory { /* ability(), role(), user() */ }
final class TableRegistry { /* table(), setTables() */ }
final class OwnershipResolver { /* isOwnedBy(), ownedVia() */ }
final class MorphKeyMapper { /* getModelKey(), morphKeyMap() */ }
```

---

### **7. Warden.php: Deprecated Methods Bloat**
**Location**: `src/Warden.php:515-532`

```php
#[Deprecated('Use can() instead')]
public function allows(string $ability, mixed $arguments = []): bool
{
    return $this->can($ability, $arguments);
}

#[Deprecated('Use cannot() instead')]
public function denies(string $ability, mixed $arguments = []): bool
{
    return $this->cannot($ability, $arguments);
}
```

**Fix**: Remove deprecated methods (breaking change, document in upgrade guide)

---

### **8. Clipboard: Missing Interface Documentation**
**Location**: `src/Clipboard/AbstractClipboard.php`

```php
abstract class AbstractClipboard implements ClipboardInterface
{
    abstract public function checkGetId(Model $authority, string $ability, Model|string|null $model = null): bool|int|string|null;

    // Missing: isOwnedBy() - implemented but not in interface contract
    protected function isOwnedBy(Model $authority, Model|string|null $model): bool
}
```

**Fix**: Move `isOwnedBy` to interface or document as internal helper

---

## **Test Organization Issues**

### **1. Inconsistent Test Structure**
- **Unit tests**: Properly organized under `tests/Unit/`
- **Integration tests**: Scattered in root `tests/` directory
- **No clear separation** between unit/integration/feature tests

**Fix**: Reorganize:
```
tests/
├── Unit/          (existing - good)
├── Integration/   (move: MultiTenancyTest, MultipleAbilitiesTest, etc.)
├── Feature/       (move: AuthorizableTest, OwnershipTest, etc.)
└── Fixtures/      (existing - good)
```

---

### **2. Massive Test Files (750+ lines)**
- `IsAbilityTest.php`: 750 lines
- `IsRoleTest.php`: 721 lines
- `BouncerMigratorTest.php`: 824 lines

**Fix**: Split by concern using Pest describe blocks or separate files

---

### **3. TestCase Helper Method Inconsistency**

```php
// Static methods
public static function bouncer(?Model $authority = null): WardenClass
public static function gate(Model $authority): Gate

// Instance methods
public function clipboard(): ClipboardInterface
protected static function registerClipboard(): void
```

**Fix**: Make all helpers static for consistency

---

## **Quick Wins (Low Effort, High Impact)**

1. **Remove 30+ PHPStan suppressions** → Create DTOs for migration data
2. **Rename `$bouncer` → `$warden`** in Factory.php
3. **Split `slot()` method** into `getSlot()`/`setSlot()`
4. **Extract guardName initialization** to base conductor class
5. **Remove deprecated methods** (`allows()`, `denies()`)
6. **Reorganize test directory** structure

---

## **Medium Effort Improvements**

1. **Extract AbilityResolver service** from FindsAndCreatesAbilities trait
2. **Split ModelRegistry** into 4 focused classes
3. **Split large test files** using describe blocks
4. **Standardize test helpers** (all static or all instance)

---

## **Long-Term Refactoring**

1. **Introduce strict typing** - remove all PHPStan suppressions
2. **Implement DTOs** for complex data structures (migration data, constraint data)
3. **Consider CQRS pattern** for command (assign/forbid) vs query (check/is) operations
4. **Add architecture tests** to prevent regression (Pest Arch plugin)

---

## **Specific Files Needing Attention**

| File | Lines | Issues | Priority |
|------|-------|--------|----------|
| `ModelRegistry.php` | 568 | God object, 4 responsibilities | HIGH |
| `FindsAndCreatesAbilities.php` | 309 | Complex logic, hard to test | HIGH |
| `BouncerMigrator.php` | 312 | 20+ PHPStan suppressions | MED |
| `Warden.php` | 673 | Deprecated methods, large API | MED |
| `IsAbilityTest.php` | 750 | Needs splitting | LOW |
| `IsRoleTest.php` | 721 | Needs splitting | LOW |
