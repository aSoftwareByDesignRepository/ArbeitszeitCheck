// @ts-check
import { test, expect } from '@playwright/test'
import { login, credsFromEnv } from './helpers/auth.js'
import { assertArbeitszeitcheckLoaded } from './helpers/app-config.js'

/**
 * Sidebar hierarchy: children nest modestly past the parent *label* (not mid-word
 * under “Administration”), with a gutter rail — not oversized nest + bullet dots.
 */
test.describe('Sidebar nav hierarchy', () => {
	test.skip(!process.env.NC_ADMIN_USER, 'Requires NC_ADMIN_USER / NC_ADMIN_PASS')

	test('admin submenu labels nest cleanly under Administration', async ({ page }) => {
		await page.setViewportSize({ width: 1280, height: 900 })
		await login(page, credsFromEnv('ADMIN'))
		await page.goto('/apps/arbeitszeitcheck/admin')
		await assertArbeitszeitcheckLoaded(page)
		await page.waitForSelector('#admin-subnav', { timeout: 30000 })

		const adminToggle = page.locator('#app-navigation .nav-item-has-children').filter({
			has: page.locator('#admin-subnav'),
		}).locator('.nav-parent-toggle')
		await expect(adminToggle).toBeVisible()
		await expect(adminToggle.locator('.nav-parent-chevron')).toBeVisible()

		const submenu = page.locator('#admin-subnav')
		await expect(submenu).toBeVisible()
		if (await submenu.getAttribute('hidden') !== null) {
			await adminToggle.click()
		}
		await expect(submenu).not.toHaveAttribute('hidden', '')

		const metrics = await page.evaluate(() => {
			const toggle = document.querySelector(
				'#app-navigation .nav-item-has-children:has(#admin-subnav) > .nav-parent-toggle'
			)
			const parentLabel = toggle?.querySelector('span:not(.azc-nav__icon):not(.nav-parent-chevron)')
			const parentIcon = toggle?.querySelector('.azc-nav__icon')
			const childLink = document.querySelector('#admin-subnav > li > a')
			const childLabel = childLink?.querySelector('span')
			const submenuEl = document.getElementById('admin-subnav')
			if (!toggle || !parentLabel || !childLink || !childLabel || !parentIcon || !submenuEl) {
				return { ok: false, reason: 'missing-nodes' }
			}
			const parentTextLeft = parentLabel.getBoundingClientRect().left
			const parentTextRight = parentLabel.getBoundingClientRect().right
			const childTextLeft = childLabel.getBoundingClientRect().left
			const iconLeft = parentIcon.getBoundingClientRect().left
			const iconRight = parentIcon.getBoundingClientRect().right
			const styles = getComputedStyle(childLink)
			const before = getComputedStyle(childLink, '::before')
			const rail = getComputedStyle(submenuEl, '::before')
			const nav = document.getElementById('app-navigation')
			const nest = nav ? getComputedStyle(nav).getPropertyValue('--azc-nav-child-nest').trim() : ''
			return {
				ok: true,
				parentTextLeft,
				childTextLeft,
				delta: childTextLeft - parentTextLeft,
				// Child must not start after most of the parent word (old “mid-Administration” bug)
				overshootPastParentWord: childTextLeft - parentTextRight,
				paddingLeft: styles.paddingLeft,
				nest,
				iconWidth: parentIcon.getBoundingClientRect().width,
				railDisplay: rail.display,
				railWidth: rail.width,
				railLeft: rail.left,
				bulletContent: before.content,
				bulletDisplay: before.display,
				iconGutterCenter: (iconLeft + iconRight) / 2,
			}
		})

		expect(metrics.ok, JSON.stringify(metrics)).toBe(true)
		// Modest nest past parent label (0.5rem ≈ 8px; allow subpixel + font variance)
		expect(metrics.delta).toBeGreaterThanOrEqual(6)
		expect(metrics.delta).toBeLessThanOrEqual(28)
		// Must not leap past the end of “Administration” into empty space
		expect(metrics.overshootPastParentWord).toBeLessThan(8)
		expect(parseFloat(String(metrics.paddingLeft))).toBeGreaterThan(36)
		expect(String(metrics.nest)).toMatch(/0\.5rem|0\.375rem/)
		expect(Number(metrics.iconWidth)).toBeGreaterThan(0)
		expect(Number(metrics.iconWidth)).toBeLessThan(28)
		// No bullet markers on child links
		expect(['none', 'normal', '']).toContain(String(metrics.bulletContent).replace(/"/g, ''))
		// Tree rail present in the icon gutter
		expect(String(metrics.railDisplay)).not.toBe('none')
		expect(parseFloat(String(metrics.railWidth))).toBeGreaterThanOrEqual(1)
	})

	test('hover and active chrome keep child indent and use inset pills', async ({ page }) => {
		await page.setViewportSize({ width: 1280, height: 900 })
		await login(page, credsFromEnv('ADMIN'))
		await page.goto('/apps/arbeitszeitcheck/admin/settings')
		await assertArbeitszeitcheckLoaded(page)
		await page.waitForSelector('#admin-subnav', { timeout: 30000 })

		const submenu = page.locator('#admin-subnav')
		if (await submenu.getAttribute('hidden') !== null) {
			await page.locator('#app-navigation .nav-item-has-children').filter({
				has: page.locator('#admin-subnav'),
			}).locator('.nav-parent-toggle').click()
		}

		const active = page.locator('#admin-subnav > li.active > a, #admin-subnav a[aria-current="page"]').first()
		await expect(active).toBeVisible()
		await active.scrollIntoViewIfNeeded()

		const idle = page.locator('#admin-subnav > li:not(.active) > a').first()
		await expect(idle).toBeVisible()

		const before = await idle.evaluate((el) => {
			const label = el.querySelector('span')
			const s = getComputedStyle(el)
			return {
				labelLeft: label?.getBoundingClientRect().left ?? 0,
				transform: s.transform,
				paddingLeft: s.paddingLeft,
				marginLeft: s.marginLeft,
				radius: s.borderRadius,
			}
		})

		await idle.hover()
		const hovered = await idle.evaluate((el) => {
			const label = el.querySelector('span')
			const s = getComputedStyle(el)
			return {
				labelLeft: label?.getBoundingClientRect().left ?? 0,
				transform: s.transform,
				paddingLeft: s.paddingLeft,
				bg: s.backgroundColor,
			}
		})

		expect(hovered.transform).toBe('none')
		expect(Math.abs(hovered.labelLeft - before.labelLeft)).toBeLessThan(1)
		expect(hovered.paddingLeft).toBe(before.paddingLeft)
		expect(parseFloat(String(before.marginLeft))).toBeGreaterThanOrEqual(4)
		expect(parseFloat(String(before.radius))).toBeGreaterThanOrEqual(4)

		const activeMetrics = await active.evaluate((el) => {
			const parent = document.querySelector(
				'#app-navigation .nav-item-has-children:has(#admin-subnav) > .nav-parent-toggle'
			)
			const s = getComputedStyle(el)
			const after = getComputedStyle(el, '::after')
			const ps = parent ? getComputedStyle(parent) : null
			return {
				color: s.color,
				bg: s.backgroundColor,
				afterBg: after.backgroundColor,
				afterLeft: after.left,
				shadow: s.boxShadow,
				parentBg: ps?.backgroundColor ?? '',
				parentMarginLeft: ps?.marginLeft ?? '',
				parentPaddingLeft: ps?.paddingLeft ?? '',
			}
		})

		// Active child: primary fill on ::after (rail gutter stays clear).
		expect(String(activeMetrics.shadow)).toMatch(/none/)
		expect(activeMetrics.afterBg).not.toMatch(/rgba\(0,\s*0,\s*0,\s*0\)|transparent/)
		expect(parseFloat(String(activeMetrics.afterLeft))).toBeGreaterThan(8)
		// Open parent must not look like the active page (no primary fill).
		expect(activeMetrics.parentBg).toMatch(/rgba\(0,\s*0,\s*0,\s*0\)|transparent/)
		expect(parseFloat(String(activeMetrics.parentMarginLeft))).toBeGreaterThanOrEqual(6)
		expect(parseFloat(String(activeMetrics.parentPaddingLeft))).toBeGreaterThanOrEqual(16)

		const hoverSurface = await idle.evaluate((el) => {
			const after = getComputedStyle(el, '::after')
			const rail = getComputedStyle(document.getElementById('admin-subnav'), '::before')
			return {
				afterLeft: parseFloat(after.left),
				afterBg: after.backgroundColor,
				railLeft: parseFloat(rail.left),
			}
		})
		// Hover pill starts to the right of the tree rail (no paint-over).
		expect(hoverSurface.afterLeft).toBeGreaterThan(0)
		expect(hoverSurface.afterBg).not.toMatch(/rgba\(0,\s*0,\s*0,\s*0\)|transparent/)
	})
})
