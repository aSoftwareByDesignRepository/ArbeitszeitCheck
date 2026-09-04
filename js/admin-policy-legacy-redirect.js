/**
 * Forward legacy /admin/notifications#… kitchen-sink bookmarks to the owning
 * policy page (AdminPolicyPagesCatalog::LEGACY_ANCHORS via server payload).
 *
 * Security: target URLs come only from the server-rendered catalog payload;
 * hash text never becomes a URL by itself. Fail-closed on missing/malformed data.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */
(function (root) {
	'use strict';

	/**
	 * @param {object|null|undefined} payload window.ArbeitszeitCheck.adminPolicyPages
	 * @param {string} hash location.hash without leading #
	 * @returns {string|null} absolute path (+ fragment) to forward to, or null
	 */
	function resolve(payload, hash) {
		if (!payload || typeof payload !== 'object') {
			return null;
		}
		const anchors = payload.legacyAnchors;
		const urls = payload.urls;
		if (!anchors || typeof anchors !== 'object' || !urls || typeof urls !== 'object') {
			return null;
		}
		const clean = String(hash || '').replace(/^#/, '');
		if (clean === '' || !Object.prototype.hasOwnProperty.call(anchors, clean)) {
			return null;
		}
		const targetSection = anchors[clean];
		if (typeof targetSection !== 'string' || targetSection === '') {
			return null;
		}
		const current = String(payload.current || '');
		if (current === targetSection) {
			// Same page — leave native scroll-to-id behaviour.
			return null;
		}
		if (!Object.prototype.hasOwnProperty.call(urls, targetSection)) {
			return null;
		}
		const base = urls[targetSection];
		if (typeof base !== 'string' || base === '' || base === '#') {
			return null;
		}
		// Fail-closed: base must equal a known catalog URL value.
		const known = Object.keys(urls).some(function (k) {
			return Object.prototype.hasOwnProperty.call(urls, k) && urls[k] === base;
		});
		if (!known) {
			return null;
		}
		return base + '#' + clean;
	}

	function run() {
		const payload = root && root.ArbeitszeitCheck && root.ArbeitszeitCheck.adminPolicyPages;
		const hash = (root && root.location && root.location.hash) || '';
		const target = resolve(payload, hash);
		if (!target || !root || !root.location || typeof root.location.replace !== 'function') {
			return;
		}
		root.location.replace(target);
	}

	const api = Object.freeze({ resolve: resolve });
	if (root) {
		root.ArbeitszeitCheck = root.ArbeitszeitCheck || {};
		root.ArbeitszeitCheck.AdminPolicyLegacyRedirect = api;
		if (root.document && root.document.readyState === 'loading') {
			root.document.addEventListener('DOMContentLoaded', run);
		} else {
			run();
		}
	}
})(typeof window !== 'undefined' ? window : null);
