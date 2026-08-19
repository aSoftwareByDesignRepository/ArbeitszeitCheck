/**
 * Defers OC.L10N.register until window.OC exists.
 *
 * Locale files under l10n/*.js wrap registration with __azcBootL10n so they
 * never throw on /apps/dashboard or other early loads. This script must load
 * before Util::addTranslations() injects those locale files.
 *
 * @copyright Copyright (c) 2026, Software by Design GbR
 * @license AGPL-3.0-or-later
 */
(function (root) {
	'use strict';

	function registerWhenReady(registerFn) {
		try {
			var OC = root.OC;
			if (typeof OC !== 'undefined' && OC.L10N && typeof OC.L10N.register === 'function') {
				registerFn(OC);
				return true;
			}
		} catch (e) {
			// Never break page boot if OC is mid-init.
		}
		return false;
	}

	root.__azcBootL10n = function (registerFn) {
		if (typeof registerFn !== 'function') {
			return;
		}
		if (registerWhenReady(registerFn)) {
			return;
		}
		var tries = 0;
		var timer = setInterval(function () {
			tries += 1;
			if (registerWhenReady(registerFn) || tries > 100) {
				clearInterval(timer);
			}
		}, 40);
	};
})(typeof window !== 'undefined' ? window : globalThis);
