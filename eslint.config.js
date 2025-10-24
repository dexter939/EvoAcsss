import js from '@eslint/js';

export default [
  js.configs.recommended,
  {
    files: ['**/*.js', '**/*.blade.php'],
    languageOptions: {
      ecmaVersion: 2022,
      sourceType: 'script',
      globals: {
        window: 'readonly',
        document: 'readonly',
        console: 'readonly',
        alert: 'readonly',
        $: 'readonly',
        jQuery: 'readonly',
        bootstrap: 'readonly',
        Chart: 'readonly',
        axios: 'readonly',
        Swal: 'readonly'
      }
    },
    rules: {
      'no-redeclare': 'error',
      'no-duplicate-imports': 'error',
      'no-undef': 'warn',
      'no-unused-vars': 'warn'
    }
  }
];
