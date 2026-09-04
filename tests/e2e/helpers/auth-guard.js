/**
 * Nextcloud readiness checks for E2E (dev/CI only — not customer configuration).
 */
import { test } from '@playwright/test'

/**
 * Skip when Nextcloud is not ready for UI assertions (upgrade pending, maintenance, etc.).
 */
export async function assertNcReady(page) {
	const upgradeHeading = page.getByRole('heading', {
		name: /app update required|app-aktualisierung erforderlich|update needed|aktualisierung (erforderlich|benötigt)/i,
	})
	if (await upgradeHeading.isVisible({ timeout: 2000 }).catch(() => false)) {
		test.skip(
			true,
			'Nextcloud requires occ upgrade. Run: cd nextcloud && docker compose exec -u www-data nextcloud php occ upgrade',
		)
	}

	const webUpgradeRisk = page.getByRole('button', {
		name: /upgrade via web|über die weboberfläche aktualisieren/i,
	})
	if (await webUpgradeRisk.isVisible({ timeout: 1000 }).catch(() => false)) {
		test.skip(
			true,
			'Nextcloud shows web-upgrade interstitial. Run: cd nextcloud && docker compose exec -u www-data nextcloud php occ upgrade',
		)
	}

	const maintenance = page.getByText(/maintenance mode|wartungsmodus/i)
	if (await maintenance.isVisible({ timeout: 1000 }).catch(() => false)) {
		test.skip(true, 'Nextcloud is in maintenance mode.')
	}
}

/**
 * Navigate to an app route and skip when the instance is not ready.
 */
export async function gotoApp(page, url) {
	await page.goto(url, { waitUntil: 'domcontentloaded' })
	await assertNcReady(page)
}
