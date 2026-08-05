// @ts-check
import { test, expect } from '@playwright/test';
import { login, credsFromEnv } from './helpers/auth.js';

/**
 * Guards the Vue home dashboard against arbeitszeitcheck l10n/*.js throwing
 * when window.OC is not ready yet (classic OC.L10N.register race).
 */
test.describe('NC home dashboard console safety', () => {
	test('admin dashboard loads without OC / arbeitszeitcheck l10n ReferenceErrors', async ({ page }) => {
		test.setTimeout(120000);
		test.skip(!process.env.NC_ADMIN_USER, 'Requires NC_ADMIN_USER / NC_ADMIN_PASS');

		/** @type {string[]} */
		const fatalConsole = [];
		page.on('pageerror', (err) => {
			fatalConsole.push(String(err?.message || err));
		});
		page.on('console', (msg) => {
			if (msg.type() !== 'error') {
				return;
			}
			const text = msg.text();
			if (/OC is not defined/i.test(text)) {
				fatalConsole.push(text);
			}
		});

		await login(page, credsFromEnv('ADMIN'));
		await page.goto('/apps/dashboard/', { waitUntil: 'domcontentloaded', timeout: 90000 });
		await page.locator('#app-dashboard').waitFor({ state: 'attached', timeout: 60000 });
		await page.waitForTimeout(3500);

		const azcOcErrors = fatalConsole.filter((line) => /OC is not defined/i.test(line));
		expect(azcOcErrors, JSON.stringify(azcOcErrors, null, 2)).toEqual([]);

		const state = await page.evaluate(() => ({
			hasOc: typeof window.OC !== 'undefined',
			hasAzcBoot: [...document.querySelectorAll('script[src*="arbeitszeitcheck/l10n"]')].length >= 0,
			azcL10nScripts: [...document.querySelectorAll('script[src*="arbeitszeitcheck/l10n"]')].map((el) => el.getAttribute('src')),
		}));
		expect(state.hasOc, 'window.OC must exist after dashboard boot').toBeTruthy();
	});
});
