import { test, expect } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'
import { login, credsFromEnv } from './helpers/auth.js'
import { api } from './helpers/api.js'
import { assertArbeitszeitcheckLoaded } from './helpers/app-config.js'

/**
 * DACH Phase 1–2 acceptance: Country & region admin UX + AT/CH holiday API.
 * Restores DE / NW after each run so the shared Docker instance stays neutral.
 */
test.describe('DACH country & region (admin)', () => {
	test.skip(!process.env.NC_ADMIN_USER, 'Requires NC_ADMIN_USER / NC_ADMIN_PASS')

	test.afterEach(async ({ page }) => {
		// Best-effort restore — ignore if login session already gone.
		try {
			await api(page, 'POST', '/apps/arbeitszeitcheck/api/admin/settings', {
				data: { country: 'DE', germanState: 'NW' },
			})
		} catch {
			/* ignore */
		}
	})

	test('settings UI: country radios, region filter, aria-live (keyboard)', async ({ page }) => {
		await login(page, credsFromEnv('ADMIN'))
		await page.goto('/apps/arbeitszeitcheck/admin/settings')
		await assertArbeitszeitcheckLoaded(page)
		await page.waitForSelector('#section-regional-heading', { timeout: 30000 })

		const fieldset = page.locator('fieldset').filter({ has: page.locator('#country-legend') })
		await expect(fieldset).toBeVisible()
		await expect(page.locator('#country-de')).toBeVisible()
		await expect(page.locator('#country-at')).toBeVisible()
		await expect(page.locator('#country-ch')).toBeVisible()

		const region = page.locator('#germanState')
		await expect(region).toBeVisible()

		// Start from Germany so the AT switch is a real change.
		await page.locator('#country-de').check()
		await expect(page.locator('#country-de')).toBeChecked()

		await page.locator('#country-at').focus()
		await page.keyboard.press('Space')
		await expect(page.locator('#country-at')).toBeChecked()

		const atOptions = await region.locator('option').evaluateAll((opts) =>
			opts.map((o) => /** @type {HTMLOptionElement} */ (o).value)
		)
		expect(atOptions.every((code) => String(code).startsWith('AT-'))).toBe(true)
		expect(atOptions).toContain('AT-W')

		const live = page.locator('#country-region-live')
		await expect(live).toBeVisible()
		await expect(live).toHaveAttribute('aria-live', 'polite')
		await expect(live).not.toHaveText('')

		// Nothing auto-saved (WCAG 3.2.2) — instance still DE until form POST.
		const settings = await api(page, 'GET', '/apps/arbeitszeitcheck/api/admin/settings')
		expect(settings.success).toBe(true)
		expect(settings.settings?.country ?? 'DE').toBe('DE')

		// Nested radiogroup removed — fieldset owns the relationship (1.3.1).
		await expect(page.locator('.azc-country-grid[role="radiogroup"]')).toHaveCount(0)

		const vacationCallout = page.locator('#vacation-days-suggestion-callout')
		await expect(vacationCallout).toBeVisible()
		await expect(vacationCallout).toContainText('25')

		await page.locator('#country-ch').check()
		await expect(page.locator('#weekly-absolute-max-group')).toBeVisible()
		await expect(page.locator('#weeklyAbsoluteMaxHours')).toBeEnabled()
		await expect(vacationCallout).toHaveAttribute('data-current-suggestion', '20')

		const axe = await new AxeBuilder({ page })
			.include('#section-regional-heading')
			.include('#admin-settings-form')
			.withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
			.analyze()
		expect(axe.violations, JSON.stringify(axe.violations, null, 2)).toEqual([])
	})

	test('API: Austria country + OOE statutory catalog (no Good Friday seed)', async ({ page }) => {
		await login(page, credsFromEnv('ADMIN'))

		const year = 2027
		await api(page, 'POST', '/apps/arbeitszeitcheck/api/admin/settings', {
			data: { country: 'AT', germanState: 'AT-OOE' },
		})
		const settings = await api(page, 'GET', '/apps/arbeitszeitcheck/api/admin/settings')
		expect(settings.settings?.country).toBe('AT')
		expect(settings.settings?.germanState).toBe('AT-OOE')

		const list = await api(
			page,
			'GET',
			`/apps/arbeitszeitcheck/api/admin/state-holidays?state=AT-OOE&year=${year}`
		)
		expect(list.success).toBe(true)
		const statutory = (list.holidays || []).filter((h) => h.scope === 'statutory')
		expect(statutory.length).toBeGreaterThanOrEqual(13)

		const dates = statutory.map((h) => h.date)
		expect(dates).toContain(`${year}-10-26`) // Austrian National Day
		expect(dates).toContain(`${year}-01-01`)
		// Good Friday 2027-03-26 must never be auto-seeded (E-1).
		expect(dates).not.toContain(`${year}-03-26`)

		const suggestions = await api(
			page,
			'GET',
			`/apps/arbeitszeitcheck/api/admin/state-holidays/suggestions?state=AT-OOE&year=${year}`
		)
		expect(suggestions.success).toBe(true)
		expect(Array.isArray(suggestions.suggestions)).toBe(true)
		expect(suggestions.suggestions.length).toBeGreaterThan(0)
	})

	test('API: Switzerland ZH half-day statutory weight (E-6)', async ({ page }) => {
		await login(page, credsFromEnv('ADMIN'))

		const year = 2026
		await api(page, 'POST', '/apps/arbeitszeitcheck/api/admin/settings', {
			data: { country: 'CH', germanState: 'CH-ZH' },
		})
		const settings = await api(page, 'GET', '/apps/arbeitszeitcheck/api/admin/settings')
		expect(settings.settings?.country).toBe('CH')
		expect(settings.settings?.germanState).toBe('CH-ZH')

		const list = await api(
			page,
			'GET',
			`/apps/arbeitszeitcheck/api/admin/state-holidays?state=CH-ZH&year=${year}`
		)
		expect(list.success).toBe(true)
		const statutory = (list.holidays || []).filter((h) => h.scope === 'statutory')
		expect(statutory.length).toBeGreaterThanOrEqual(10)

		const half = statutory.filter((h) => h.kind === 'half')
		// Zurich Sechseläuten / Knabenschiessen are half-day statutory (E-6).
		expect(half.length).toBeGreaterThanOrEqual(1)
	})

	test('API: E-4 country switch keeps explicit max daily hours', async ({ page }) => {
		await login(page, credsFromEnv('ADMIN'))

		await api(page, 'POST', '/apps/arbeitszeitcheck/api/admin/settings', {
			data: {
				country: 'DE',
				germanState: 'NW',
				maxDailyHours: '9.25',
				minRestPeriod: '12',
			},
		})
		await api(page, 'POST', '/apps/arbeitszeitcheck/api/admin/settings', {
			data: { country: 'AT' },
		})
		const after = await api(page, 'GET', '/apps/arbeitszeitcheck/api/admin/settings')
		expect(after.settings?.country).toBe('AT')
		expect(after.settings?.germanState).toBe('AT-W')
		expect(Number(after.settings?.maxDailyHours)).toBeCloseTo(9.25, 2)
		expect(Number(after.settings?.minRestPeriod)).toBe(12)
	})
})
