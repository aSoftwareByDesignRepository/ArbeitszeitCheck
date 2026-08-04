// @ts-check
/**
 * Bachus journeys: overtime discoverability + Saldo≠payout + simplified premium fixture a11y.
 */
import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import path from 'path';
import { fileURLToPath } from 'url';
import { login, credsFromEnv } from './helpers/auth.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const fixtureUrl = 'file://' + path.resolve(__dirname, 'fixtures/bachus-premium-config.html');
const dashboardPremiumFixtureUrl = 'file://' + path.resolve(__dirname, 'fixtures/dashboard-premium-summary.html');

test.describe('Bachus: overtime discoverability (employee)', () => {
	test.beforeEach(async ({ page }) => {
		test.skip(!process.env.NC_EMPLOYEE_USER, 'Requires NC_EMPLOYEE_USER / NC_EMPLOYEE_PASS');
		await login(page, credsFromEnv('EMPLOYEE'));
	});

	test('J-E1/J-E2: overtime balance card always visible with formula', async ({ page }) => {
		await page.goto('/apps/arbeitszeitcheck/dashboard');
		await page.waitForSelector('#azc-main-content', { timeout: 30000 });

		const heading = page.locator('#dashboard-overtime-heading');
		await expect(heading).toBeVisible();
		await expect(heading).toHaveText(/overtime balance|Überstundensaldo|Ihre Überstunden/i);

		const formula = page.locator('#dashboard-overtime-formula');
		await expect(formula).toBeVisible();
		await expect(formula).not.toBeEmpty();

		const balance = page.locator('#dashboard-overtime-balance-value');
		await expect(balance).toBeVisible();
		await expect(balance).toContainText(/h/i);
	});

	test('J-E3: axe clean on overtime card region', async ({ page }) => {
		await page.goto('/apps/arbeitszeitcheck/dashboard');
		await page.waitForSelector('#dashboard-overtime-heading', { timeout: 30000 });

		const results = await new AxeBuilder({ page })
			.include('.dashboard-overtime-card')
			.withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
			.analyze();

		expect(results.violations, JSON.stringify(results.violations, null, 2)).toEqual([]);
	});
});

test.describe('Bachus: payout vs Saldo (admin)', () => {
	test('J-A1: payouts page explains Saldo is on the dashboard', async ({ page }) => {
		test.skip(!process.env.NC_ADMIN_USER, 'Requires NC_ADMIN_USER / NC_ADMIN_PASS');
		await login(page, credsFromEnv('ADMIN'));
		await page.goto('/apps/arbeitszeitcheck/admin/overtime-payouts');
		await page.waitForSelector('#azc-main-content', { timeout: 30000 });

		const hint = page.locator('#admin-ot-payout-saldo-hint');
		await expect(hint).toHaveCount(1);
		await expect(hint).toBeVisible();
		await expect(hint).toContainText(/dashboard|Arbeitsplatz|Übersicht|balance|Saldo|contract|Soll|gearbeitet|Stundensaldo/i);
	});
});

test.describe('Bachus: simplified premium config fixture', () => {
	test('J-P1: one-screen config is axe-clean and saveable', async ({ page }) => {
		await page.goto(fixtureUrl);
		await expect(page.locator('#premium-title')).toBeVisible();

		const resultsOff = await new AxeBuilder({ page })
			.include('#premium-simple-config')
			.withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
			.analyze();
		expect(resultsOff.violations, JSON.stringify(resultsOff.violations, null, 2)).toEqual([]);

		await page.locator('#premium-enabled').check();
		await expect(page.locator('#premium-panel')).toBeVisible();

		const resultsOn = await new AxeBuilder({ page })
			.include('#premium-simple-config')
			.withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
			.analyze();
		expect(resultsOn.violations, JSON.stringify(resultsOn.violations, null, 2)).toEqual([]);

		await page.locator('#premium-save').click();
		await expect(page.locator('#premium-status')).toContainText(/saved|gespeichert/i);
	});
});

test.describe('Bachus: dashboard Saldo + Zuschläge fixture', () => {
	test('J-P2: premiums section stays separate from Saldo and is axe-clean', async ({ page }) => {
		await page.goto(dashboardPremiumFixtureUrl);
		await expect(page.locator('#dashboard-overtime-heading')).toBeVisible();
		await expect(page.locator('#dashboard-overtime-balance-value')).toContainText(/12\.50/);
		await expect(page.locator('#dashboard-premium-heading')).toBeVisible();
		await expect(page.locator('#dashboard-premium-help')).toContainText(/not pay|not your Saldo/i);
		await expect(page.locator('.dashboard-premium-list li')).toHaveCount(2);

		const results = await new AxeBuilder({ page })
			.include('.dashboard-overtime-card')
			.withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
			.analyze();
		expect(results.violations, JSON.stringify(results.violations, null, 2)).toEqual([]);
	});
});
