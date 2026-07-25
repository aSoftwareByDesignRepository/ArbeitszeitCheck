// @ts-check
import { test, expect } from '@playwright/test'
import { login, credsFromEnv } from './helpers/auth.js'
import { assertArbeitszeitcheckLoaded } from './helpers/app-config.js'

/**
 * Type + Past record badges must share one inline group (not stacked siblings).
 * Injects a controlled fixture row so the assertion does not depend on live data.
 */
test.describe('Absences past-record badge layout', () => {
	test.skip(!process.env.NC_EMPLOYEE_USER, 'Requires NC_EMPLOYEE_USER / NC_EMPLOYEE_PASS')

	test('type and past-record badges share an inline flex group', async ({ page }) => {
		await page.setViewportSize({ width: 1280, height: 900 })
		await login(page, credsFromEnv('EMPLOYEE'))
		await page.goto('/apps/arbeitszeitcheck/absences')
		await assertArbeitszeitcheckLoaded(page)
		await page.waitForSelector('#absences-table, .absences-table, #app-content.azc-app--absences', {
			timeout: 30000,
		})

		const metrics = await page.evaluate(() => {
			const table = document.querySelector('#absences-table, table.absences-table')
			if (!table) {
				return { ok: false, reason: 'no-table' }
			}
			const tbody = table.querySelector('tbody')
			if (!tbody) {
				return { ok: false, reason: 'no-tbody' }
			}
			const tr = document.createElement('tr')
			tr.setAttribute('data-e2e-fixture', 'past-record-inline')
			tr.innerHTML = `
				<td data-label="Type">
					<span class="absence-type-badges">
						<span class="absence-type-badge type-unpaid_leave">Unpaid Leave</span>
						<span class="badge badge--past-record absence-past-record-badge">Past record</span>
					</span>
				</td>
				<td data-label="Start Date">01.01.2020</td>
				<td data-label="End Date">02.01.2020</td>
			`
			tbody.prepend(tr)

			const group = tr.querySelector('.absence-type-badges')
			const type = tr.querySelector('.absence-type-badge')
			const past = tr.querySelector('.absence-past-record-badge')
			if (!group || !type || !past) {
				return { ok: false, reason: 'missing-badges' }
			}
			const gs = getComputedStyle(group)
			const typeR = type.getBoundingClientRect()
			const pastR = past.getBoundingClientRect()
			const marginTop = getComputedStyle(past).marginTop
			return {
				ok: true,
				display: gs.display,
				gap: gs.gap || gs.columnGap,
				marginTop,
				deltaY: Math.abs(typeR.top - pastR.top),
				deltaX: pastR.left - typeR.right,
				typeW: typeR.width,
				pastW: pastR.width,
			}
		})

		expect(metrics.ok, metrics.reason || 'fixture failed').toBe(true)
		expect(metrics.display).toMatch(/inline-flex|flex/)
		expect(parseFloat(String(metrics.marginTop))).toBe(0)
		// Same row: tops align within a few pixels; past badge starts after type.
		expect(metrics.deltaY).toBeLessThan(12)
		expect(metrics.deltaX).toBeGreaterThanOrEqual(-2)
	})
})
