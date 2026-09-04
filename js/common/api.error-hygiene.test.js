import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

/**
 * Mutation guard: opaque proxy/HTML 400 bodies must never surface as raw HTML
 * or a bare "400" to end users (Tobias Kolberg / Kolberg Consulting report).
 */

describe('AzcApi mapApiError transport hygiene', () => {
	beforeEach(async () => {
		vi.resetModules()
		delete globalThis.window.AzcApi
		globalThis.window.OC = { requestToken: 'tok', L10N: {} }
		globalThis.window.t = (_app, msgid) => msgid
		await import('./api.js')
	})

	afterEach(() => {
		vi.restoreAllMocks()
		delete globalThis.window.AzcApi
		delete globalThis.window.t
	})

	it('flags HTML, bare status codes, and guest-box noise as unsafe', () => {
		const api = window.AzcApi
		expect(api.isUnsafeApiErrorText('400')).toBe(true)
		expect(api.isUnsafeApiErrorText('HTTP 400 Bad Request')).toBe(true)
		expect(api.isUnsafeApiErrorText('<html><body class="guest-box">400</body></html>')).toBe(true)
		expect(api.isUnsafeApiErrorText('')).toBe(true)
		expect(api.isUnsafeApiErrorText('You cannot clock in on the selected project.')).toBe(false)
	})

	it('maps HTML 400 body to localized HTTP transport fallback', () => {
		const api = window.AzcApi
		const html = '<html><body class="body-login"><div class="empty-content">400</div></body></html>'
		const msg = api.mapApiError(html, 400)
		expect(msg).toContain('HTTP 400')
		expect(msg).not.toContain('<html')
		expect(msg).not.toBe('400')
	})

	it('still returns real business-rule JSON messages', () => {
		const api = window.AzcApi
		const msg = api.mapApiError({
			success: false,
			error: 'User is already clocked in',
			error_code: 'already_clocked_in',
		}, 400)
		expect(msg).toBe('User is already clocked in')
	})
})
