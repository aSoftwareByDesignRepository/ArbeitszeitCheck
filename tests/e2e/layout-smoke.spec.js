// @ts-check
import { test, expect } from '@playwright/test';
import { login, credsFromEnv } from './helpers/auth.js';

const routes = [
	{ path: '/apps/arbeitszeitcheck/dashboard', name: 'dashboard' },
	{ path: '/apps/arbeitszeitcheck/time-entries', name: 'time-entries' },
	{ path: '/apps/arbeitszeitcheck/settings/breaks', name: 'settings' },
	{ path: '/apps/arbeitszeitcheck/compliance', name: 'compliance' },
];

for (const { path, name } of routes) {
	test(`layout smoke: ${name} has shell and no horizontal overflow`, async ({ page }) => {
		test.skip(!process.env.NC_EMPLOYEE_USER, 'Requires NC_EMPLOYEE_USER / NC_EMPLOYEE_PASS');
		await page.setViewportSize({ width: 375, height: 800 });
		await login(page, credsFromEnv('EMPLOYEE'));
		await page.goto(path);
		await page.waitForSelector('#azc-main-content', { timeout: 30000 });
		await expect(page.locator('.azc-page-header').first()).toBeVisible();
		await expect(page.locator('#azc-main-content')).toBeVisible();
		const overflow = await page.evaluate(() => {
			const main = document.getElementById('azc-main-content');
			if (!main) {
				return false;
			}
			return main.scrollWidth <= main.clientWidth + 2;
		});
		expect(overflow).toBe(true);
	});
}
