import { defineConfig } from 'vitest/config'

export default defineConfig({
  test: {
    environment: 'jsdom',
    include: [
      'js/**/*.test.js',
      'tests/js/admin-premium-policy.test.mjs',
      'tests/js/admin-policy-legacy-redirect.test.mjs',
      'tests/js/admin-settings-legacy-redirect.test.mjs',
      'tests/js/admin-settings-month-reopen-picker.test.mjs',
      'tests/js/employee-settings-legacy-redirect.test.mjs',
    ],
    setupFiles: ['tests/js/vitest.setup.js'],
    restoreMocks: true,
    clearMocks: true,
  },
})

