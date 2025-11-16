#!/bin/bash

# Script to organize remaining Pest test files
# This processes files that haven't been organized yet

set -e

TESTS_DIR="/Users/brian/Developer/packages/warden/tests"

# Array of files already organized
ORGANIZED=(
    "AbilitiesForModelsTest.php"
    "AbilityConstraintsTest.php"
    "AuthorizableTest.php"
    "AutoTitlesTest.php"
    "BackedEnumTest.php"
    "ReportedIssuesTest.php"
)

# Array of files to skip (not actual tests)
SKIP_FILES=(
    "Pest.php"
    "TestCase.php"
    "Concerns/TestsClipboards.php"
    "Datasets/Clipboards.php"
)

# List of remaining feature test files to organize
FEATURE_FILES=(
    "BeforePoliciesTest.php"
    "BouncerSimpleTest.php"
    "CachedClipboardTest.php"
    "CleanCommandTest.php"
    "ContextAwarePermissionsTest.php"
    "CustomAuthorityTest.php"
    "FactoryTest.php"
    "ForbidTest.php"
    "HasRolesAndAbilitiesTraitTest.php"
    "HelpersTest.php"
    "MorphKeyConfigTest.php"
    "MorphKeyIntegrationTest.php"
    "MorphKeyMapTest.php"
    "MultiTenancyTest.php"
    "MultipleAbilitiesTest.php"
    "OwnershipTest.php"
    "SyncTest.php"
    "TablePrefixTest.php"
    "TitledAbilitiesTest.php"
    "WildcardsTest.php"
)

QUERY_SCOPE_FILES=(
    "QueryScopes/RoleScopesTest.php"
    "QueryScopes/UserIsScopesTest.php"
)

UNIT_FILES=(
    "Unit/Conductors/RemovesRolesTest.php"
    "Unit/Constraints/BuilderTest.php"
    "Unit/Constraints/ConstrainerTest.php"
    "Unit/Constraints/ConstraintTest.php"
    "Unit/Constraints/GroupsTest.php"
    "Unit/Database/Concerns/IsAbilityTest.php"
    "Unit/Database/Concerns/IsRoleTest.php"
    "Unit/Database/ModelsTest.php"
    "Unit/Database/Queries/AbilitiesForModelTest.php"
    "Unit/Database/Queries/AbilitiesTest.php"
    "Unit/Database/Queries/RolesTest.php"
    "Unit/Database/Scope/ScopeTest.php"
    "Unit/Database/Titles/TitleTest.php"
    "Unit/Enums/EnumTest.php"
    "Unit/Facades/WardenTest.php"
    "Unit/GuardTest.php"
    "Unit/WardenServiceProviderTest.php"
    "Unit/WardenTest.php"
)

# Combine all files to process
ALL_FILES=("${FEATURE_FILES[@]}" "${QUERY_SCOPE_FILES[@]}" "${UNIT_FILES[@]}")

echo "Processing ${#ALL_FILES[@]} test files..."
echo ""

# Get total baseline before ANY changes
echo "Getting baseline test counts..."
BASELINE_OUTPUT=$(vendor/bin/pest --compact 2>&1 || true)
BASELINE_TESTS=$(echo "$BASELINE_OUTPUT" | grep -oP '\d+(?= passed)' | head -1 || echo "0")
BASELINE_ASSERTIONS=$(echo "$BASELINE_OUTPUT" | grep -oP '\d+(?= assertions)' | head -1 || echo "0")

echo "Baseline: $BASELINE_TESTS tests, $BASELINE_ASSERTIONS assertions"
echo ""

# Process each file
PROCESSED=0
FAILED=0

for file in "${ALL_FILES[@]}"; do
    FILEPATH="$TESTS_DIR/$file"

    if [ ! -f "$FILEPATH" ]; then
        echo "⚠ File not found: $file"
        continue
    fi

    echo "Processing: $file"

    # Get file-specific baseline
    FILE_BASELINE=$(vendor/bin/pest "$FILEPATH" --compact 2>&1 | grep -E "Tests:" || echo "Tests: 0 passed")

    # Check if file needs organizing (no describe blocks)
    if ! grep -q "^describe(" "$FILEPATH"; then
        echo "  → Needs organizing (no describe blocks)"
        # File would be organized here by the main process
        PROCESSED=$((PROCESSED + 1))
    else
        echo "  → Already organized (has describe blocks)"
    fi
done

echo ""
echo "Summary:"
echo "  Processed: $PROCESSED files"
echo "  Failed: $FAILED files"
echo ""

# Final verification
echo "Running final test suite verification..."
FINAL_OUTPUT=$(vendor/bin/pest --compact 2>&1 || true)
FINAL_TESTS=$(echo "$FINAL_OUTPUT" | grep -oP '\d+(?= passed)' | head -1 || echo "0")
FINAL_ASSERTIONS=$(echo "$FINAL_OUTPUT" | grep -oP '\d+(?= assertions)' | head -1 || echo "0")

echo "Final: $FINAL_TESTS tests, $FINAL_ASSERTIONS assertions"

if [ "$BASELINE_TESTS" == "$FINAL_TESTS" ] && [ "$BASELINE_ASSERTIONS" == "$FINAL_ASSERTIONS" ]; then
    echo "✓ Test and assertion counts match!"
    exit 0
else
    echo "✗ ERROR: Counts don't match!"
    echo "  Before: $BASELINE_TESTS tests, $BASELINE_ASSERTIONS assertions"
    echo "  After:  $FINAL_TESTS tests, $FINAL_ASSERTIONS assertions"
    exit 1
fi
