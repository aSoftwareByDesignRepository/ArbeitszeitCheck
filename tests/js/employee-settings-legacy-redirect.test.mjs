/**
 * Fail-closed legacy redirect for employee My settings multipage split.
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */
import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it, beforeEach, vi } from 'vitest'

const root = join(dirname(fileURLToPath(import.meta.url)), '../..')
const jsPath = join(root, 'js/employee-settings-legacy-redirect.js')

function loadApi() {
	const code = readFileSync(jsPath, 'utf8')
	const sandbox = { window: globalThis }
	// eslint-disable-next-line no-new-func
	const run = new Function('window', code + '\n;return window.ArbeitszeitCheck.EmployeeSettingsLegacyRedirect;')
	return run(sandbox.window)
}

describe('employee-settings-legacy-redirect', () => {
	beforeEach(() => {
		vi.restoreAllMocks()
		delete globalThis.ArbeitszeitCheck
	})

	it('exports resolve and mirrors fail-closed rules', () => {
		const api = loadApi()
		expect(api).toBeTruthy()
		expect(typeof api.resolve).toBe('function')
	})

	it('returns null without payload or hash', () => {
		const { resolve } = loadApi()
		expect(resolve(null, 'settings-notifications-heading')).toBeNull()
		expect(resolve({ legacyAnchors: {}, urls: {} }, '')).toBeNull()
		expect(resolve({
			legacyAnchors: { 'settings-notifications-heading': 'notifications' },
			urls: {},
		}, 'settings-notifications-heading')).toBeNull()
	})

	it('returns null when already on the owning section', () => {
		const { resolve } = loadApi()
		const payload = {
			current: 'notifications',
			legacyAnchors: { 'settings-notifications-heading': 'notifications' },
			urls: { notifications: '/apps/arbeitszeitcheck/settings/notifications' },
		}
		expect(resolve(payload, 'settings-notifications-heading')).toBeNull()
	})

	it('forwards to catalog URL + fragment', () => {
		const { resolve } = loadApi()
		const payload = {
			current: 'breaks',
			legacyAnchors: { 'settings-notifications-heading': 'notifications' },
			urls: {
				breaks: '/apps/arbeitszeitcheck/settings/breaks',
				notifications: '/apps/arbeitszeitcheck/settings/notifications',
			},
		}
		expect(resolve(payload, '#settings-notifications-heading')).toBe(
			'/apps/arbeitszeitcheck/settings/notifications#settings-notifications-heading',
		)
	})

	it('rejects prototype-polluted keys and unknown anchors', () => {
		const { resolve } = loadApi()
		const payload = {
			current: 'breaks',
			legacyAnchors: Object.create({ polluted: 'notifications' }),
			urls: { notifications: '/apps/arbeitszeitcheck/settings/notifications' },
		}
		expect(resolve(payload, 'polluted')).toBeNull()
		expect(resolve(payload, 'constructor')).toBeNull()
		expect(resolve({
			current: 'breaks',
			legacyAnchors: { 'settings-notifications-heading': 'notifications' },
			urls: { notifications: '/apps/arbeitszeitcheck/settings/notifications' },
		}, 'settings-bogus-heading')).toBeNull()
	})
})
