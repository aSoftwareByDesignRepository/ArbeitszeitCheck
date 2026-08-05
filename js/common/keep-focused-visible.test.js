/**
 * @vitest-environment jsdom
 */

import { beforeEach, describe, expect, it, vi } from 'vitest';

describe('keep-focused-visible IME gating', () => {
	/** @type {typeof import('./keep-focused-visible.js')} */
	let api;

	beforeEach(async () => {
		vi.resetModules();
		document.documentElement.removeAttribute('data-azc-keep-focused-visible');
		delete document.documentElement.dataset.azcKeepFocusedVisible;
		document.body.innerHTML = '';
		api = await import('./keep-focused-visible.js');
		if (api._resetPadHostForTests) {
			api._resetPadHostForTests();
		}
	});

	it('needsImeReveal is false for checkbox, radio, and select', () => {
		const checkbox = document.createElement('input');
		checkbox.type = 'checkbox';
		document.body.appendChild(checkbox);
		const radio = document.createElement('input');
		radio.type = 'radio';
		document.body.appendChild(radio);
		const select = document.createElement('select');
		select.appendChild(document.createElement('option'));
		document.body.appendChild(select);
		expect(api.needsImeReveal(checkbox)).toBe(false);
		expect(api.needsImeReveal(radio)).toBe(false);
		expect(api.needsImeReveal(select)).toBe(false);
	});

	it('needsImeReveal is false for date and time pickers', () => {
		const date = document.createElement('input');
		date.type = 'date';
		document.body.appendChild(date);
		const time = document.createElement('input');
		time.type = 'time';
		document.body.appendChild(time);
		expect(api.needsImeReveal(date)).toBe(false);
		expect(api.needsImeReveal(time)).toBe(false);
	});

	it('needsImeReveal is true for text and textarea', () => {
		const text = document.createElement('input');
		text.type = 'text';
		document.body.appendChild(text);
		const area = document.createElement('textarea');
		document.body.appendChild(area);
		expect(api.needsImeReveal(text)).toBe(true);
		expect(api.needsImeReveal(area)).toBe(true);
	});

	it('shouldAutoReveal is false on desktop without soft keyboard even for text', () => {
		const text = document.createElement('input');
		text.type = 'text';
		document.body.appendChild(text);
		Object.defineProperty(window, 'innerHeight', { configurable: true, value: 900 });
		Object.defineProperty(window, 'visualViewport', {
			configurable: true,
			value: { height: 900, width: 1400, offsetTop: 0, addEventListener() {}, removeEventListener() {} },
		});
		expect(api.softKeyboardLikelyOpen(window)).toBe(false);
		expect(api.shouldAutoReveal(text, window)).toBe(false);
		expect(api.ensureFocusedVisible(text, window).moved).toBe(false);
	});

	it('shouldAutoReveal is true for text when soft keyboard shrinks the viewport', () => {
		const text = document.createElement('input');
		text.type = 'text';
		document.body.appendChild(text);
		Object.defineProperty(window, 'innerHeight', { configurable: true, value: 900 });
		Object.defineProperty(window, 'visualViewport', {
			configurable: true,
			value: { height: 500, width: 400, offsetTop: 0, addEventListener() {}, removeEventListener() {} },
		});
		expect(api.softKeyboardLikelyOpen(window)).toBe(true);
		expect(api.shouldAutoReveal(text, window)).toBe(true);
	});

	it('ensureFocusedVisible does not scrollIntoView for selects', () => {
		const select = document.createElement('select');
		select.appendChild(document.createElement('option'));
		document.body.appendChild(select);
		const scrollIntoView = vi.fn();
		select.scrollIntoView = scrollIntoView;
		const result = api.ensureFocusedVisible(select, window);
		expect(result.moved).toBe(false);
		expect(scrollIntoView).not.toHaveBeenCalled();
	});

	it('focusin on select does not schedule scrollIntoView', () => {
		const select = document.createElement('select');
		select.appendChild(document.createElement('option'));
		document.body.appendChild(select);
		const scrollIntoView = vi.fn();
		select.scrollIntoView = scrollIntoView;
		api.install(document, window);
		select.focus();
		select.dispatchEvent(new FocusEvent('focusin', { bubbles: true }));
		expect(scrollIntoView).not.toHaveBeenCalled();
	});

	it('ensureFocusedVisible does not scrollIntoView for checkboxes', () => {
		const checkbox = document.createElement('input');
		checkbox.type = 'checkbox';
		document.body.appendChild(checkbox);
		const scrollIntoView = vi.fn();
		checkbox.scrollIntoView = scrollIntoView;
		const result = api.ensureFocusedVisible(checkbox, window);
		expect(result.moved).toBe(false);
		expect(scrollIntoView).not.toHaveBeenCalled();
	});
});
