/**
 * Optional ArbeitszeitCheck overtime Stichtag on Nextcloud “New account” (user management).
 *
 * @copyright Copyright (c) 2026 Alexander Mäule <info@software-by-design.de>
 * @license AGPL-3.0-or-later
 */
(function () {
	'use strict';

	const APP_ID = 'arbeitszeitcheck';
	const STATE_KEY = 'arbeitszeitcheckNewUserOvertime';

	/** @type {null|{dialog: HTMLElement, tracking: string}} */
	let pendingCreateCapture = null;

	function readInitialState() {
		try {
			if (typeof window.OCP === 'undefined' || !window.OCP.InitialState || typeof window.OCP.InitialState.loadState !== 'function') {
				return null;
			}
			return window.OCP.InitialState.loadState(APP_ID, STATE_KEY);
		} catch (e) {
			return null;
		}
	}

	function convertEuropeanToISO(value) {
		const s = String(value == null ? '' : value).trim();
		if (!s) {
			return '';
		}
		const dp = window.ArbeitszeitCheckDatepicker;
		if (dp && typeof dp.convertEuropeanToISO === 'function') {
			return dp.convertEuropeanToISO(s);
		}
		if (/^\d{4}-\d{2}-\d{2}$/.test(s)) {
			return s;
		}
		const m = s.match(/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/);
		if (!m) {
			return '';
		}
		return m[3] + '-' + m[2].padStart(2, '0') + '-' + m[1].padStart(2, '0');
	}

	function isValidOptionalEuropeanDate(value) {
		const s = String(value == null ? '' : value).trim();
		if (!s) {
			return true;
		}
		return /^\d{4}-\d{2}-\d{2}$/.test(convertEuropeanToISO(s));
	}

	function getRequestToken() {
		const head = document.head || document.getElementsByTagName('head')[0];
		if (typeof OC !== 'undefined' && OC.requestToken) {
			return OC.requestToken;
		}
		const fromHead = head && head.getAttribute('data-requesttoken');
		if (fromHead) {
			return fromHead;
		}
		const meta = document.querySelector('meta[name="requesttoken"]');
		return meta ? meta.getAttribute('content') || '' : '';
	}

	function buildPutUrl(template, placeholder, userId) {
		if (!template || !placeholder) {
			return '';
		}
		const enc = encodeURIComponent(userId);
		if (template.indexOf(placeholder) === -1) {
			return '';
		}
		return template.split(placeholder).join(enc);
	}

	function resolveDialog(el) {
		if (!el || el.nodeType !== 1) {
			return null;
		}
		if (el.getAttribute('role') === 'dialog') {
			return el;
		}
		return el.closest ? el.closest('[role="dialog"]') : null;
	}

	function looksLikeNewUserDialog(dialog) {
		if (!dialog) {
			return false;
		}
		const title = dialog.querySelector('h2, h3, .modal-title, [class*="modal"] h2');
		const titleText = title ? title.textContent : '';
		if (/new (user|account)|neues konto|nouveau compte|nuovo account|nueva cuenta/i.test(titleText)) {
			return true;
		}
		const pwd = dialog.querySelector('input[type="password"]');
		const textInputs = dialog.querySelectorAll('input[type="text"], input[type="email"], input:not([type])');
		return !!(pwd && textInputs && textInputs.length > 0);
	}

	function findDialogContentRoot(dialog) {
		const content = dialog.querySelector('.modal-container__content, .dialog__content, [class*="modal"][class*="content"], form');
		return content || dialog;
	}

	function injectPanel(dialog, strings) {
		if (dialog.querySelector('.azc-nc-newuser-overtime')) {
			return;
		}
		const mount = findDialogContentRoot(dialog);
		const fieldset = document.createElement('fieldset');
		fieldset.className = 'azc-nc-newuser-overtime';
		fieldset.setAttribute('aria-describedby', 'azc-nc-newuser-overtime-help');

		const legend = document.createElement('legend');
		legend.className = 'azc-nc-newuser-overtime__legend';
		legend.textContent = strings.fieldset || 'ArbeitszeitCheck';

		const row = document.createElement('div');
		row.className = 'azc-nc-newuser-overtime__row';

		const field = document.createElement('div');
		field.className = 'azc-nc-newuser-overtime__field';

		const label = document.createElement('label');
		label.className = 'azc-nc-newuser-overtime__label';
		label.setAttribute('for', 'azc-nc-newuser-overtime-date');
		label.textContent = strings.trackingLabel || 'Overtime tracking from (optional)';

		const input = document.createElement('input');
		input.type = 'text';
		input.id = 'azc-nc-newuser-overtime-date';
		input.className = 'azc-nc-newuser-overtime__input datepicker-input';
		input.setAttribute('autocomplete', 'off');
		input.setAttribute('placeholder', strings.datePlaceholder || 'dd.mm.yyyy');
		input.setAttribute('pattern', '\\d{2}\\.\\d{2}\\.\\d{4}');
		input.setAttribute('maxlength', '10');
		input.setAttribute('inputmode', 'numeric');

		const help = document.createElement('p');
		help.id = 'azc-nc-newuser-overtime-help';
		help.className = 'azc-nc-newuser-overtime__help';
		const helpParts = [strings.trackingHelp || '', strings.formatHelp || ''].filter(Boolean);
		help.textContent = helpParts.join(' ');

		const status = document.createElement('p');
		status.className = 'azc-nc-newuser-overtime__status';
		status.setAttribute('role', 'status');
		status.setAttribute('aria-live', 'polite');
		status.hidden = true;

		field.appendChild(label);
		field.appendChild(input);
		row.appendChild(field);
		fieldset.appendChild(legend);
		fieldset.appendChild(row);
		fieldset.appendChild(help);
		fieldset.appendChild(status);
		mount.appendChild(fieldset);

		dialog.__azcOvertimeStatusEl = status;
		dialog.__azcOvertimeInput = input;

		const dp = window.ArbeitszeitCheckDatepicker;
		if (dp && typeof dp.initializeDatepicker === 'function') {
			dp.initializeDatepicker(input, {});
		}

		bindCreateCapture(dialog, input, strings);
	}

	function bindCreateCapture(dialog, input, strings) {
		if (dialog.__azcCreateCaptureBound) {
			return;
		}
		dialog.__azcCreateCaptureBound = true;

		const capture = function () {
			if (!looksLikeNewUserDialog(dialog)) {
				return;
			}
			const raw = input && input.value ? String(input.value).trim() : '';
			if (raw && !isValidOptionalEuropeanDate(raw)) {
				showStatus(dialog, strings.invalidDate || 'Please enter a valid date (dd.mm.yyyy).', true);
				pendingCreateCapture = null;
				return;
			}
			const tracking = raw ? convertEuropeanToISO(raw) : '';
			pendingCreateCapture = { dialog: dialog, tracking: tracking };
		};

		dialog.addEventListener('submit', capture, true);
		const buttons = dialog.querySelectorAll('button[type="submit"], button.primary, .button-vue--vue-primary');
		buttons.forEach(function (btn) {
			btn.addEventListener('click', capture, true);
		});
	}

	function showStatus(dialog, message, isError) {
		const el = dialog && dialog.__azcOvertimeStatusEl;
		if (!el) {
			return;
		}
		el.textContent = message || '';
		el.hidden = !message;
		el.classList.toggle('azc-nc-newuser-overtime__status--error', !!isError);
	}

	function extractNewUserIdFromOcs(data) {
		if (!data || typeof data !== 'object') {
			return '';
		}
		const ocs = data.ocs;
		if (!ocs || typeof ocs !== 'object') {
			return '';
		}
		const inner = ocs.data;
		if (inner && typeof inner === 'object' && typeof inner.id === 'string') {
			return inner.id;
		}
		if (typeof inner === 'string') {
			return inner;
		}
		return '';
	}

	function isUidLike(uid) {
		if (!uid || typeof uid !== 'string' || uid.length > 64 || uid.length < 1) {
			return false;
		}
		return /^[a-zA-Z0-9_.@-]+$/.test(uid);
	}

	function isOcsUserCreateRequest(url, method) {
		if (method !== 'POST' || typeof url !== 'string') {
			return false;
		}
		if (url.indexOf('/ocs/v') === -1 || url.indexOf('/cloud/users') === -1) {
			return false;
		}
		// Exclude sub-resources (e.g. …/users/{id}/groups).
		return !/\/cloud\/users\/[^/?]+/.test(url);
	}

	async function applyOvertimeStart(state, userId, tracking, dialog) {
		if (!tracking) {
			return;
		}
		const url = buildPutUrl(state.overtimePutUrlTemplate, state.uidPlaceholder, userId);
		if (!url) {
			return;
		}
		const token = getRequestToken();
		const res = await fetch(url, {
			method: 'PUT',
			credentials: 'same-origin',
			headers: {
				Accept: 'application/json',
				'Content-Type': 'application/json',
				requesttoken: token,
				'X-Requested-With': 'XMLHttpRequest',
			},
			body: JSON.stringify({ trackingFrom: tracking }),
		});
		const body = await res.json().catch(function () { return {}; });
		if (res.ok && body && body.success) {
			if (window.OC && typeof window.OC.Notification !== 'undefined' && typeof window.OC.Notification.showTemporary === 'function') {
				window.OC.Notification.showTemporary(state.strings.toastApplied || '');
			}
			const input = dialog && dialog.__azcOvertimeInput;
			if (input) {
				input.value = '';
			}
			showStatus(dialog, '', false);
		} else {
			if (window.OC && typeof window.OC.Notification !== 'undefined' && typeof window.OC.Notification.showTemporary === 'function') {
				window.OC.Notification.showTemporary(state.strings.toastSkipped || '');
			}
			showStatus(dialog, state.strings.toastSkipped || '', true);
		}
	}

	function installFetchHook(state) {
		if (window.__azcUsersFetchHooked) {
			return;
		}
		window.__azcUsersFetchHooked = true;
		const orig = window.fetch;
		window.fetch = function () {
			const args = arguments;
			const p = orig.apply(this, args);
			try {
				const req = args[0];
				const init = args[1] || {};
				const url = typeof req === 'string' ? req : (req && req.url ? req.url : '');
				const method = (init.method || (req && req.method) || 'GET').toUpperCase();
				if (!isOcsUserCreateRequest(url, method)) {
					return p;
				}
				const snapshot = pendingCreateCapture;
				return p.then(function (res) {
					if (!snapshot || !res || !res.ok || res.status < 200 || res.status >= 300) {
						return res;
					}
					return res.clone().json().then(function (data) {
						const uid = extractNewUserIdFromOcs(data);
						if (uid && isUidLike(uid)) {
							applyOvertimeStart(state, uid, snapshot.tracking, snapshot.dialog).catch(function () {});
						}
						pendingCreateCapture = null;
						return res;
					}).catch(function () {
						pendingCreateCapture = null;
						return res;
					});
				});
			} catch (e) {
				return p;
			}
		};
	}

	function observeDialogs(state) {
		const mo = new MutationObserver(function (mutations) {
			for (let i = 0; i < mutations.length; i++) {
				const m = mutations[i];
				if (m.type !== 'childList') {
					continue;
				}
				m.addedNodes.forEach(function (node) {
					if (!node || node.nodeType !== 1) {
						return;
					}
					const direct = resolveDialog(node);
					if (direct && looksLikeNewUserDialog(direct)) {
						injectPanel(direct, state.strings || {});
					}
					const dialogs = node.querySelectorAll ? node.querySelectorAll('[role="dialog"]') : [];
					dialogs.forEach(function (dlg) {
						if (looksLikeNewUserDialog(dlg)) {
							injectPanel(dlg, state.strings || {});
						}
					});
				});
			}
		});
		mo.observe(document.body, { childList: true, subtree: true });
	}

	function init() {
		const state = readInitialState();
		if (!state || !state.overtimePutUrlTemplate || !state.uidPlaceholder) {
			return;
		}
		installFetchHook(state);
		observeDialogs(state);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
