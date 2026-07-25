// @ts-check
import { test, expect } from '@playwright/test'
import { login, credsFromEnv } from './helpers/auth.js'
import { assertArbeitszeitcheckLoaded } from './helpers/app-config.js'

/**
 * App-wide azc-btn chrome must stay solid (not demoted to underlined links).
 */
test.describe('azc-btn cascade chrome', () => {
	test.skip(!process.env.NC_ADMIN_USER, 'Requires NC_ADMIN_USER / NC_ADMIN_PASS')

	test('admin employees actions keep solid secondary buttons', async ({ page }) => {
		await page.setViewportSize({ width: 1280, height: 900 })
		await login(page, credsFromEnv('ADMIN'))
		await page.goto('/apps/arbeitszeitcheck/admin/users')
		await assertArbeitszeitcheckLoaded(page)

		const edit = page.locator('a.azc-btn.azc-btn--secondary[data-action="edit-user"]').first()
		await edit.waitFor({ state: 'visible', timeout: 30000 })

		const chrome = await edit.evaluate((el) => {
			const s = getComputedStyle(el)
			return {
				bg: s.backgroundColor,
				decoration: s.textDecorationLine || s.textDecoration,
				minHeight: parseFloat(s.minHeight),
			}
		})
		expect(chrome.bg).not.toMatch(/rgba\(0,\s*0,\s*0,\s*0\)|transparent/)
		expect(String(chrome.decoration)).toMatch(/none/)
		expect(chrome.minHeight).toBeGreaterThanOrEqual(32)
	})

	test('kiosk pin modal secondary actions are not bare azc-btn', async ({ page }) => {
		await page.setViewportSize({ width: 1280, height: 900 })
		await login(page, credsFromEnv('ADMIN'))
		await page.goto('/apps/arbeitszeitcheck/admin/kiosk')
		await assertArbeitszeitcheckLoaded(page)

		// Hidden until a PIN is issued — assert class contract in DOM regardless.
		const email = page.locator('#azc-kiosk-pin-email')
		await expect(email).toHaveCount(1)
		await expect(email).toHaveClass(/azc-btn--secondary/)
		const close = page.locator('#azc-kiosk-pin-close')
		await expect(close).toHaveClass(/azc-btn--secondary/)
	})
})
