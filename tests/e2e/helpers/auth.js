import { test } from '@playwright/test'
import { assertNcReady, gotoApp } from './auth-guard.js'

export { assertNcReady, gotoApp } from './auth-guard.js'

export function hasCreds(role) {
	const u = process.env[`NC_${role}_USER`]
	const p = process.env[`NC_${role}_PASS`]
	return Boolean(u && p)
}

export function skipIfMissingCreds(role) {
	if (!hasCreds(role)) {
		test.skip(
			true,
			`Missing NC_${role}_USER / NC_${role}_PASS. Copy tests/e2e/.env.example to tests/e2e/.env (dev/CI only).`,
		)
	}
}

export async function login(page, { username, password }) {
	await page.goto('/login')

	const upgradeHeading = page.getByRole('heading', {
		name: /app update required|update needed|aktualisierung (erforderlich|benötigt)/i,
	})
	if (await upgradeHeading.isVisible({ timeout: 1500 }).catch(() => false)) {
		throw new Error(
			'Nextcloud shows Update needed on /login. From nextcloud/: docker compose exec -u www-data nextcloud php occ upgrade',
		)
	}

	const userInput = page
		.getByRole('textbox', { name: /account name|email|benutzername|e-mail/i })
		.or(page.locator('#user'))
		.or(page.locator('input[name="user"]'))
		.first()
	const passInput = page
		.getByRole('textbox', { name: /^password$|^passwort$/i })
		.or(page.locator('#password'))
		.or(page.locator('input[name="password"]'))
		.first()

	await userInput.waitFor({ state: 'visible', timeout: 20_000 })
	await userInput.fill(username)
	await passInput.fill(password)

	const submit = page
		.getByRole('button', { name: /^log in$|^anmelden$/i })
		.or(page.locator('button[type="submit"]'))
		.first()
	await submit.click()

	await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 30000 })
	await page.waitForLoadState('networkidle')
	await assertNcReady(page)
}

export async function loginAs(page, role) {
	skipIfMissingCreds(role)
	await login(page, credsFromEnv(role))
}

export function credsFromEnv(role) {
	const u = process.env[`NC_${role}_USER`]
	const p = process.env[`NC_${role}_PASS`]
	if (!u || !p) {
		throw new Error(
			`Missing NC_${role}_USER / NC_${role}_PASS (set in tests/e2e/.env for local E2E)`,
		)
	}
	return { username: u, password: p }
}
