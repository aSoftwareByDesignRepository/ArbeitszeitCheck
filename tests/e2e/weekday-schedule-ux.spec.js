// @ts-check
/**
 * Phase A Bachus: weekday schedule matrix UX + WCAG on fixture.
 */
import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const fixtureUrl = 'file://' + path.resolve(__dirname, 'fixtures/weekday-schedule-config.html');

test.describe('Phase A: weekday schedule fixture', () => {
	test('J-S1: preset fills BANSS nets and syncs weekly total', async ({ page }) => {
		await page.goto(fixtureUrl);
		await expect(page.locator('#weekday-schedule-panel')).toBeVisible();

		await page.locator('[data-action="apply-weekday-preset"]').click();
		await expect(page.locator('#fx-mon-net')).toHaveText('8.50 h');
		await expect(page.locator('#fx-fri-net')).toHaveText('4.50 h');
		await expect(page.locator('#fx-week-total')).toHaveText(/38\.50/);
		await expect(page.locator('#weeklyHours')).toHaveValue('38.50');
		await expect(page.locator('#weeklyHours')).toHaveAttribute('readonly', '');
		await expect(page.locator('#dailyHours')).toHaveValue('7.70');
		await expect(page.locator('#workDaysPerWeek')).toHaveValue('5');
	});

	test('J-S2: clear restores editable scalars', async ({ page }) => {
		await page.goto(fixtureUrl);
		await page.locator('[data-action="apply-weekday-preset"]').click();
		await page.locator('[data-action="clear-weekday-schedule"]').click();
		await expect(page.locator('#fx-week-total')).toHaveText('');
		await expect(page.locator('#weeklyHours')).not.toHaveAttribute('readonly', '');
	});

	test('J-S3: axe clean on weekday schedule panel', async ({ page }) => {
		await page.goto(fixtureUrl);
		await page.locator('[data-action="apply-weekday-preset"]').click();

		const results = await new AxeBuilder({ page })
			.include('#weekday-schedule-panel')
			.withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
			.analyze();

		expect(results.violations, JSON.stringify(results.violations, null, 2)).toEqual([]);
	});

	test('J-S4: contract — live WTM JS must not open create/edit modals', async () => {
		const fs = await import('node:fs');
		const pathMod = await import('node:path');
		const jsPath = pathMod.resolve(__dirname, '../../js/working-time-models.js');
		const js = fs.readFileSync(jsPath, 'utf8');
		expect(js).toContain('function openEditorPanel');
		expect(js).not.toContain('create-model-modal');
		expect(js).not.toContain('edit-model-modal');
	});
});
