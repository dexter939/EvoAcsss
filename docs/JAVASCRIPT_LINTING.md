# JavaScript Linting for Blade Templates

## Overview

This project uses a custom linting workflow to check JavaScript code embedded in Laravel Blade templates. Since ESLint cannot directly parse Blade/PHP syntax, we extract the JavaScript portions and lint them separately.

## Quick Start

```bash
# Run JavaScript lint on all Blade templates
npm run lint
```

## How It Works

The linting workflow consists of three components:

### 1. ESLint Configuration (`eslint.config.js`)

Standard ESLint v9+ flat config with:
- `no-redeclare` rule set to **error** - prevents duplicate variable declarations (const, let, var)
- `no-duplicate-imports` rule set to **error**
- Common browser globals defined ($, jQuery, bootstrap, Chart, axios, etc.)

### 2. Extraction Script (`scripts/lint-blade-js.sh`)

Bash script that:
1. Finds all Blade templates in `resources/views/acs/`
2. Extracts JavaScript from `@push('scripts')` sections
3. Creates temporary `.js` files for each Blade template
4. Runs ESLint on extracted JavaScript
5. Reports errors with reference to original Blade file
6. Cleans up temporary files

### 3. NPM Script (`package.json`)

```json
{
  "scripts": {
    "lint": "bash scripts/lint-blade-js.sh"
  }
}
```

## Common Errors Detected

### Duplicate Variable Declarations

**❌ BAD:**
```javascript
const deviceId = 123;
// ... many lines later ...
const deviceId = document.getElementById('device-id').value; // ERROR!
```

**✅ GOOD:**
```javascript
const deviceId = 123;
// ... many lines later ...
deviceId = document.getElementById('device-id').value; // Reassignment
```

### Undefined Variables

The linter will warn about undefined variables, helping catch typos in function names:

**❌ BAD:**
```html
<button onclick="openSetParameterModal()">...</button> <!-- Typo! -->
```

**✅ GOOD:**
```html
<button onclick="openSetParametersModal()">...</button>
```

## Regression Test

The project includes automated regression tests in `tests/Feature/DeviceDetailPageTest.php` that verify:

1. **No duplicate declarations** - Ensures exactly 1 declaration of critical variables
2. **All modal functions defined** - Verifies presence of required JavaScript functions
3. **Libraries loaded** - Confirms jQuery and Bootstrap are included

Run regression tests:

```bash
php artisan test --filter=DeviceDetailPageTest
```

## Workflow Integration

### During Development

Before committing changes to Blade templates with embedded JavaScript:

```bash
# Check for JavaScript errors
npm run lint

# Run regression tests
php artisan test --filter=DeviceDetailPageTest
```

### CI/CD Integration

Add to your CI pipeline:

```yaml
- name: Lint Blade JavaScript
  run: npm run lint

- name: Run JavaScript Regression Tests
  run: php artisan test --filter=DeviceDetailPageTest
```

## Limitations

- Only lints JavaScript within `@push('scripts')` sections
- Cannot auto-fix errors (manual correction required)
- Warnings about "file ignored" from /tmp are expected and can be ignored
- Does not lint inline `onclick` attributes (only `@push` sections)

## Troubleshooting

### "File ignored because outside of base path"

This warning is expected and can be ignored. It appears because ESLint is checking temporary files in `/tmp/`.

### Script returns exit code 1

The script found JavaScript errors. Check the output for specific file names and error descriptions.

### No JavaScript extracted from a Blade file

Verify that your JavaScript is wrapped in a `@push('scripts')` section:

```blade
@push('scripts')
<script>
    // Your JavaScript here
</script>
@endpush
```

## Historical Context

This linting system was created after a critical bug where duplicate `const deviceId` declarations in `device-detail.blade.php` blocked all JavaScript execution. The regression test ensures this specific error never recurs.

## References

- ESLint v9 Configuration: https://eslint.org/docs/latest/use/configure/
- Laravel Blade Templates: https://laravel.com/docs/blade
- Regression Test: `tests/Feature/DeviceDetailPageTest.php`
