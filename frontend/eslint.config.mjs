import withNuxt from './.nuxt/eslint.config.mjs'

const controlStatements = ['if', 'return', 'for', 'while', 'do', 'switch', 'try', 'throw']

export default withNuxt(
  {
    rules: {
      'curly': ['error', 'all'],
      '@stylistic/brace-style': ['error', '1tbs', { allowSingleLine: false }],
      '@stylistic/padding-line-between-statements': [
        'error',
        ...controlStatements.flatMap(statement => [
          { blankLine: 'always', prev: '*', next: statement },
          { blankLine: 'always', prev: statement, next: '*' },
        ]),
      ],
    },
  },
  {
    files: ['app/pages/**/*.vue', 'app/app.vue'],
    rules: {
      'vue/multi-word-component-names': 'off',
    },
  },
  {
    files: ['**/*.ts', '**/*.vue'],
    rules: {
      '@typescript-eslint/consistent-type-imports': [
        'error',
        { prefer: 'type-imports', fixStyle: 'separate-type-imports' },
      ],
    },
  },
  {
    ignores: ['editor/dist/**', '.output/**', '.nuxt/**'],
  },
)
