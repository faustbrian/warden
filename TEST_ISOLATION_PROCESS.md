# Test Isolation Process for BouncerMigratorTest

## Problem
`Tests\Unit\Migrators\BouncerMigratorTest` fails when run via `make test:docker:postgres:ulid` (full suite) but passes when run in isolation.

## Current State
- **phpunit.ulid.xml** has been modified to run ONLY BouncerMigratorTest
- This serves as the baseline to systematically identify which test is leaking state

## Process to Identify Culprit

### Step 1: Verify baseline (DONE)
Run `make test:docker:postgres:ulid` with only BouncerMigratorTest enabled. This should PASS.

### Step 2: Add tests incrementally
Gradually add test files/directories back to phpunit.ulid.xml and run the full suite each time.

**Strategy: Start with tests in the same directory first**

1. Add `./tests/Unit/Migrators/SpatieMigratorTest.php`
2. Add `./tests/Unit/Migrators/GuardMigrationTest.php`
3. Add entire `./tests/Unit/Migrators/` directory
4. Add `./tests/Unit/Console/` directory (migration commands)
5. Add other `./tests/Unit/` subdirectories one by one
6. Add root-level test files one by one

### Step 3: When failure occurs
When BouncerMigratorTest fails again, you've found the culprit(s). The last test file/directory added is causing state leakage.

### Step 4: Analyze the leaking test
Once identified, examine:
- Database state modifications (inserts, schema changes, config changes)
- Config modifications that aren't cleaned up
- Model boot state (BouncerMigratorTest already clears this in afterEach)
- Singleton/static state
- Cache state

## How to Re-enable Tests in phpunit.ulid.xml

Edit the `<testsuites>` section:

```xml
<testsuites>
    <testsuite name="default">
        <!-- Start with only BouncerMigratorTest -->
        <file>./tests/Unit/Migrators/BouncerMigratorTest.php</file>

        <!-- Add one at a time -->
        <!-- <file>./tests/Unit/Migrators/SpatieMigratorTest.php</file> -->
        <!-- <file>./tests/Unit/Migrators/GuardMigrationTest.php</file> -->

        <!-- Or add entire directory when confident -->
        <!-- <directory suffix=".php">./tests/Unit/Migrators</directory> -->
    </testsuite>
</testsuites>
```

## Commands

```bash
# Run only BouncerMigratorTest (baseline)
make test:docker:postgres:ulid

# After each modification to phpunit.ulid.xml, run again
make test:docker:postgres:ulid

# When you find the culprit, restore full suite and investigate
# (restore phpunit.ulid.xml to original state after investigation)
```

## Original phpunit.ulid.xml Configuration

```xml
<testsuites>
    <testsuite name="default">
        <directory suffix=".php">./tests</directory>
    </testsuite>
</testsuites>
```

Remember to restore this when done investigating!
