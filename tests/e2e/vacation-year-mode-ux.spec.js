// @ts-check
/**
 * Phase B Bachus: vacation year mode radios + WCAG on fixture.
 */
import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const fixtureUrl = 'file://' + path.resolve(__dirname, 'fixtures/vacation-year-mode-config.html');

test.describe('Phase B: vacation year mode fixture', () => {
	test('J-V1: anniversary radio selectable and saveable', async ({ page }) => {
		await page.goto(fixtureUrl);
		await expect(page.locator('#vacation-year-title')).toBeVisible();
		await page.locator('#vacationYearMode-anniversary').check();
		await page.locator('button[type="submit"]').click();
		await expect(page.locator('#save-status')).toHaveText(/anniversary/);
	});

	test('J-V2: axe clean on vacation year config', async ({ page }) => {
		await page.goto(fixtureUrl);
		const results = await new AxeBuilder({ page })
			.include('#vacation-year-config')
			.withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
			.analyze();
		expect(results.violations, JSON.stringify(results.violations, null, 2)).toEqual([]);
	});
});
