// @ts-check
import { test, expect } from '@playwright/test'
import { login, credsFromEnv } from './helpers/auth.js'
import { assertArbeitszeitcheckLoaded } from './helpers/app-config.js'

/**
 * Support & Us is a dedicated Administration page with a clear CTA hierarchy.
 */
test.describe('Admin Support & Us page', () => {
	test.skip(!process.env.NC_ADMIN_USER, 'Requires NC_ADMIN_USER / NC_ADMIN_PASS')

	test('hero + offers layout with safe external links and keyboard focus', async ({ page }) => {
		await page.setViewportSize({ width: 1280, height: 900 })
		await login(page, credsFromEnv('ADMIN'))
		await page.goto('/apps/arbeitszeitcheck/admin/support-us')
		await assertArbeitszeitcheckLoaded(page)

		const hero = page.locator('.azc-support-us-page__hero')
		const section = page.locator('#azc-support-us')
		await hero.waitFor({ state: 'visible', timeout: 30000 })
		await section.waitFor({ state: 'visible', timeout: 30000 })

		await expect(page.locator('.azc-support-us-page')).toHaveAttribute(
			'data-azc-support-us-layout',
			'offer-grid',
		)

		const brand = page.locator('[data-azc-vendor-logo="1"]')
		await expect(brand).toBeVisible()
		await expect(page.locator('.azc-support-us-page__wordmark-by')).toHaveText(/BY DESIGN/)
		await expect(page.locator('.azc-support-us-page__mark')).toHaveAttribute('src', /vendor-logo-mark\.png/)
		await expect(page.locator('.azc-support-us-page__hero-main')).toBeVisible()
		await expect(page.locator('.azc-support-us__option-title')).toHaveCount(3)

		const layoutMetrics = await page.evaluate(() => {
			const pageRoot = document.querySelector('.azc-support-us-page')
			const main = document.querySelector('#azc-main-content')
			const heroEl = document.querySelector('.azc-support-us-page__hero')
			const heroMain = document.querySelector('.azc-support-us-page__hero-main')
			const trust = document.querySelector('.azc-support-us-page__trust')
			const primary = document.querySelector('.azc-support-us__primary')
			const options = document.querySelector('.azc-support-us__options')
			const optionTitles = document.querySelectorAll('.azc-support-us__option-title')
			if (!pageRoot || !main || !heroEl || !heroMain || !trust || !primary || !options) {
				return { ok: false }
			}
			const rootR = pageRoot.getBoundingClientRect()
			const mainR = main.getBoundingClientRect()
			const heroMainR = heroMain.getBoundingClientRect()
			const trustR = trust.getBoundingClientRect()
			const primaryR = primary.getBoundingClientRect()
			const optionsR = options.getBoundingClientRect()
			const trustStyle = getComputedStyle(trust)
			return {
				ok: true,
				rootMax: getComputedStyle(pageRoot).maxWidth,
				primaryMax: getComputedStyle(primary).maxWidth,
				rootWidth: rootR.width,
				mainWidth: mainR.width,
				primaryWidth: primaryR.width,
				optionsWidth: optionsR.width,
				trustListStyle: trustStyle.listStyleType,
				trustPadL: parseFloat(trustStyle.paddingLeft) || 0,
				heroIsGrid: getComputedStyle(heroEl).display === 'grid',
				trustBesideHero:
					Math.abs(trustR.top - heroMainR.top) < 48
					&& trustR.left > heroMainR.right - 8,
				optionTitleCount: optionTitles.length,
			}
		})
		expect(layoutMetrics.ok).toBe(true)
		expect(layoutMetrics.rootMax).toBe('none')
		expect(layoutMetrics.primaryMax).toBe('none')
		expect(layoutMetrics.rootWidth).toBeGreaterThan(layoutMetrics.mainWidth - 24)
		expect(layoutMetrics.primaryWidth).toBeGreaterThan(layoutMetrics.rootWidth - 40)
		expect(layoutMetrics.optionsWidth).toBeGreaterThan(layoutMetrics.rootWidth - 40)
		expect(layoutMetrics.primaryWidth).toBeGreaterThan(800)
		expect(layoutMetrics.trustListStyle).toBe('none')
		expect(layoutMetrics.heroIsGrid).toBe(true)
		expect(layoutMetrics.trustBesideHero).toBe(true)
		expect(layoutMetrics.optionTitleCount).toBe(3)

		const brandLink = page.locator('a.azc-support-us-page__brand-link')
		await expect(brandLink).toHaveAttribute('rel', 'noopener noreferrer')
		await expect(brandLink).toHaveAttribute('target', '_blank')

		await expect(section).toHaveAttribute('data-support-us', '1')
		await expect(section).toHaveAttribute('data-support-us-presentation', 'page')
		await expect(page.locator('#azc-support-us-hero-title')).toBeVisible()
		await expect(page.locator('#azc-support-us-title')).toBeVisible()
		await expect(page.locator('#azc-support-us-partner-title')).toBeVisible()
		await expect(page.locator('#azc-support-us-secondary-label')).toBeVisible()

		const partnerCta = section.locator('a.azc-support-us__cta--primary')
		await expect(partnerCta).toBeVisible()
		await expect(partnerCta).toHaveAttribute('href', /mailto:info@software-by-design\.de/)
		await expect(partnerCta).toHaveClass(/azc-btn--primary/)

		const ctaChrome = await partnerCta.evaluate((el) => {
			const s = getComputedStyle(el)
			return {
				bg: s.backgroundColor,
				color: s.color,
				decoration: s.textDecorationLine || s.textDecoration,
				minHeight: parseFloat(s.minHeight),
				borderRadius: s.borderRadius,
			}
		})
		// Must be a solid primary button — not a demoted underlined link.
		expect(ctaChrome.bg).not.toMatch(/rgba\(0,\s*0,\s*0,\s*0\)|transparent/)
		expect(ctaChrome.bg).toMatch(/^rgb\(/)
		expect(String(ctaChrome.decoration)).toMatch(/none/)
		expect(ctaChrome.minHeight).toBeGreaterThanOrEqual(40)
		expect(parseFloat(ctaChrome.borderRadius)).toBeGreaterThanOrEqual(8)

		const secondaryCta = section.locator('a.azc-support-us__cta--secondary').first()
		await expect(secondaryCta).toBeVisible()
		const secondaryChrome = await secondaryCta.evaluate((el) => {
			const s = getComputedStyle(el)
			return {
				bg: s.backgroundColor,
				decoration: s.textDecorationLine || s.textDecoration,
			}
		})
		expect(secondaryChrome.bg).not.toMatch(/rgba\(0,\s*0,\s*0,\s*0\)|transparent/)
		expect(String(secondaryChrome.decoration)).toMatch(/none/)

		const blankLinks = section.locator('a[target="_blank"]')
		const blankCount = await blankLinks.count()
		expect(blankCount).toBeGreaterThanOrEqual(2)
		for (let i = 0; i < blankCount; i++) {
			await expect(blankLinks.nth(i)).toHaveAttribute('rel', 'noopener noreferrer')
		}

		const metrics = await page.evaluate(() => {
			const el = document.getElementById('azc-support-us')
			const pageRoot = document.querySelector('.azc-support-us-page')
			const heroEl = document.querySelector('.azc-support-us-page__hero')
			if (!el || !pageRoot || !heroEl) {
				return { ok: false, reason: 'missing-nodes' }
			}
			const elR = el.getBoundingClientRect()
			const heroR = heroEl.getBoundingClientRect()
			return {
				ok: true,
				elWidth: elR.width,
				heroWidth: heroR.width,
				parentIsPage: el.parentElement === pageRoot,
				presentation: el.getAttribute('data-support-us-presentation'),
			}
		})

		expect(metrics.ok).toBe(true)
		expect(metrics.parentIsPage).toBe(true)
		expect(metrics.presentation).toBe('page')
		expect(metrics.elWidth).toBeGreaterThan(400)
		expect(metrics.heroWidth).toBeGreaterThan(400)

		const navLink = page.locator('#app-navigation a[href*="/admin/support-us"]')
		await expect(navLink).toBeVisible()

		await partnerCta.focus()
		await expect(partnerCta).toBeFocused()
	})

	test('settings no longer embeds Support Us; cross-link reaches the page', async ({ page }) => {
		await page.setViewportSize({ width: 1280, height: 900 })
		await login(page, credsFromEnv('ADMIN'))
		await page.goto('/apps/arbeitszeitcheck/admin/settings')
		await assertArbeitszeitcheckLoaded(page)

		await expect(page.locator('#azc-support-us')).toHaveCount(0)
		const crossLink = page.locator('.azc-admin-settings__support-link a')
		await expect(crossLink).toBeVisible()
		await crossLink.click()
		await expect(page).toHaveURL(/\/admin\/support-us/)
		await expect(page.locator('#azc-support-us')).toBeVisible()
	})

	test('mobile viewport: CTAs remain usable without horizontal overflow', async ({ page }) => {
		await page.setViewportSize({ width: 390, height: 844 })
		await login(page, credsFromEnv('ADMIN'))
		await page.goto('/apps/arbeitszeitcheck/admin/support-us')
		await assertArbeitszeitcheckLoaded(page)

		const section = page.locator('#azc-support-us')
		await section.waitFor({ state: 'visible', timeout: 30000 })
		await section.scrollIntoViewIfNeeded()

		const overflow = await page.evaluate(() => {
			const doc = document.documentElement
			const el = document.getElementById('azc-support-us')
			const hero = document.querySelector('.azc-support-us-page__hero')
			const trust = document.querySelector('.azc-support-us-page__trust')
			const heroMain = document.querySelector('.azc-support-us-page__hero-main')
			if (!el || !hero || !trust || !heroMain) {
				return { ok: false }
			}
			const trustR = trust.getBoundingClientRect()
			const mainR = heroMain.getBoundingClientRect()
			return {
				ok: true,
				scrollWidth: doc.scrollWidth,
				clientWidth: doc.clientWidth,
				ctaWidth: el.querySelector('.azc-support-us__cta--primary')?.getBoundingClientRect().width ?? 0,
				// Stacked: trust sits below hero copy (not beside it).
				trustBelow: trustR.top >= mainR.bottom - 4,
			}
		})

		expect(overflow.ok).toBe(true)
		expect(overflow.scrollWidth).toBeLessThanOrEqual(overflow.clientWidth + 1)
		expect(overflow.ctaWidth).toBeGreaterThan(120)
		expect(overflow.trustBelow).toBe(true)
	})
})
