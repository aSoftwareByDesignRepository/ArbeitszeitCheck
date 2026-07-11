/**
 * @vitest-environment node
 */
import { createRequire } from 'node:module'
import { describe, it } from 'vitest'
import { RuleTester } from 'eslint'

const require = createRequire(import.meta.url)
const plugin = require('../../scripts/eslint/arbeitszeitcheck-plugin.cjs')

const ruleTester = new RuleTester({
  parserOptions: { ecmaVersion: 'latest', sourceType: 'script' },
})

describe('eslint-plugin-arbeitszeitcheck URL guards', () => {
  it('no-raw-app-url allows approved helpers and blocks bare literals', () => {
    ruleTester.run('no-raw-app-url', plugin.rules['no-raw-app-url'], {
      valid: [
        "Utils.buildAppUrl('/apps/arbeitszeitcheck/api/admin/users')",
        "Utils.ajax('/apps/arbeitszeitcheck/api/clock/status', {})",
        "OC.generateUrl('/apps/arbeitszeitcheck/dashboard')",
        "Utils.resolveUrl(config.exportUrl || '/apps/arbeitszeitcheck/api/x')",
        "const marker = '/custom_apps/arbeitszeitcheck/'",
      ],
      invalid: [
        {
          code: "const url = '/apps/arbeitszeitcheck/api/admin/users'; void url",
          errors: [{ message: /Raw \/apps\/arbeitszeitcheck/ }],
        },
      ],
    })
  })

  it('no-raw-app-navigation blocks direct navigation to raw app paths', () => {
    ruleTester.run('no-raw-app-navigation', plugin.rules['no-raw-app-navigation'], {
      valid: [
        "window.location.href = Utils.buildAppUrl('/apps/arbeitszeitcheck/export')",
        "window.open(OC.generateUrl('/apps/arbeitszeitcheck/export'), '_blank')",
      ],
      invalid: [
        {
          code: "window.location.href = '/apps/arbeitszeitcheck/api/admin/dashboard-employees?format=csv'",
          errors: [{ message: /Do not navigate/ }],
        },
      ],
    })
  })
})
