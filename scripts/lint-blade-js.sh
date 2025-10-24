#!/bin/bash

# Script to extract and lint JavaScript from Blade templates
# Prevents duplicate variable declarations and common JS errors

set -e

TEMP_DIR=$(mktemp -d)
EXIT_CODE=0

echo "🔍 Extracting JavaScript from Blade templates..."

# Find all Blade files with script sections
BLADE_FILES=$(find resources/views/acs -name "*.blade.php" -type f)

for FILE in $BLADE_FILES; do
    # Extract JavaScript from @push('scripts') sections
    SCRIPT_CONTENT=$(sed -n '/@push.*scripts/,/@endpush/p' "$FILE" | \
                     sed '/@push/d; /@endpush/d; /<script>/d; /<\/script>/d')
    
    if [ -n "$SCRIPT_CONTENT" ]; then
        # Create temporary JS file
        BASENAME=$(basename "$FILE" .blade.php)
        TEMP_FILE="$TEMP_DIR/${BASENAME}.js"
        
        # Add common globals to avoid false positives
        echo "/* global $, jQuery, bootstrap, Chart, axios, Swal, deviceId */" > "$TEMP_FILE"
        echo "$SCRIPT_CONTENT" >> "$TEMP_FILE"
        
        # Run ESLint on extracted JavaScript
        echo "  Checking: $FILE"
        if ! npx eslint "$TEMP_FILE" --no-ignore 2>&1; then
            echo "  ❌ Errors found in $FILE"
            EXIT_CODE=1
        fi
    fi
done

# Cleanup
rm -rf "$TEMP_DIR"

if [ $EXIT_CODE -eq 0 ]; then
    echo "✅ All Blade JavaScript checks passed!"
else
    echo "❌ JavaScript errors found in Blade templates"
fi

exit $EXIT_CODE
