# TODO: Critical Bug Review

## Keymap Lookup Bug Pattern

**Date**: 2025-11-15
**Priority**: HIGH
**Status**: OPEN

### Issue Description
Discovered a critical bug in `AssignsRoles` where code was using `find($id)` to lookup models by their keymap value (e.g., ULID) instead of their primary key. This caused all role assignments to incorrectly target user ID 1 because `find()` searches by the primary key column (`id`), not the configured keymap column (`ulid`).

### Root Cause
When `enforceMorphKeyMap` is configured to use alternative keys (like `ulid`), the `mapAuthorityByClass()` helper extracts the keymap value. However, subsequent code was using `Model::find($value)` which searches by the model's primary key, not the keymap column.

**Before (broken)**:
```php
$authority = $query->find($authorityId); // Searches by 'id' column
```

**After (fixed)**:
```php
$authority = $query->where(Models::getModelKeyFromClass($authorityClass), $authorityId)->first();
```

### Action Items

1. **Codebase-wide Audit**
   - [x] Search for all `find()` calls that receive values from keymap lookups - COMPLETED
   - [x] Search for `findOrFail()`, `findMany()`, `findOr()` with keymap values - None found
   - [x] Review all code paths where `Models::getModelKey()` or `mapAuthorityByClass()` is used - COMPLETED
   - [x] Check if values are being used correctly with appropriate lookup methods - COMPLETED

2. **Specific Files to Review**
   - [x] `src/Conductors/GivesAbilities.php` - ✅ SAFE: Works directly with authority models, no find() calls
   - [x] `src/Conductors/RemovesAbilities.php` - ✅ SAFE: Uses trait, no find() calls
   - [x] `src/Conductors/RemovesRoles.php` - ✅ SAFE: Uses keymap IDs directly in WHERE clause, no find()
   - [x] `src/Conductors/ChecksRoles.php` - ✅ SAFE: No find() calls
   - [x] `src/Conductors/SyncsRolesAndAbilities.php` - ✅ SAFE: No find() calls
   - [x] `src/Conductors/AssignsRoles.php` - ✅ FIXED: Now uses where() with getModelKeyFromClass()
   - [x] `src/Conductors/AssignsRoles.php` (findOrCreateRoles method) - ✅ SAFE: Only uses find() when is_int() check passes
   - [x] `src/Migrators/SpatieMigrator.php` - ✅ FIXED: findUser() now uses where($keyColumn, $id)->first()
   - [x] `src/Migrators/BouncerMigrator.php` - ✅ FIXED: findUser() now uses where($keyColumn, $id)->first()
   - [x] `src/Database/Concerns/IsRole.php` - ✅ SAFE: Uses find() on integers after explicit grouping

3. **Search Patterns**
   ```bash
   # Find potential problematic patterns
   rg "::find\(" src/
   rg "->find\(" src/
   rg "findOrFail\(" src/
   rg "mapAuthorityByClass" src/
   rg "getModelKey" src/
   ```

4. **Regression Test**
   - [x] Create test that configures User model with `ulid` keymap
   - [x] Create multiple users with different ULIDs
   - [x] Assign roles to each user via `Warden::assign()->to($user)`
   - [x] Assert each user received their assigned role (not just user 1)
   - [x] Test should fail on old code, pass on fixed code
   - [ ] TODO: Add similar tests for migrators (SpatieMigrator, BouncerMigrator)
   - [ ] TODO: Add tests for edge cases (abilities, removes, checks, syncs)

5. **Documentation**
   - [ ] Document the correct pattern for model lookups with keymaps
   - [ ] Add warning in contributor docs about using `find()` with keymap values
   - [ ] Consider adding PHPStan rule to detect this pattern

### Impact
**Critical** - This bug caused data corruption where all role assignments were incorrectly assigned to a single user instead of the intended users. Any production system using keymap configuration with non-default keys is affected.

### Fixed Files
1. **src/Conductors/AssignsRoles.php:143** - Authority lookup in assignRoles()
2. **src/Migrators/SpatieMigrator.php:175-187** - findUser() method
3. **src/Migrators/BouncerMigrator.php:285-304** - findUser() method
4. **src/Database/ModelRegistry.php:497-506** - New getModelKeyFromClass() helper
5. **src/Database/ModelRegistry.php:317** - Ownership check now uses keymap value instead of getKey()
6. **src/Support/Helpers.php:85,92** - extractModelAndKeys() now uses keymap values instead of getKey()
   - Affects: Role::assignTo(), Role::retractFrom()
7. **src/Database/Queries/Abilities.php:120-126** - getAuthorityRoleConstraint() now uses keymap column/values
8. **src/Database/Queries/Abilities.php:150-157** - getAuthorityConstraint() now uses keymap column/values
9. **src/Conductors/Concerns/DisassociatesAbilities.php:113** - getAbilitiesPivotQuery() now uses keymap value
10. **src/Conductors/SyncsRolesAndAbilities.php:264** - newPivotQuery() now uses keymap value
11. **src/Database/Queries/Roles.php:112-113,117,120** - constrainWhereAssignedTo() now uses keymap column
12. **src/Clipboard/CachedClipboard.php:360** - compileModelAbilityIdentifiers() now uses keymap value for cache keys
13. **src/Clipboard/CachedClipboard.php:426** - getCacheKey() now uses keymap value for cache keys
14. **src/Console/CleanCommand.php:178** - scopeQueryToWhereModelIsMissing() now uses keymap column for subject_id comparison

### Pattern
**Wrong:**
```php
$authority = $query->find($authorityId); // Uses primary key, not keymap column
```

**Correct:**
```php
$keyColumn = Models::getModelKeyFromClass($authorityClass);
$authority = $query->where($keyColumn, $authorityId)->first();
```
