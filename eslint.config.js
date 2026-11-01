import js from '@eslint/js';
import { defineConfigWithVueTs, vueTsConfigs } from '@vue/eslint-config-typescript';
import prettier from 'eslint-config-prettier';
import vue from 'eslint-plugin-vue';

export default defineConfigWithVueTs(
    { ignores: ['vendor', 'public', 'storage', 'bootstrap/cache', 'node_modules', 'playwright-report', 'test-results', 'resources/js/components/ui/**'] },
    js.configs.recommended,
    vue.configs['flat/recommended'],
    vueTsConfigs.strictTypeChecked,
    prettier,
    {
        rules: {
            '@typescript-eslint/no-explicit-any': 'error',
            'vue/multi-word-component-names': 'off',
            'vue/block-lang': ['error', { script: { lang: 'ts' } }],
            'vue/no-v-html': 'error',
            '@typescript-eslint/no-confusing-void-expression': 'off',
            '@typescript-eslint/restrict-template-expressions': ['error', { allowNumber: true }],
            '@typescript-eslint/no-floating-promises': 'error',
            'no-restricted-syntax': ['error', { selector: "Literal[value=/^#[0-9a-fA-F]{3,8}$/]", message: 'Use a semantic design token, not a raw colour.' }],
        },
    },
    { files: ['**/*.config.ts', 'eslint.config.js', 'tests/**'], rules: { '@typescript-eslint/no-floating-promises': 'off' } },
);
