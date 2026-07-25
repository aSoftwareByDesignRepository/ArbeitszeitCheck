// @ts-check
import { test, expect } from '@playwright/test'
import { login, credsFromEnv } from './helpers/auth.js'
import { assertArbeitszeitcheckLoaded } from './helpers/app-config.js'

/**
 * Page roots must fill the wide shell — no leftover 56rem page caps.
 */
test.describe('Admin page full-width shell', () => {
	test.skip(!process.env.NC_ADMIN_USER, 'Requires NC_ADMIN_USER / NC_ADMIN_PASS')

	test('license page uses available content width', async ({ page }) => {
		await page.setViewportSize({ width: 1600, height: 900 })
		await login(page, credsFromEnv('ADMIN'))
		await page.goto('/apps/arbeitszeitcheck/admin/license')
		await assertArbeitszeitcheckLoaded(page)

		await expect(page.locator('#app-content-wrapper')).toHaveClass(/azc-shell--wide/)
		const pageRoot = page.locator('.azc-license-page')
		await expect(pageRoot).toBeVisible()

		const metrics = await page.evaluate(() => {
			const shell = document.querySelector('#app-content-wrapper')
			const root = document.querySelector('.azc-license-page')
			const main = document.querySelector('#azc-main-content')
			if (!shell || !root || !main) {
				return { ok: false }
			}
			const shellR = shell.getBoundingClientRect()
			const rootR = root.getBoundingClientRect()
			const mainR = main.getBoundingClientRect()
			const cs = getComputedStyle(root)
			return {
				ok: true,
				shellWidth: shellR.width,
				mainWidth: mainR.width,
				rootWidth: rootR.width,
				maxWidth: cs.maxWidth,
			}
		})

		expect(metrics.ok).toBe(true)
		expect(metrics.maxWidth).toBe('none')
		// Page root must nearly fill main (allow ≤24px for rounding / gaps).
		expect(metrics.rootWidth).toBeGreaterThan(metrics.mainWidth - 24)
		expect(metrics.rootWidth).toBeGreaterThan(900)
	})

	test('support-us page root is not capped at 56rem', async ({ page }) => {
		await page.setViewportSize({ width: 1600, height: 900 })
		await login(page, credsFromEnv('ADMIN'))
		await page.goto('/apps/arbeitszeitcheck/admin/support-us')
		await assertArbeitszeitcheckLoaded(page)

		await expect(page.locator('#app-content-wrapper')).toHaveClass(/azc-shell--wide/)
		const metrics = await page.evaluate(() => {
			const root = document.querySelector('.azc-support-us-page')
			const main = document.querySelector('#azc-main-content')
			if (!root || !main) {
				return { ok: false }
			}
			return {
				ok: true,
				maxWidth: getComputedStyle(root).maxWidth,
				rootWidth: root.getBoundingClientRect().width,
				mainWidth: main.getBoundingClientRect().width,
			}
		})
		expect(metrics.ok).toBe(true)
		expect(metrics.maxWidth).toBe('none')
		expect(metrics.rootWidth).toBeGreaterThan(metrics.mainWidth - 24)
	})
})
