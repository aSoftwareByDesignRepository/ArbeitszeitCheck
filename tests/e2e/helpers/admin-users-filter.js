/**
 * Employee list access filter helpers for E2E (dev/CI only).
 */

/** @type {number} */
const FILTER_API_TIMEOUT_MS = 120_000;

/**
 * @param {import('@playwright/test').Page} page
 * @param {string} selector
 * @param {'app_access' | 'all'} filter
 */
async function waitForFilterResponse(page, selector, filter) {
	const responsePromise = page.waitForResponse(
		(res) =>
			res.url().includes('/api/admin/users')
			&& res.url().includes(`filter=${filter}`)
			&& res.request().method() === 'GET',
		{ timeout: FILTER_API_TIMEOUT_MS },
	);
	await page.locator(`label:has(${selector})`).click();
	await responsePromise;
}

/**
 * Activate a filter, no-op when it is already selected.
 *
 * @param {import('@playwright/test').Page} page
 * @param {'app_access' | 'all'} filter
 */
export async function selectEmployeeListAccessFilter(page, filter) {
	const targetId = filter === 'app_access' ? '#employee-list-filter-app-access' : '#employee-list-filter-all';
	const target = page.locator(targetId);
	if (await target.isChecked()) {
		return;
	}
	await waitForFilterResponse(page, targetId, filter);
}

/**
 * Force a filter change even when the target is already active (toggle away, then back).
 *
 * @param {import('@playwright/test').Page} page
 * @param {'app_access' | 'all'} filter
 */
export async function switchEmployeeListAccessFilter(page, filter) {
	const targetId = filter === 'app_access' ? '#employee-list-filter-app-access' : '#employee-list-filter-all';
	const otherId = filter === 'app_access' ? '#employee-list-filter-all' : '#employee-list-filter-app-access';
	const target = page.locator(targetId);

	if (await target.isChecked()) {
		const otherFilter = filter === 'app_access' ? 'all' : 'app_access';
		await waitForFilterResponse(page, otherId, otherFilter);
	}

	await waitForFilterResponse(page, targetId, filter);
}
