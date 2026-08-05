/**
 * Fail-closed legacy redirect for admin policy IA split.
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */
import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it, beforeEach, vi } from 'vitest'

const root = join(dirname(fileURLToPath(import.meta.url)), '../..')
const jsPath = join(root, 'js/admin-policy-legacy-redirect.js')

function loadApi() {
	const code = readFileSync(jsPath, 'utf8')
	const sandbox = { window: globalThis }
	// eslint-disable-next-line no-new-func
	const run = new Function('window', code + '\n;return window.ArbeitszeitCheck.AdminPolicyLegacyRedirect;')
	return run(sandbox.window)
}

describe('admin-policy-legacy-redirect', () => {
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
		expect(resolve(null, 'overtime-bank-heading')).toBeNull()
		expect(resolve({ legacyAnchors: {}, urls: {} }, '')).toBeNull()
		expect(resolve({ legacyAnchors: { 'overtime-bank-heading': 'overtime' }, urls: {} }, 'overtime-bank-heading')).toBeNull()
	})

	it('returns null when already on the owning section', () => {
		const { resolve } = loadApi()
		const payload = {
			current: 'overtime',
			legacyAnchors: { 'overtime-bank-heading': 'overtime' },
			urls: { overtime: '/apps/arbeitszeitcheck/admin/overtime-settings' },
		}
		expect(resolve(payload, 'overtime-bank-heading')).toBeNull()
	})

	it('forwards to catalog URL + fragment', () => {
		const { resolve } = loadApi()
		const payload = {
			current: 'notifications',
			legacyAnchors: { 'overtime-bank-heading': 'overtime' },
			urls: {
				notifications: '/apps/arbeitszeitcheck/admin/notifications',
				overtime: '/apps/arbeitszeitcheck/admin/overtime-settings',
			},
		}
		expect(resolve(payload, '#overtime-bank-heading')).toBe(
			'/apps/arbeitszeitcheck/admin/overtime-settings#overtime-bank-heading',
		)
	})

	it('rejects prototype-polluted keys and unknown anchors', () => {
		const { resolve } = loadApi()
		const payload = {
			current: 'notifications',
			legacyAnchors: Object.create({ polluted: 'overtime' }),
			urls: { overtime: '/apps/arbeitszeitcheck/admin/overtime-settings' },
		}
		expect(resolve(payload, 'polluted')).toBeNull()
		expect(resolve(payload, 'constructor')).toBeNull()
		expect(resolve({
			current: 'notifications',
			legacyAnchors: { 'overtime-bank-heading': 'overtime' },
			urls: { overtime: '/apps/arbeitszeitcheck/admin/overtime-settings' },
		}, 'vacation-unit-heading')).toBeNull()
	})

	it('rejects empty or hash-only catalog URLs', () => {
		const { resolve } = loadApi()
		expect(resolve({
			current: 'notifications',
			legacyAnchors: { 'overtime-bank-heading': 'overtime' },
			urls: { overtime: '#' },
		}, 'overtime-bank-heading')).toBeNull()
	})
})
