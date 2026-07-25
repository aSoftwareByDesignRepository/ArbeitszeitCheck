// @ts-check
import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { login, credsFromEnv } from './helpers/auth.js';

const a11yRoutes = [
	'/apps/arbeitszeitcheck/dashboard',
	'/apps/arbeitszeitcheck/settings',
	'/apps/arbeitszeitcheck/admin/settings',
];

for (const path of a11yRoutes) {
	test(`a11y smoke: ${path}`, async ({ page }) => {
		const needsAdmin = path.includes('/admin/');
		if (needsAdmin) {
			test.skip(!process.env.NC_ADMIN_USER, 'Requires NC_ADMIN_USER / NC_ADMIN_PASS');
			await login(page, credsFromEnv('ADMIN'));
		} else {
			test.skip(!process.env.NC_EMPLOYEE_USER, 'Requires NC_EMPLOYEE_USER / NC_EMPLOYEE_PASS');
			await login(page, credsFromEnv('EMPLOYEE'));
		}
		await page.goto(path);
		await page.waitForSelector('#azc-main-content', { timeout: 30000 });
		const results = await new AxeBuilder({ page })
			.withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
			.analyze();
		expect(results.violations, JSON.stringify(results.violations, null, 2)).toEqual([]);
	});
}
