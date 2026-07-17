/**
 * Admin kiosk — terminals, credentials, enrollment.
 *
 * @license AGPL-3.0-or-later
 */
(function () {
	'use strict';

	const page = document.getElementById('azc-kiosk-page');
	if (!page) {
		return;
	}

	const Messaging = window.ArbeitszeitCheckMessaging || {};
	const token = page.dataset.requesttoken || '';
	const live = document.getElementById('azc-kiosk-live');
	const alertEl = document.getElementById('azc-kiosk-alert');
	const feedback = document.getElementById('azc-kiosk-feedback');
	const FLASH_KEY = 'azc-kiosk-flash';

	let i18n = {};
	try {
		i18n = JSON.parse(page.dataset.i18n || '{}');
	} catch {
		i18n = {};
	}

	function t(key, fallback) {
		return i18n[key] || fallback || key;
	}

	function prefersReducedMotion() {
		return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	}

	function announce(el, message) {
		if (el) {
			el.textContent = message;
		}
	}

	function showFeedback(message, type) {
		if (!feedback) {
			return;
		}
		feedback.hidden = false;
		feedback.textContent = message;
		feedback.className = 'azc-kiosk-feedback azc-kiosk-feedback--' + (type || 'success');
		feedback.scrollIntoView({
			block: 'nearest',
			behavior: prefersReducedMotion() ? 'auto' : 'smooth',
		});
		if (type === 'error') {
			announce(alertEl, message);
			if (typeof Messaging.showError === 'function') {
				Messaging.showError(message);
			}
		} else {
			announce(live, message);
			if (typeof Messaging.showSuccess === 'function') {
				Messaging.showSuccess(message);
			}
		}
	}

	function setFlashAndReload(message) {
		try {
			window.sessionStorage.setItem(FLASH_KEY, message);
		} catch {
			// Best effort.
		}
		window.location.reload();
	}

	try {
		const flash = window.sessionStorage.getItem(FLASH_KEY);
		if (flash) {
			window.sessionStorage.removeItem(FLASH_KEY);
			showFeedback(flash, 'success');
		}
	} catch {
		// ignore
	}

	function headers() {
		return {
			'Content-Type': 'application/json',
			requesttoken: token,
			'X-Requested-With': 'XMLHttpRequest',
		};
	}

	function apiUrl(pattern, id) {
		return String(pattern || '').replace('__ID__', encodeURIComponent(id));
	}

	async function api(url, options) {
		const res = await fetch(url, options);
		const data = await res.json().catch(() => ({}));
		if (!res.ok) {
			const msg = data.message || data.error || t('requestFailed', 'Request failed');
			const err = new Error(msg);
			err.code = data.error;
			throw err;
		}
		return data;
	}

	function escapeHtml(s) {
		const d = document.createElement('div');
		d.textContent = s;
		return d.innerHTML;
	}

	function setBusy(el, busy) {
		if (!el) {
			return;
		}
		el.disabled = !!busy;
		el.setAttribute('aria-busy', busy ? 'true' : 'false');
	}

	function formatDateTime(iso) {
		if (!iso) {
			return t('neverSeen', 'Never');
		}
		try {
			const d = new Date(iso);
			if (Number.isNaN(d.getTime())) {
				return iso;
			}
			const pad = (n) => String(n).padStart(2, '0');
			return pad(d.getDate()) + '.' + pad(d.getMonth() + 1) + '.' + d.getFullYear()
				+ ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
		} catch {
			return iso;
		}
	}

	// Format last-seen cells rendered by PHP.
	document.querySelectorAll('.azc-kiosk-last-seen[data-iso]').forEach((el) => {
		const iso = el.getAttribute('data-iso') || '';
		el.textContent = formatDateTime(iso);
	});

	let modalReturnFocus = null;
	let openModalEl = null;

	function getFocusables(modal) {
		return Array.from(modal.querySelectorAll(
			'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
		)).filter((el) => el.offsetParent !== null || el === document.activeElement);
	}

	function openModal(modal, backdrop, returnFocus) {
		if (!modal) {
			return;
		}
		modalReturnFocus = returnFocus || null;
		openModalEl = modal;
		modal.hidden = false;
		if (backdrop) {
			backdrop.hidden = false;
		}
		document.body.classList.add('azc-kiosk-modal-open');
		const focusables = getFocusables(modal);
		if (focusables[0] instanceof HTMLElement) {
			focusables[0].focus();
		}
	}

	function closeModal(modal, backdrop, reloadAfter) {
		if (!modal) {
			return;
		}
		modal.hidden = true;
		if (backdrop) {
			backdrop.hidden = true;
		}
		if (openModalEl === modal) {
			openModalEl = null;
		}
		document.body.classList.remove('azc-kiosk-modal-open');
		if (modalReturnFocus instanceof HTMLElement) {
			modalReturnFocus.focus();
		}
		modalReturnFocus = null;
		if (reloadAfter) {
			window.location.reload();
		}
	}

	function bindModal(modal, backdrop, closeBtn, reloadOnClose) {
		if (!modal) {
			return;
		}
		const close = () => closeModal(modal, backdrop, !!reloadOnClose);
		if (closeBtn) {
			closeBtn.addEventListener('click', close);
		}
		if (backdrop) {
			backdrop.addEventListener('click', close);
		}
	}

	document.addEventListener('keydown', (e) => {
		if (!openModalEl || openModalEl.hidden) {
			return;
		}
		if (e.key === 'Escape') {
			e.preventDefault();
			const isPairing = openModalEl.id === 'azc-kiosk-pairing-modal';
			closeModal(
				openModalEl,
				openModalEl.id === 'azc-kiosk-pin-modal'
					? document.getElementById('azc-kiosk-pin-backdrop')
					: document.getElementById('azc-kiosk-pairing-backdrop'),
				isPairing,
			);
			return;
		}
		if (e.key === 'Tab') {
			const focusables = getFocusables(openModalEl);
			if (focusables.length === 0) {
				return;
			}
			const first = focusables[0];
			const last = focusables[focusables.length - 1];
			if (e.shiftKey && document.activeElement === first) {
				e.preventDefault();
				last.focus();
			} else if (!e.shiftKey && document.activeElement === last) {
				e.preventDefault();
				first.focus();
			}
		}
	});

	const pairingModal = document.getElementById('azc-kiosk-pairing-modal');
	const pairingBackdrop = document.getElementById('azc-kiosk-pairing-backdrop');
	const pairingClose = document.getElementById('azc-kiosk-pairing-close');
	bindModal(pairingModal, pairingBackdrop, pairingClose, true);

	const pinModal = document.getElementById('azc-kiosk-pin-modal');
	const pinBackdrop = document.getElementById('azc-kiosk-pin-backdrop');
	const pinClose = document.getElementById('azc-kiosk-pin-close');
	bindModal(pinModal, pinBackdrop, pinClose, false);

	const selectedUser = document.getElementById('azc-kiosk-selected-user');
	const selectedPanel = document.getElementById('azc-kiosk-selected-panel');
	const selectedName = document.getElementById('azc-kiosk-selected-name');
	const selectedAllowed = document.getElementById('azc-kiosk-selected-allowed');
	const userSearch = document.getElementById('azc-kiosk-user-search');
	const userResults = document.getElementById('azc-kiosk-user-results');

	let enrollmentPollToken = 0;
	let enrollmentTerminalId = '';
	let searchAbort = null;
	let searchTimer = null;
	let searchActiveIndex = -1;

	function colLabels() {
		return {
			employee: t('employee', 'Employee'),
			credentials: t('credentials', 'Credentials'),
			allowed: t('kioskAllowedLabel', 'Allow kiosk access'),
			actions: t('actions', 'Actions'),
		};
	}

	function typeLabel(type) {
		if (type === 'pin') {
			return t('typePin', 'PIN');
		}
		if (type === 'rfid') {
			return t('typeRfid', 'Badge');
		}
		return type;
	}

	function groupCredentials(creds) {
		const map = new Map();
		creds.forEach((c) => {
			if (!map.has(c.userId)) {
				map.set(c.userId, {
					userId: c.userId,
					displayName: c.displayName,
					kioskAllowed: !!c.kioskAllowed,
					items: [],
				});
			}
			const row = map.get(c.userId);
			row.kioskAllowed = !!c.kioskAllowed;
			row.items.push(c);
		});
		return Array.from(map.values());
	}

	async function loadCredentials() {
		const tbody = document.getElementById('azc-kiosk-creds-body');
		if (!tbody) {
			return;
		}
		const data = await api(page.dataset.apiCredentials || '', { headers: headers() });
		const creds = (data.data && data.data.credentials) || [];
		const groups = groupCredentials(creds);
		const labels = colLabels();
		const allowedLabel = t('kioskAllowedLabel', 'Allow kiosk access');

		if (groups.length === 0) {
			tbody.innerHTML = '<tr class="azc-kiosk-empty-row"><td class="azc-kiosk-empty-cell" colspan="4">'
				+ escapeHtml(t('noCredentials', 'No badges or PINs yet. Select an employee above to get started.'))
				+ '</td></tr>';
			return;
		}

		tbody.innerHTML = groups.map((g) => {
			const toggleId = 'azc-kiosk-allowed-user-' + escapeHtml(g.userId);
			const tags = g.items.map((c) =>
				'<span class="azc-badge azc-badge--neutral">' + escapeHtml(typeLabel(c.type)) + '</span>'
			).join('');
			const actions = g.items.map((c) => {
				const btnLabel = c.type === 'pin'
					? t('deletePin', 'Delete PIN')
					: t('deleteBadge', 'Delete badge');
				return '<button type="button" class="azc-btn azc-btn--small azc-btn--danger azc-kiosk-delete-cred" data-id="'
					+ escapeHtml(String(c.id)) + '">' + escapeHtml(btnLabel) + '</button>';
			}).join('');
			return '<tr data-user-id="' + escapeHtml(g.userId) + '">'
				+ '<td data-label="' + escapeHtml(labels.employee) + '">'
				+ escapeHtml(g.displayName) + ' <small>(' + escapeHtml(g.userId) + ')</small></td>'
				+ '<td data-label="' + escapeHtml(labels.credentials) + '"><div class="azc-kiosk-cred-tags">' + tags + '</div></td>'
				+ '<td data-label="' + escapeHtml(labels.allowed) + '">'
				+ '<label class="azc-kiosk-allowed-toggle" for="' + toggleId + '">'
				+ '<input type="checkbox" class="azc-kiosk-allowed-input" id="' + toggleId + '"'
				+ ' data-user-id="' + escapeHtml(g.userId) + '"' + (g.kioskAllowed ? ' checked' : '') + '>'
				+ '<span class="azc-sr-only">' + escapeHtml(allowedLabel) + ' — ' + escapeHtml(g.displayName) + '</span>'
				+ '</label></td>'
				+ '<td data-label="' + escapeHtml(labels.actions) + '"><div class="azc-kiosk-cred-actions">' + actions + '</div></td>'
				+ '</tr>';
		}).join('');

		tbody.querySelectorAll('.azc-kiosk-delete-cred').forEach((btn) => {
			btn.addEventListener('click', async () => {
				const id = btn.getAttribute('data-id');
				if (!window.confirm(t('confirmDeleteCred', 'Remove this credential?'))) {
					return;
				}
				setBusy(btn, true);
				try {
					await api((page.dataset.apiCredentials || '') + '/' + id, { method: 'DELETE', headers: headers() });
					showFeedback(t('credentialRemoved', 'Credential removed'), 'success');
					await loadCredentials();
				} catch (e) {
					showFeedback(e instanceof Error ? e.message : t('requestFailed', 'Request failed'), 'error');
					setBusy(btn, false);
				}
			});
		});

		tbody.querySelectorAll('.azc-kiosk-allowed-input').forEach((input) => {
			input.addEventListener('change', async () => {
				const userId = input.getAttribute('data-user-id') || '';
				const allowed = input.checked;
				input.disabled = true;
				try {
					await api(apiUrl(page.dataset.apiUserAllowed || '', userId), {
						method: 'PUT',
						headers: headers(),
						body: JSON.stringify({ kioskAllowed: allowed }),
					});
					showFeedback(
						allowed ? t('kioskAllowedOn', 'Kiosk access enabled') : t('kioskAllowedOff', 'Kiosk access disabled'),
						'success',
					);
					if (selectedUser && selectedUser.value === userId && selectedAllowed) {
						selectedAllowed.checked = allowed;
					}
				} catch (e) {
					input.checked = !allowed;
					showFeedback(e instanceof Error ? e.message : t('requestFailed', 'Request failed'), 'error');
				} finally {
					input.disabled = false;
				}
			});
		});
	}

	function bindRevokeButtons(scope) {
		const root = scope || document;
		root.querySelectorAll('.azc-kiosk-revoke-terminal').forEach((btn) => {
			if (btn.dataset.bound === '1') {
				return;
			}
			btn.dataset.bound = '1';
			btn.addEventListener('click', async () => {
				const terminalId = btn.getAttribute('data-terminal-id') || '';
				if (!terminalId) {
					return;
				}
				if (!window.confirm(t('confirmRevoke', 'Revoke this terminal?'))) {
					return;
				}
				setBusy(btn, true);
				try {
					await api(apiUrl(page.dataset.apiTerminalRevoke || '', terminalId), {
						method: 'POST',
						headers: headers(),
					});
					setFlashAndReload(t('terminalRevoked', 'Terminal revoked'));
				} catch (e) {
					showFeedback(e instanceof Error ? e.message : t('requestFailed', 'Request failed'), 'error');
					setBusy(btn, false);
				}
			});
		});
	}

	const enabledToggle = document.getElementById('azc-kiosk-enabled');
	if (enabledToggle) {
		enabledToggle.addEventListener('change', async () => {
			const wanted = enabledToggle.checked;
			enabledToggle.disabled = true;
			try {
				await api(page.dataset.apiEnabled || '', {
					method: 'POST',
					headers: headers(),
					body: JSON.stringify({ enabled: wanted }),
				});
				showFeedback(
					wanted ? t('kioskEnabled', 'Kiosk enabled') : t('kioskDisabled', 'Kiosk disabled'),
					'success',
				);
			} catch (e) {
				enabledToggle.checked = !wanted;
				showFeedback(e instanceof Error ? e.message : t('requestFailed', 'Request failed'), 'error');
			} finally {
				enabledToggle.disabled = false;
			}
		});
	}

	const createBtn = document.getElementById('azc-kiosk-create-terminal');
	if (createBtn) {
		createBtn.addEventListener('click', async () => {
			const labelEl = document.getElementById('azc-kiosk-terminal-label');
			const label = labelEl ? labelEl.value.trim() : '';
			if (!label) {
				showFeedback(t('labelRequired', 'Enter a terminal label'), 'error');
				if (labelEl) {
					labelEl.focus();
				}
				return;
			}
			setBusy(createBtn, true);
			try {
				const data = await api(page.dataset.apiTerminals || '', {
					method: 'POST',
					headers: headers(),
					body: JSON.stringify({ label }),
				});
				const codeEl = document.getElementById('azc-kiosk-pairing-code');
				const expiresEl = document.getElementById('azc-kiosk-pairing-expires');
				if (codeEl && data.data) {
					codeEl.textContent = data.data.pairingCode || '';
				}
				if (expiresEl && data.data && data.data.pairingExpiresAt) {
					expiresEl.hidden = false;
					expiresEl.textContent = t('pairingExpires', 'Valid until') + ': '
						+ formatDateTime(data.data.pairingExpiresAt);
				}
				openModal(pairingModal, pairingBackdrop, createBtn);
				showFeedback(t('terminalCreated', 'Terminal created — save the pairing code'), 'success');
				if (labelEl) {
					labelEl.value = '';
				}
			} catch (e) {
				showFeedback(e instanceof Error ? e.message : t('requestFailed', 'Request failed'), 'error');
			} finally {
				setBusy(createBtn, false);
			}
		});
	}

	function selectEmployee(userId, displayName, kioskAllowed) {
		if (selectedUser) {
			selectedUser.value = userId;
		}
		if (userSearch) {
			userSearch.value = displayName;
		}
		if (userResults) {
			userResults.hidden = true;
			userResults.innerHTML = '';
		}
		if (userSearch) {
			userSearch.setAttribute('aria-expanded', 'false');
			userSearch.removeAttribute('aria-activedescendant');
		}
		searchActiveIndex = -1;
		if (selectedPanel) {
			selectedPanel.hidden = false;
		}
		if (selectedName) {
			selectedName.textContent = displayName + ' (' + userId + ')';
		}
		if (selectedAllowed) {
			selectedAllowed.checked = !!kioskAllowed;
		}
	}

	function renderSearchResults(users) {
		if (!userResults) {
			return;
		}
		searchActiveIndex = -1;
		if (users.length === 0) {
			userResults.hidden = true;
			userResults.innerHTML = '';
			if (userSearch) {
				userSearch.setAttribute('aria-expanded', 'false');
			}
			return;
		}
		userResults.innerHTML = users.map((u, idx) =>
			'<li role="option" id="azc-kiosk-user-opt-' + idx + '" data-id="' + escapeHtml(u.userId)
			+ '" data-allowed="' + (u.kioskAllowed ? '1' : '0') + '">'
			+ '<button type="button" class="azc-kiosk-user-pick" tabindex="-1">'
			+ escapeHtml(u.displayName) + '</button></li>'
		).join('');
		userResults.hidden = false;
		if (userSearch) {
			userSearch.setAttribute('aria-expanded', 'true');
		}
		userResults.querySelectorAll('.azc-kiosk-user-pick').forEach((btn) => {
			btn.addEventListener('mousedown', (e) => {
				e.preventDefault();
				const li = btn.closest('li');
				if (!li) {
					return;
				}
				selectEmployee(
					li.getAttribute('data-id') || '',
					btn.textContent || '',
					li.getAttribute('data-allowed') === '1',
				);
			});
		});
	}

	function moveSearchHighlight(delta) {
		if (!userResults || userResults.hidden) {
			return;
		}
		const options = Array.from(userResults.querySelectorAll('[role="option"]'));
		if (options.length === 0) {
			return;
		}
		searchActiveIndex = (searchActiveIndex + delta + options.length) % options.length;
		options.forEach((opt, i) => {
			opt.setAttribute('aria-selected', i === searchActiveIndex ? 'true' : 'false');
		});
		const active = options[searchActiveIndex];
		if (userSearch && active) {
			userSearch.setAttribute('aria-activedescendant', active.id);
			active.scrollIntoView({ block: 'nearest' });
		}
	}

	if (userSearch && userResults) {
		userSearch.addEventListener('input', () => {
			if (selectedUser) {
				selectedUser.value = '';
			}
			if (selectedPanel) {
				selectedPanel.hidden = true;
			}
			clearTimeout(searchTimer);
			searchTimer = setTimeout(async () => {
				const q = userSearch.value.trim();
				if (q.length < 2) {
					userResults.hidden = true;
					userResults.innerHTML = '';
					userSearch.setAttribute('aria-expanded', 'false');
					return;
				}
				if (searchAbort) {
					searchAbort.abort();
				}
				searchAbort = new AbortController();
				try {
					const data = await api(
						(page.dataset.apiSearchUsers || '') + '?q=' + encodeURIComponent(q),
						{ headers: headers(), signal: searchAbort.signal },
					);
					renderSearchResults(data.users || []);
				} catch (e) {
					if (e && e.name === 'AbortError') {
						return;
					}
					showFeedback(e instanceof Error ? e.message : t('requestFailed', 'Request failed'), 'error');
				}
			}, 250);
		});

		userSearch.addEventListener('keydown', (e) => {
			if (e.key === 'ArrowDown') {
				e.preventDefault();
				moveSearchHighlight(1);
			} else if (e.key === 'ArrowUp') {
				e.preventDefault();
				moveSearchHighlight(-1);
			} else if (e.key === 'Enter') {
				if (searchActiveIndex >= 0 && userResults) {
					const opt = userResults.querySelectorAll('[role="option"]')[searchActiveIndex];
					if (opt) {
						e.preventDefault();
						const btn = opt.querySelector('button');
						selectEmployee(
							opt.getAttribute('data-id') || '',
							btn ? btn.textContent || '' : '',
							opt.getAttribute('data-allowed') === '1',
						);
					}
				}
			} else if (e.key === 'Escape') {
				userResults.hidden = true;
				userSearch.setAttribute('aria-expanded', 'false');
			}
		});
	}

	if (selectedAllowed) {
		selectedAllowed.addEventListener('change', async () => {
			const userId = selectedUser ? selectedUser.value : '';
			if (!userId) {
				selectedAllowed.checked = false;
				showFeedback(t('selectEmployee', 'Select an employee first'), 'error');
				return;
			}
			const allowed = selectedAllowed.checked;
			selectedAllowed.disabled = true;
			try {
				await api(apiUrl(page.dataset.apiUserAllowed || '', userId), {
					method: 'PUT',
					headers: headers(),
					body: JSON.stringify({ kioskAllowed: allowed }),
				});
				showFeedback(
					allowed ? t('kioskAllowedOn', 'Kiosk access enabled') : t('kioskAllowedOff', 'Kiosk access disabled'),
					'success',
				);
				await loadCredentials();
			} catch (e) {
				selectedAllowed.checked = !allowed;
				showFeedback(e instanceof Error ? e.message : t('requestFailed', 'Request failed'), 'error');
			} finally {
				selectedAllowed.disabled = false;
			}
		});
	}

	function stopEnrollmentPoll() {
		enrollmentPollToken += 1;
		enrollmentTerminalId = '';
		const cancelBtn = document.getElementById('azc-kiosk-cancel-enrollment');
		if (cancelBtn) {
			cancelBtn.hidden = true;
		}
	}

	async function pollEnrollment(terminalId) {
		const myToken = ++enrollmentPollToken;
		enrollmentTerminalId = terminalId;
		const statusEl = document.getElementById('azc-kiosk-enrollment-status');
		const cancelBtn = document.getElementById('azc-kiosk-cancel-enrollment');
		if (cancelBtn) {
			cancelBtn.hidden = false;
		}
		for (let i = 0; i < 60; i++) {
			if (myToken !== enrollmentPollToken) {
				return;
			}
			await new Promise((r) => setTimeout(r, 3000));
			if (myToken !== enrollmentPollToken) {
				return;
			}
			try {
				const data = await api(
					(page.dataset.apiEnrollmentStatus || '') + '?terminalId=' + encodeURIComponent(terminalId),
					{ headers: headers() },
				);
				const st = (data.data && data.data.status) || '';
				if (st === 'completed') {
					if (statusEl) {
						statusEl.textContent = t('enrollmentDone', 'Badge assigned successfully');
					}
					showFeedback(t('enrollmentDone', 'Badge assigned successfully'), 'success');
					stopEnrollmentPoll();
					await loadCredentials();
					return;
				}
				if (st === 'expired') {
					if (statusEl) {
						statusEl.textContent = t('enrollmentExpired', 'Enrollment expired');
					}
					showFeedback(t('enrollmentExpired', 'Enrollment expired'), 'error');
					stopEnrollmentPoll();
					return;
				}
			} catch (e) {
				if (i === 59) {
					showFeedback(e instanceof Error ? e.message : t('requestFailed', 'Request failed'), 'error');
					stopEnrollmentPoll();
				}
			}
		}
		if (myToken === enrollmentPollToken) {
			if (statusEl) {
				statusEl.textContent = t('enrollmentExpired', 'Enrollment expired');
			}
			stopEnrollmentPoll();
		}
	}

	const enrollBtn = document.getElementById('azc-kiosk-start-enrollment');
	if (enrollBtn) {
		enrollBtn.addEventListener('click', async () => {
			const userId = selectedUser ? selectedUser.value : '';
			const terminalSelect = document.getElementById('azc-kiosk-enroll-terminal');
			const terminalId = terminalSelect ? terminalSelect.value : '';
			if (!userId || !terminalId) {
				showFeedback(t('selectEmployeeTerminal', 'Select employee and terminal'), 'error');
				return;
			}
			if (selectedAllowed && !selectedAllowed.checked) {
				showFeedback(t('enableAccessFirst', 'Allow kiosk access for this employee first'), 'error');
				return;
			}
			setBusy(enrollBtn, true);
			try {
				await api(page.dataset.apiEnrollmentStart || '', {
					method: 'POST',
					headers: headers(),
					body: JSON.stringify({ userId, terminalId }),
				});
				const statusEl = document.getElementById('azc-kiosk-enrollment-status');
				if (statusEl) {
					statusEl.textContent = t('enrollmentWaiting', 'Waiting for badge scan…');
				}
				pollEnrollment(terminalId);
			} catch (e) {
				showFeedback(e instanceof Error ? e.message : t('requestFailed', 'Request failed'), 'error');
			} finally {
				setBusy(enrollBtn, false);
			}
		});
	}

	const cancelEnrollBtn = document.getElementById('azc-kiosk-cancel-enrollment');
	if (cancelEnrollBtn) {
		cancelEnrollBtn.addEventListener('click', async () => {
			const terminalId = enrollmentTerminalId
				|| (document.getElementById('azc-kiosk-enroll-terminal') || {}).value
				|| '';
			if (!terminalId) {
				stopEnrollmentPoll();
				return;
			}
			setBusy(cancelEnrollBtn, true);
			try {
				await api(page.dataset.apiEnrollmentCancel || '', {
					method: 'POST',
					headers: headers(),
					body: JSON.stringify({ terminalId }),
				});
				const statusEl = document.getElementById('azc-kiosk-enrollment-status');
				if (statusEl) {
					statusEl.textContent = t('enrollmentCancelled', 'Enrollment cancelled');
				}
				showFeedback(t('enrollmentCancelled', 'Enrollment cancelled'), 'success');
				stopEnrollmentPoll();
			} catch (e) {
				showFeedback(e instanceof Error ? e.message : t('requestFailed', 'Request failed'), 'error');
			} finally {
				setBusy(cancelEnrollBtn, false);
			}
		});
	}

	const pinBtn = document.getElementById('azc-kiosk-generate-pin');
	if (pinBtn) {
		pinBtn.addEventListener('click', async () => {
			const userId = selectedUser ? selectedUser.value : '';
			if (!userId) {
				showFeedback(t('selectEmployee', 'Select an employee first'), 'error');
				if (userSearch) {
					userSearch.focus();
				}
				return;
			}
			if (selectedAllowed && !selectedAllowed.checked) {
				showFeedback(t('enableAccessFirst', 'Allow kiosk access for this employee first'), 'error');
				return;
			}
			setBusy(pinBtn, true);
			try {
				const data = await api(page.dataset.apiPin || '', {
					method: 'POST',
					headers: headers(),
					body: JSON.stringify({ userId }),
				});
				const pin = data.data && data.data.pin;
				const codeEl = document.getElementById('azc-kiosk-pin-code');
				if (codeEl) {
					codeEl.textContent = pin ? String(pin) : '';
				}
				openModal(pinModal, pinBackdrop, pinBtn);
				await loadCredentials();
			} catch (e) {
				showFeedback(e instanceof Error ? e.message : t('requestFailed', 'Request failed'), 'error');
			} finally {
				setBusy(pinBtn, false);
			}
		});
	}

	bindRevokeButtons();
	loadCredentials().catch((e) => {
		showFeedback(e instanceof Error ? e.message : t('requestFailed', 'Request failed'), 'error');
	});
})();
