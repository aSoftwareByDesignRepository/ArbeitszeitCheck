import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

/**
 * Guard: toast must never render raw HTML/proxy error pages (giant "400").
 */

describe('ArbeitszeitCheckComponents showToast HTML safety', () => {
	beforeEach(async () => {
		document.body.innerHTML = '<div id="toast-container"></div>'
		vi.resetModules()
		delete globalThis.window.ArbeitszeitCheckComponents
		delete globalThis.window.AzcComponents
		await import('./components.js')
	})

	afterEach(() => {
		document.body.innerHTML = ''
		vi.restoreAllMocks()
	})

	it('escapes HTML error bodies so NC guest pages do not render inside the toast', () => {
		const Components = window.ArbeitszeitCheckComponents
		const html = '<!DOCTYPE html><html><body class="body-login"><div class="empty-content"><h2>400</h2></div></body></html>'
		Components.showToast({ type: 'error', message: html, duration: 0 })

		const toast = document.querySelector('.toast')
		expect(toast).toBeTruthy()
		expect(toast.querySelector('.empty-content')).toBeNull()
		expect(toast.querySelector('h2')).toBeNull()
		const msg = toast.querySelector('.toast-message')
		expect(msg).toBeTruthy()
		expect(msg.textContent).toContain('<!DOCTYPE html>')
		expect(msg.innerHTML).not.toContain('<div class="empty-content"')
		expect(msg.innerHTML).toContain('&lt;!DOCTYPE html&gt;')
	})

	it('escapes a bare status code string without creating special markup', () => {
		const Components = window.ArbeitszeitCheckComponents
		Components.showToast({ type: 'error', message: '400', title: '<b>Boom</b>', duration: 0 })
		const msg = document.querySelector('.toast-message')
		const title = document.querySelector('.toast-title')
		expect(msg.textContent).toBe('400')
		expect(title.textContent).toBe('<b>Boom</b>')
		expect(title.querySelector('b')).toBeNull()
	})
})
