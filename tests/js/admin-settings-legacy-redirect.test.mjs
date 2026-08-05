/**
 * Fail-closed legacy redirect for admin settings multipage split.
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */
import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it, beforeEach, vi } from 'vitest'

const root = join(dirname(fileURLToPath(import.meta.url)), '../..')
const jsPath = join(root, 'js/admin-settings-legacy-redirect.js')

function loadApi() {
	const code = readFileSync(jsPath, 'utf8')
	const sandbox = { window: globalThis }
	// eslint-disable-next-line no-new-func
	const run = new Function('window', code + '\n;return window.ArbeitszeitCheck.AdminSettingsLegacyRedirect;')
	return run(sandbox.window)
}

describe('admin-settings-legacy-redirect', () => {
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
		expect(resolve(null, 'section-hours-heading')).toBeNull()
		expect(resolve({ legacyAnchors: {}, urls: {} }, '')).toBeNull()
		expect(resolve({ legacyAnchors: { 'section-hours-heading': 'hours' }, urls: {} }, 'section-hours-heading')).toBeNull()
	})

	it('returns null when already on the owning section', () => {
		const { resolve } = loadApi()
		const payload = {
			current: 'hours',
			legacyAnchors: { 'section-hours-heading': 'hours' },
			urls: { hours: '/apps/arbeitszeitcheck/admin/settings/hours' },
		}
		expect(resolve(payload, 'section-hours-heading')).toBeNull()
	})

	it('forwards to catalog URL + fragment', () => {
		const { resolve } = loadApi()
		const payload = {
			current: 'access',
			legacyAnchors: { 'section-hours-heading': 'hours' },
			urls: {
				access: '/apps/arbeitszeitcheck/admin/settings/access',
				hours: '/apps/arbeitszeitcheck/admin/settings/hours',
			},
		}
		expect(resolve(payload, '#section-hours-heading')).toBe(
			'/apps/arbeitszeitcheck/admin/settings/hours#section-hours-heading',
		)
	})

	it('rejects prototype-polluted keys and unknown anchors', () => {
		const { resolve } = loadApi()
		const payload = {
			current: 'access',
			legacyAnchors: Object.create({ polluted: 'hours' }),
			urls: { hours: '/apps/arbeitszeitcheck/admin/settings/hours' },
		}
		expect(resolve(payload, 'polluted')).toBeNull()
		expect(resolve(payload, 'constructor')).toBeNull()
		expect(resolve({
			current: 'access',
			legacyAnchors: { 'section-hours-heading': 'hours' },
			urls: { hours: '/apps/arbeitszeitcheck/admin/settings/hours' },
		}, 'section-bogus-heading')).toBeNull()
	})
})
