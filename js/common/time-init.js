/**
 * Applies timezone bootstrap from Nextcloud InitialState.
 *
 * Loaded via Util::addScript() by {@see TimeClientBootstrap} (never
 * addInitScript — that would preload l10n/*.js before window.OC on the Vue
 * home dashboard). Ensures `window.ArbeitszeitCheck.tz` / `serverNow` exist
 * before `common/time.js` on app pages and desklets that skip navigation.php.
 *
 * @copyright Copyright (c) 2026 Alexander Mäule <info@software-by-design.de>
 * @license AGPL-3.0-or-later
 */
(function (root) {
	'use strict';

	/**
	 * @param {string} appId
	 * @param {string} key
	 * @returns {object|null}
	 */
	function loadAppInitialState(appId, key) {
		try {
			if (root.OCP?.InitialState && typeof root.OCP.InitialState.loadState === 'function') {
				return root.OCP.InitialState.loadState(appId, key);
			}
			if (root.OC?.initialState && typeof root.OC.initialState.loadState === 'function') {
				return root.OC.initialState.loadState(appId, key);
			}
		} catch (_) {
			// Missing state — inline bootstrap in time-bootstrap.php covers this.
		}
		return null;
	}

	function applyBootstrap(cfg) {
		if (!cfg || typeof cfg !== 'object') {
			return;
		}
		root.ArbeitszeitCheck = root.ArbeitszeitCheck || {};
		if (cfg.tz && typeof cfg.tz === 'object') {
			root.ArbeitszeitCheck.tz = Object.assign({}, root.ArbeitszeitCheck.tz || {}, cfg.tz);
		}
		if (typeof cfg.serverNow === 'string' && cfg.serverNow !== '') {
			root.ArbeitszeitCheck.serverNow = cfg.serverNow;
		}
		if (root.ArbeitszeitCheckTime
			&& typeof root.ArbeitszeitCheckTime.syncFromServer === 'function'
			&& root.ArbeitszeitCheck.serverNow) {
			root.ArbeitszeitCheckTime.syncFromServer(root.ArbeitszeitCheck.serverNow);
		}
	}

	applyBootstrap(loadAppInitialState('arbeitszeitcheck', 'time'));
})(typeof window !== 'undefined' ? window : globalThis);
