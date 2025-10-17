import js from '@eslint/js';
import globals from 'globals';
import pluginReact from 'eslint-plugin-react';
import pluginReactHooks from 'eslint-plugin-react-hooks';
import pluginTestingLibrary from 'eslint-plugin-testing-library';
import pluginJestDom from 'eslint-plugin-jest-dom';
import tseslint from 'typescript-eslint';

const IGNORE_PATTERNS = [
  '**/node_modules/**',
  '**/dist/**',
  '**/.next/**',
  '**/coverage/**',
  '**/public/sw*.js',
  '**/public/workbox-*.js'
];

export default tseslint.config(
  {
    ignores: IGNORE_PATTERNS
  },
  js.configs.recommended,
  ...tseslint.configs.recommended,
  {
    files: ['**/*.{ts,tsx}'],
    languageOptions: {
      parserOptions: {
        tsconfigRootDir: import.meta.dirname
      },
      globals: {
        ...globals.browser,
        ...globals.node
      }
    },
    plugins: {
      react: pluginReact,
      'react-hooks': pluginReactHooks,
      'testing-library': pluginTestingLibrary,
      'jest-dom': pluginJestDom
    },
    settings: {
      react: {
        version: '18.0'
      }
    },
    rules: {
      'react/react-in-jsx-scope': 'off',
      'react/prop-types': 'off',
      'react-hooks/rules-of-hooks': 'error',
      'react-hooks/exhaustive-deps': 'warn',
      'testing-library/no-node-access': 'warn',
      'testing-library/no-container': 'warn'
    }
  },
  {
    files: ['packages/ui/**/*.test.{ts,tsx}'],
    plugins: {
      'testing-library': pluginTestingLibrary,
      'jest-dom': pluginJestDom
    },
    rules: {
      'testing-library/consistent-data-testid': 'off',
      'jest-dom/prefer-enabled-disabled': 'error'
    }
  }
);
