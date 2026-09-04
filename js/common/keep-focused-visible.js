/**
 * Soft-keyboard / visualViewport: keep focused form fields (e.g. time-entry notes)
 * visible above the on-screen keyboard on phones and tablets.
 *
 * Hardening notes (auditor-facing):
 * - Pads a content host (form / main / dialog), not #app-content itself — NC flex shells
 *   ignore padding-bottom on the overflow scroller for scrollHeight.
 * - When the field lives in a body-mounted dialog, pads that dialog (never the page main).
 * - Subtracts sticky/fixed bottom chrome (GDPR banners, dialog footers) from the usable band.
 * - Tracks the padded host so focusout restores the correct element.
 * - Cancels pending reveal/clear timers to avoid focusin/focusout races and leaks.
 * - No user HTML; only style.paddingBottom + optional empty spacer cleanup.
 */
(function (root, factory) {
	'use strict';
	const api = factory();
	if (typeof module === 'object' && module.exports) {
		module.exports = api;
	}
	if (typeof root === 'object' && root !== null) {
		root.AZCKeepFocusedVisible = api;
	}
	if (typeof document !== 'undefined') {
		api.install(document, typeof window !== 'undefined' ? window : undefined);
	}
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
	'use strict';

	const FOCUSABLE =
		'input:not([type="hidden"]):not([disabled]), textarea:not([disabled]), select:not([disabled]), [contenteditable=""], [contenteditable="true"]';
	/** Inputs that never open a soft keyboard — never pad/scroll for these. */
	const NO_IME_INPUT_TYPES = {
		checkbox: true,
		radio: true,
		button: true,
		submit: true,
		reset: true,
		file: true,
		image: true,
		range: true,
		color: true,
		hidden: true,
		date: true,
		time: true,
		'datetime-local': true,
		month: true,
		week: true,
	};
	/** Layout vs visualViewport gap (px) that indicates an on-screen keyboard. */
	const KEYBOARD_SHRINK_PX = 120;
	const EDGE_PAD = 20;
	const RETRY_MS = [50, 200, 400];
	const SPACER_ID = 'azc-ime-scroll-spacer';
	const PAD_ATTR = 'data-azc-ime-pad';
	const INSTALL_ATTR = 'azcKeepFocusedVisible';

	const DIALOG_HOST_SEL = [
		// Prefer InventoryCheck hosts first (shared Check-family helper).
		'.iv-dialog',
		'[role="dialog"]',
		'.modal',
		'.oc-dialog',
		'.dc-dialog',
		'.bc-dialog',
		'.crm-dialog',
		'.mn-dialog',
		'.ic-dialog',
		'.tc-dialog',
		'.mc-dialog',
		'.azc-dialog',
		'.pc-dialog',
		'.dk-dialog',
		'.helpdesk-dialog',
		'.dialog',
	].join(', ');

	const DIALOG_INNER_SEL = [
		'.iv-dialog__body',
		'.modal-body',
		'.modal__content',
		'.dialog__content',
		'.oc-dialog-content',
		'.dc-dialog__body',
		'.dialog-body',
	].join(', ');

	/** Full-viewport fixed layers — never treat as sticky bottom chrome. */
	const OVERLAY_CHROME_SKIP_SEL = [
		'.modal-backdrop',
		'.iv-dialog-overlay',
		'.oc-dialog-dim',
		'[role="presentation"]',
	].join(', ');

	const FORM_HOST_SEL = [
		'.iv-main',
		'#iv-main-content',
		'.iv-form',
		'.time-entry-form',
		'.pc-main',
		'.azc-main',
		'.dc-main',
		'.bc-main',
		'.crm-main',
		'.ic-main',
		'.mn-main',
		'.mc-main',
		'.tc-main',
		'#pc-main-content',
		'#ic-main-content',
		'#dc-main-content',
		'#mn-main-content',
		'#mc-main-content',
		'#tc-main-content',
		'form.time-entry-form',
		'form[id$="-form"]',
		'form.form',
		'.section--form',
		'.bc-editor',
		'.crm-form',
		'.helpdesk-form',
		'.ticket-form',
		'.mn-form',
		'.mc-form',
		'.ic-form',
		'.projectcheck-admin',
	].join(', ');

	const PAGE_HOST_SELECTORS = [
		'.iv-main',
		'#iv-main-content',
		'.iv-form',
		'.time-entry-form',
		'.pc-main',
		'.azc-main',
		'.dc-main',
		'.bc-main',
		'.crm-main',
		'.ic-main',
		'.mn-main',
		'.mc-main',
		'.tc-main',
		'#pc-main-content',
		'#ic-main-content',
		'#dc-main-content',
		'#mn-main-content',
		'#mc-main-content',
		'#tc-main-content',
		'.section--form',
		'.bc-editor',
		'.crm-form',
		'.helpdesk-form',
		'.ticket-form',
		'.mn-form',
		'.mc-form',
		'.ic-form',
		'.projectcheck-admin',
		'form[id$="-form"]',
		'form.form',
		'#app-content .pc-main',
		'#app-content form',
		'#app-content main',
	];

	/** Sticky/fixed chrome that sits inside the visual viewport above the IME. */
	const BOTTOM_CHROME_SEL = [
		'.tc-banner',
		'.modal-footer',
		'.iv-dialog__actions',
		'.helpdesk-form-actions',
		'.sticky-actions',
		'.form-actions--sticky',
		'.admin-user-detail__actions',
		'.admin-user-detail .form-actions',
		'.form-actions',
		'[data-ime-chrome="bottom"]',
	].join(', ');

	/** @type {Element|null} */
	let paddedHost = null;
	/** @type {number} */
	let focusEpoch = 0;

	/**
	 * @param {Window|undefined|null} win
	 * @returns {{ top: number, bottom: number }}
	 */
	function visibleBand(win) {
		if (!win) {
			return { top: 0, bottom: 0 };
		}
		const vv = win.visualViewport;
		if (vv && typeof vv.height === 'number' && vv.height > 0) {
			const top = typeof vv.offsetTop === 'number' ? vv.offsetTop : 0;
			return { top: top, bottom: top + vv.height };
		}
		return { top: 0, bottom: win.innerHeight || 0 };
	}

	/**
	 * @param {Element} node
	 * @returns {CSSStyleDeclaration|null}
	 */
	function winGetComputed(node) {
		if (typeof window === 'undefined' || !window.getComputedStyle) {
			return null;
		}
		return window.getComputedStyle(node);
	}

	/**
	 * Collect sticky/fixed candidates: allowlisted chrome plus siblings along the
	 * focused field's ancestor chain (catches app-specific sticky save bars).
	 *
	 * @param {Document} doc
	 * @param {Element|null|undefined} nearEl
	 * @returns {Element[]}
	 */
	function chromeCandidates(doc, nearEl) {
		/** @type {Element[]} */
		const list = [];
		/** @type {Set<Element>} */
		const seen = typeof Set === 'function' ? new Set() : null;

		function add(node) {
			if (!node || (seen && seen.has(node))) {
				return;
			}
			if (seen) {
				seen.add(node);
			}
			list.push(node);
		}

		const known = doc.querySelectorAll(BOTTOM_CHROME_SEL);
		for (let i = 0; i < known.length; i++) {
			add(known[i]);
		}

		let walk = nearEl && nearEl.parentElement ? nearEl.parentElement : null;
		let depth = 0;
		while (walk && walk !== doc.documentElement && depth < 24) {
			add(walk);
			const kids = walk.children;
			if (kids) {
				for (let i = 0; i < kids.length; i++) {
					add(kids[i]);
				}
			}
			walk = walk.parentElement;
			depth += 1;
		}
		return list;
	}

	/**
	 * @param {Element} node
	 * @param {{ top: number, bottom: number }} band
	 * @returns {number}
	 */
	function insetFromNode(node, band) {
		const style = winGetComputed(node);
		if (!style) {
			return 0;
		}
		const pos = style.position;
		if (pos !== 'fixed' && pos !== 'sticky') {
			return 0;
		}
		if (style.display === 'none' || style.visibility === 'hidden') {
			return 0;
		}
		if (typeof node.matches === 'function' && node.matches(OVERLAY_CHROME_SKIP_SEL)) {
			return 0;
		}
		if (typeof node.getBoundingClientRect !== 'function') {
			return 0;
		}
		const rect = node.getBoundingClientRect();
		if (!(rect.height > 0)) {
			return 0;
		}
		const usable = band.bottom - band.top;
		// Full-screen fixed overlays / dialog shells are not bottom chrome.
		if (usable > 0 && rect.height > usable * 0.45) {
			return 0;
		}
		// Overlaps the lower edge of the visible band (above the soft keyboard).
		if (rect.top < band.bottom && rect.bottom > band.bottom - 4) {
			return band.bottom - rect.top;
		}
		if (rect.bottom <= band.bottom + 1 && rect.top >= band.top && rect.bottom > band.bottom - rect.height - 120) {
			return Math.min(rect.height, band.bottom - rect.top);
		}
		return 0;
	}

	/**
	 * Height of sticky/fixed bottom UI that eats the usable visualViewport band.
	 *
	 * @param {Document|undefined|null} doc
	 * @param {Window|undefined|null} win
	 * @param {Element|null|undefined} nearEl
	 * @returns {number}
	 */
	function bottomChromeInset(doc, win, nearEl) {
		if (!doc || typeof doc.querySelectorAll !== 'function' || !win) {
			return 0;
		}
		const band = visibleBand(win);
		const usable = band.bottom - band.top;
		if (usable <= 0) {
			return 0;
		}
		let inset = 0;
		const nodes = chromeCandidates(doc, nearEl);
		for (let i = 0; i < nodes.length; i++) {
			inset = Math.max(inset, insetFromNode(nodes[i], band));
		}
		// Never collapse the usable band below a readable strip.
		return Math.min(Math.max(0, Math.round(inset)), Math.max(0, usable - 80));
	}

	/**
	 * @param {Element|null|undefined} el
	 * @returns {Element[]}
	 */
	function scrollParents(el) {
		/** @type {Element[]} */
		const list = [];
		let node = el && el.parentElement;
		while (node && node !== document.documentElement) {
			const style = winGetComputed(node);
			const oy = style ? style.overflowY : '';
			const ox = style ? style.overflowX : '';
			const canY =
				/(auto|scroll|overlay)/.test(oy) && node.scrollHeight > node.clientHeight + 1;
			const canX =
				/(auto|scroll|overlay)/.test(ox) && node.scrollWidth > node.clientWidth + 1;
			if (canY || canX) {
				list.push(node);
			}
			if (node === document.body) {
				break;
			}
			node = node.parentElement;
		}
		['#app-content', '#content', '.app-content', '#app-content-wrapper'].forEach(function (sel) {
			const shell = document.querySelector(sel);
			if (shell && list.indexOf(shell) === -1 && shell.scrollHeight > shell.clientHeight + 1) {
				list.push(shell);
			}
		});
		return list;
	}

	/**
	 * Prefer dialog / form / main hosts whose padding-bottom actually creates scroll room.
	 * When `nearEl` is set, never pick a page host that does not contain the field
	 * (body-mounted dialogs must not pad `.pc-main` / `.dc-main` on the page behind).
	 *
	 * @param {Document} doc
	 * @param {Element|null|undefined} nearEl
	 * @returns {Element|null}
	 */
	function resolvePadHost(doc, nearEl) {
		if (!doc || typeof doc.querySelector !== 'function') {
			return null;
		}

		if (nearEl && typeof nearEl.closest === 'function') {
			const dialog = nearEl.closest(DIALOG_HOST_SEL);
			if (dialog) {
				const inner =
					typeof dialog.querySelector === 'function' ? dialog.querySelector(DIALOG_INNER_SEL) : null;
				if (inner && inner.style) {
					return inner;
				}
				if (dialog.style) {
					return dialog;
				}
			}
			const fromField = nearEl.closest(FORM_HOST_SEL);
			if (fromField && fromField.style) {
				return fromField;
			}
		}

		for (let i = 0; i < PAGE_HOST_SELECTORS.length; i++) {
			const hit = doc.querySelector(PAGE_HOST_SELECTORS[i]);
			if (!hit || !hit.style) {
				continue;
			}
			if (nearEl && typeof hit.contains === 'function' && !hit.contains(nearEl)) {
				continue;
			}
			return hit;
		}

		if (nearEl && nearEl.parentElement) {
			let walk = nearEl.parentElement;
			while (walk && walk !== doc.documentElement) {
				if (walk.style) {
					return walk;
				}
				walk = walk.parentElement;
			}
		}

		const fallback = doc.querySelector('#app-content') || doc.body;
		if (fallback && fallback.style && (!nearEl || !fallback.contains || fallback.contains(nearEl))) {
			return fallback;
		}
		return nearEl && nearEl.parentElement && nearEl.parentElement.style
			? nearEl.parentElement
			: fallback && fallback.style
				? fallback
				: null;
	}

	/**
	 * @param {Document} doc
	 * @param {number} height
	 * @param {Element|null|undefined} nearEl
	 */
	function ensureKeyboardScrollRoom(doc, height, nearEl) {
		if (!doc) {
			return;
		}
		const h = Math.max(0, Math.round(Number(height) || 0));
		if (h <= 0) {
			const host = paddedHost || resolvePadHost(doc, nearEl);
			if (host && host.getAttribute && host.getAttribute(PAD_ATTR) !== null) {
				const prev = host.getAttribute(PAD_ATTR);
				host.style.paddingBottom = prev || '';
				host.removeAttribute(PAD_ATTR);
			}
			paddedHost = null;
			const spacer = typeof doc.getElementById === 'function' ? doc.getElementById(SPACER_ID) : null;
			if (spacer && spacer.parentNode) {
				spacer.parentNode.removeChild(spacer);
			}
			return;
		}
		const host = resolvePadHost(doc, nearEl);
		if (!host || !host.style) {
			return;
		}
		// Switching hosts: restore the previous one first.
		if (paddedHost && paddedHost !== host && paddedHost.getAttribute) {
			if (paddedHost.getAttribute(PAD_ATTR) !== null) {
				paddedHost.style.paddingBottom = paddedHost.getAttribute(PAD_ATTR) || '';
				paddedHost.removeAttribute(PAD_ATTR);
			}
		}
		if (!host.getAttribute(PAD_ATTR)) {
			host.setAttribute(PAD_ATTR, host.style.paddingBottom || '');
		}
		host.style.paddingBottom = h + 'px';
		paddedHost = host;
	}

	/**
	 * @param {Element|null|undefined} el
	 * @returns {boolean}
	 */
	function needsImeReveal(el) {
		if (!(el instanceof Element) || !el.matches(FOCUSABLE)) {
			return false;
		}
		if (typeof HTMLButtonElement !== 'undefined' && el instanceof HTMLButtonElement) {
			return false;
		}
		if (typeof HTMLSelectElement !== 'undefined' && el instanceof HTMLSelectElement) {
			return false;
		}
		if (typeof HTMLInputElement !== 'undefined' && el instanceof HTMLInputElement) {
			const type = String(el.type || 'text').toLowerCase();
			if (NO_IME_INPUT_TYPES[type]) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @param {Window|undefined|null} win
	 * @returns {boolean}
	 */
	function softKeyboardLikelyOpen(win) {
		if (!win || !win.visualViewport || typeof win.innerHeight !== 'number') {
			return false;
		}
		return win.visualViewport.height < win.innerHeight - KEYBOARD_SHRINK_PX;
	}

	/**
	 * Desktop mouse focus must never grow dialogs with fake IME padding.
	 * Reveal only when the soft keyboard has already shrunk the visual viewport.
	 *
	 * @param {Element|null|undefined} el
	 * @param {Window|undefined|null} win
	 * @returns {boolean}
	 */
	function shouldAutoReveal(el, win) {
		return needsImeReveal(el) && softKeyboardLikelyOpen(win);
	}

	/**
	 * @param {Element|null|undefined} el
	 * @param {Window|undefined|null} win
	 * @returns {{ moved: boolean, delta: number }}
	 */
	function ensureFocusedVisible(el, win) {
		if (!el || typeof el.getBoundingClientRect !== 'function') {
			return { moved: false, delta: 0 };
		}
		if (!shouldAutoReveal(el, win)) {
			return { moved: false, delta: 0 };
		}
		const band = visibleBand(win);
		if (band.bottom <= band.top) {
			return { moved: false, delta: 0 };
		}

		const chrome =
			typeof document !== 'undefined' ? bottomChromeInset(document, win, el) : 0;
		const usableBottom = band.bottom - chrome;
		const layoutH = win && win.innerHeight ? win.innerHeight : band.bottom;
		const obscured = Math.max(0, layoutH - (band.bottom - band.top));
		const padNeed = Math.max(obscured, chrome);
		if (typeof document !== 'undefined') {
			ensureKeyboardScrollRoom(document, padNeed > 0 ? padNeed + EDGE_PAD : 0, el);
		}

		function coverageDelta() {
			const rect = el.getBoundingClientRect();
			if (rect.bottom > usableBottom - EDGE_PAD) {
				return rect.bottom - (usableBottom - EDGE_PAD);
			}
			if (rect.top < band.top + EDGE_PAD) {
				return rect.top - (band.top + EDGE_PAD);
			}
			return 0;
		}

		let delta = coverageDelta();
		if (delta === 0) {
			return { moved: false, delta: 0 };
		}

		const parents = scrollParents(el);
		const primary =
			parents.find(function (p) {
				return (
					p.id === 'app-content' ||
					p.id === 'app-content-wrapper' ||
					(typeof p.matches === 'function' &&
						p.matches(DIALOG_HOST_SEL + ', ' + DIALOG_INNER_SEL))
				);
			}) || parents[0];

		if (primary) {
			primary.scrollTop += delta;
		}
		// Never use block:'center' — it yanks long lists/dialogs. Nearest is enough.
		if (typeof el.scrollIntoView === 'function') {
			el.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'auto' });
		}

		delta = coverageDelta();
		if (delta !== 0) {
			parents.forEach(function (parent) {
				parent.scrollTop += delta;
			});
			if (win && typeof win.scrollBy === 'function') {
				win.scrollBy(0, delta);
			}
			if (typeof document !== 'undefined' && document.scrollingElement) {
				document.scrollingElement.scrollTop += delta;
			}
			delta = coverageDelta();
			if (delta !== 0 && typeof el.scrollIntoView === 'function') {
				el.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'auto' });
			}
		}

		return { moved: true, delta: coverageDelta() };
	}

	/**
	 * @param {Document} doc
	 * @param {Window|undefined} win
	 * @returns {{ destroy: () => void }}
	 */
	function install(doc, win) {
		if (!doc || !doc.documentElement) {
			return { destroy: function () {} };
		}
		if (doc.documentElement.dataset[INSTALL_ATTR] === '1') {
			return { destroy: function () {} };
		}
		doc.documentElement.dataset[INSTALL_ATTR] = '1';

		/** @type {ReturnType<typeof setTimeout>[]} */
		const timers = [];

		function clearTimers() {
			while (timers.length) {
				const id = timers.pop();
				clearTimeout(id);
			}
		}

		function revealActive() {
			const active = doc.activeElement;
			if (!(active instanceof Element) || !shouldAutoReveal(active, win)) {
				return;
			}
			ensureFocusedVisible(active, win);
		}

		function scheduleReveal() {
			clearTimers();
			if (!softKeyboardLikelyOpen(win)) {
				// Keyboard dismissed / desktop: never leave sticky IME padding behind.
				ensureKeyboardScrollRoom(doc, 0, null);
				return;
			}
			const epoch = ++focusEpoch;
			revealActive();
			RETRY_MS.forEach(function (ms) {
				const schedule = win && win.setTimeout ? win.setTimeout.bind(win) : setTimeout;
				timers.push(
					schedule(function () {
						if (epoch !== focusEpoch) {
							return;
						}
						revealActive();
					}, ms),
				);
			});
		}

		/**
		 * @param {FocusEvent} event
		 */
		function onFocusIn(event) {
			const target = event.target;
			if (!(target instanceof Element) || !needsImeReveal(target)) {
				return;
			}
			// Typed field focused: wait for keyboard resize before scrolling.
			focusEpoch += 1;
			if (softKeyboardLikelyOpen(win)) {
				scheduleReveal();
			}
		}

		function onFocusOut() {
			clearTimers();
			const epoch = ++focusEpoch;
			const schedule = win && win.setTimeout ? win.setTimeout.bind(win) : setTimeout;
			timers.push(
				schedule(function () {
					if (epoch !== focusEpoch) {
						return;
					}
					const active = doc.activeElement;
					if (!(active instanceof Element) || !active.matches(FOCUSABLE)) {
						ensureKeyboardScrollRoom(doc, 0, null);
					}
				}, 80),
			);
		}

		doc.addEventListener('focusin', onFocusIn, true);
		doc.addEventListener('focusout', onFocusOut, true);

		/** @type {{ removeEventListener: Function }|null} */
		let vv = win && win.visualViewport ? win.visualViewport : null;
		if (vv) {
			// Resize = keyboard open/close. Scroll alone must not re-reveal (feedback loop).
			vv.addEventListener('resize', scheduleReveal);
		}

		return {
			destroy: function () {
				clearTimers();
				doc.removeEventListener('focusin', onFocusIn, true);
				doc.removeEventListener('focusout', onFocusOut, true);
				if (vv) {
					vv.removeEventListener('resize', scheduleReveal);
				}
				ensureKeyboardScrollRoom(doc, 0, null);
				delete doc.documentElement.dataset[INSTALL_ATTR];
				vv = null;
			},
		};
	}

	return {
		EDGE_PAD,
		KEYBOARD_SHRINK_PX,
		FOCUSABLE,
		PAD_ATTR,
		needsImeReveal,
		softKeyboardLikelyOpen,
		shouldAutoReveal,
		visibleBand,
		bottomChromeInset,
		resolvePadHost,
		ensureKeyboardScrollRoom,
		ensureFocusedVisible,
		install,
		/** @internal test hook */
		_resetPadHostForTests: function () {
			paddedHost = null;
			focusEpoch = 0;
		},
	};
});
