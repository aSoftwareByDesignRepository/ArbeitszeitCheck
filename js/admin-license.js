/**
 * Admin license tab — paste AZC2 key, assign mobile seats.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */
(function () {
	'use strict';

	const page = document.getElementById('azc-license-page');
	if (!page) {
		return;
	}

	const Messaging = window.ArbeitszeitCheckMessaging || {};
	const Utils = window.ArbeitszeitCheckUtils || {};

	const apiLicense = page.dataset.apiLicense || '';
	const apiClearLicense = page.dataset.apiClearLicense || '';
	const apiSeats = page.dataset.apiSeats || '';
	const apiRemoveSeat = page.dataset.apiRemoveSeat || '';
	const apiSearchUsers = page.dataset.apiSearchUsers || '';
	const requestToken = page.dataset.requesttoken || '';
	const FLASH_KEY = 'azc-license-flash';
	let i18n = {};
	try {
		i18n = JSON.parse(page.dataset.i18n || '{}');
	} catch {
		i18n = {};
	}

	function t(key, fallback) {
		return i18n[key] || fallback || key;
	}

	const liveRegion = document.getElementById('azc-license-live');
	const alertRegion = document.getElementById('azc-license-alert');
	const feedback = document.getElementById('azc-license-feedback');
	const keyInput = document.getElementById('azc-license-key-input');
	const saveBtn = document.getElementById('azc-license-save');
	const clearBtn = document.getElementById('azc-license-clear');
	const statusPanel = document.getElementById('azc-license-status');
	const seatListBody = document.getElementById('azc-seat-list-body');
	const seatEmpty = document.getElementById('azc-seat-empty');
	const seatCount = document.getElementById('azc-seat-count');
	const seatsFullHint = document.getElementById('azc-seats-full-hint');
	const userSearch = document.getElementById('azc-seat-user-search');
	const searchResults = document.getElementById('azc-seat-search-results');
	const clearBackdrop = document.getElementById('azc-license-clear-backdrop');
	const clearModal = document.getElementById('azc-license-clear-modal');
	const clearCancel = document.getElementById('azc-license-clear-cancel');
	const clearConfirm = document.getElementById('azc-license-clear-confirm');

	let searchTimer = null;
	let searchAbort = null;
	let searchActiveIndex = -1;
	let modalReturnFocus = null;

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
		feedback.className = 'azc-license-feedback azc-license-feedback--' + (type || 'success');
		// Never scrollIntoView here. Seat assign/remove happens further down the
		// page; yanking to the top banner forces the admin to scroll back after
		// every click. Toast + aria-live already announce the outcome.
		if (type === 'error') {
			announce(alertRegion, message);
			if (typeof Messaging.showError === 'function') {
				Messaging.showError(message);
			}
		} else {
			announce(liveRegion, message);
			if (typeof Messaging.showSuccess === 'function') {
				Messaging.showSuccess(message);
			}
		}
	}

	function hideFeedback() {
		if (feedback) {
			feedback.hidden = true;
			feedback.textContent = '';
		}
	}

	// Show the outcome of a save/clear that triggered a page reload.
	try {
		const flash = window.sessionStorage.getItem(FLASH_KEY);
		if (flash) {
			window.sessionStorage.removeItem(FLASH_KEY);
			showFeedback(flash, 'success');
		}
	} catch {
		// Session storage unavailable (private mode restrictions) — reload without flash.
	}

	function setFlashAndReload(message) {
		try {
			window.sessionStorage.setItem(FLASH_KEY, message);
		} catch {
			// Best effort only.
		}
		window.location.reload();
	}

	function headers() {
		return {
			'Content-Type': 'application/json',
			requesttoken: requestToken,
			'X-Requested-With': 'XMLHttpRequest',
		};
	}

	function escapeHtml(s) {
		const d = document.createElement('div');
		d.textContent = s;
		return d.innerHTML;
	}

	function escapeAttr(s) {
		return escapeHtml(String(s));
	}

	function formatAssignedAt(iso) {
		if (!iso) {
			return '—';
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

	function formatSeatCount(used, limit) {
		// window.t returns the translated string with printf placeholders
		// intact (the JS l10n layer only substitutes {curly} variables).
		const template = (typeof window.t === 'function'
			? window.t('arbeitszeitcheck', '%1$d of %2$d seats assigned')
			: null) || '%1$d of %2$d seats assigned';
		return template.replace('%1$d', String(used)).replace('%2$d', String(limit));
	}

	function updateMeter(usedEl, limitEl, meterEl, used, limit) {
		if (usedEl) {
			usedEl.textContent = String(used);
		}
		if (limitEl) {
			limitEl.textContent = String(limit);
		}
		if (meterEl && limit > 0) {
			const pct = Math.min(100, Math.round((used / limit) * 100));
			meterEl.style.width = pct + '%';
			meterEl.classList.toggle('azc-license-meter__fill--full', used >= limit);
			const meter = meterEl.closest('[role="meter"]');
			if (meter) {
				meter.setAttribute('aria-valuenow', String(used));
				meter.setAttribute('aria-valuemax', String(Math.max(1, limit)));
			}
		}
	}

	/**
	 * Refresh only the mobile-seat counters. Seat responses do not carry
	 * license or terminal data — never touch those parts of the page here.
	 */
	function updateSeatCounts(used, limit) {
		if (!Number.isFinite(used) || !Number.isFinite(limit)) {
			return;
		}
		updateMeter(
			document.getElementById('azc-license-mobile-used'),
			document.getElementById('azc-license-mobile-limit'),
			document.getElementById('azc-license-mobile-meter'),
			used,
			limit,
		);
		if (seatCount && limit > 0) {
			seatCount.textContent = formatSeatCount(used, limit);
		}
		const full = limit > 0 && used >= limit;
		if (userSearch) {
			userSearch.disabled = full;
			userSearch.setAttribute('aria-disabled', full ? 'true' : 'false');
			if (full) {
				closeSearchResults();
			}
		}
		if (seatsFullHint) {
			seatsFullHint.hidden = !full;
		}
	}

	/**
	 * Refresh the license overview after a full license response
	 * (apply license). Requires data.license to be present.
	 */
	function updateStatus(data) {
		if (!data || !data.license) {
			return;
		}
		const lic = data.license;
		if (statusPanel) {
			statusPanel.hidden = false;
		}
		const set = (id, val) => {
			const el = document.getElementById(id);
			if (el) {
				el.textContent = val;
			}
		};
		set('azc-license-customer', lic.customerId || '');
		set('azc-license-valid-until', lic.validUntil || '—');
		const badge = document.getElementById('azc-license-active-badge');
		if (badge) {
			const active = !!lic.active;
			const signatureInvalid = !!lic.dateValid && !lic.cryptographicallyValid;
			badge.textContent = active
				? (badge.dataset.activeLabel || t('activeLabel', 'Active'))
				: signatureInvalid
					? (badge.dataset.signatureInvalidLabel || t('signatureInvalidLabel', 'Signature mismatch'))
					: (badge.dataset.inactiveLabel || t('inactiveLabel', 'Expired or invalid'));
			badge.classList.toggle('azc-badge--success', active);
			badge.classList.toggle('azc-badge--warning', !active);
		}
		updateSeatCounts(data.mobileSeatsUsed ?? 0, data.mobileSeatsLimit ?? lic.mobileSeats ?? 0);
		updateMeter(
			document.getElementById('azc-license-terminal-used'),
			document.getElementById('azc-license-terminal-limit'),
			document.getElementById('azc-license-terminal-meter'),
			data.terminalDevicesUsed ?? 0,
			data.terminalDevicesLimit ?? lic.terminalDevices ?? 0,
		);
	}

	function renderSeatRows(seats) {
		if (!seatListBody) {
			return;
		}
		seatListBody.innerHTML = '';
		if (!seats || seats.length === 0) {
			if (seatEmpty) {
				seatEmpty.hidden = false;
			}
			return;
		}
		if (seatEmpty) {
			seatEmpty.hidden = true;
		}
		const removeLabel = t('removeSeat', 'Remove');
		const colEmployee = escapeAttr(t('colEmployee', 'Employee'));
		const colUserId = escapeAttr(t('colUserId', 'User ID'));
		const colAssigned = escapeAttr(t('colAssigned', 'Assigned'));
		const colActions = escapeAttr(t('colActions', 'Actions'));
		seats.forEach((seat) => {
			const tr = document.createElement('tr');
			tr.dataset.userId = seat.userId;
			tr.innerHTML =
				'<td data-label="' + colEmployee + '">' + escapeHtml(seat.displayName) + '</td>' +
				'<td data-label="' + colUserId + '"><code class="azc-license-user-id">' + escapeHtml(seat.userId) + '</code></td>' +
				'<td data-label="' + colAssigned + '">' + escapeHtml(formatAssignedAt(seat.assignedAt)) + '</td>' +
				'<td data-label="' + colActions + '" class="actions-cell">' +
				'<button type="button" class="azc-btn azc-btn--secondary azc-btn--small azc-seat-remove" data-user-id="' + escapeAttr(seat.userId) + '">' +
				escapeHtml(removeLabel) + '</button></td>';
			seatListBody.appendChild(tr);
		});
	}

	/* ── Clear-license confirmation modal ────────────────────── */

	function openClearModal() {
		if (!clearModal) {
			return;
		}
		modalReturnFocus = clearBtn || null;
		clearModal.hidden = false;
		if (clearBackdrop) {
			clearBackdrop.hidden = false;
		}
		document.body.classList.add('azc-license-modal-open');
		const focusTarget = clearCancel || clearConfirm;
		if (focusTarget) {
			focusTarget.focus();
		}
	}

	function closeClearModal() {
		if (clearModal) {
			clearModal.hidden = true;
		}
		if (clearBackdrop) {
			clearBackdrop.hidden = true;
		}
		document.body.classList.remove('azc-license-modal-open');
		if (modalReturnFocus) {
			modalReturnFocus.focus();
		}
	}

	// Keep keyboard focus inside the dialog while it is open (WCAG 2.4.3).
	document.addEventListener('keydown', (e) => {
		if (!clearModal || clearModal.hidden) {
			return;
		}
		if (e.key === 'Escape') {
			e.preventDefault();
			closeClearModal();
			return;
		}
		if (e.key === 'Tab') {
			const focusables = [clearCancel, clearConfirm].filter(Boolean);
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
			} else if (!focusables.includes(document.activeElement)) {
				e.preventDefault();
				first.focus();
			}
		}
	});

	/* ── Save / clear license ────────────────────────────────── */

	if (saveBtn && keyInput) {
		saveBtn.addEventListener('click', async () => {
			// Keys copied from e-mails often contain line wraps — remove all
			// whitespace before validating/sending (server does the same).
			const key = keyInput.value.replace(/\s+/g, '');
			if (!key) {
				showFeedback(t('emptyKey', 'Please paste a license key.'), 'error');
				keyInput.focus();
				return;
			}
			hideFeedback();
			saveBtn.disabled = true;
			const originalLabel = saveBtn.textContent;
			saveBtn.textContent = t('saving', 'Saving…');
			try {
				const res = await fetch(apiLicense, {
					method: 'POST',
					headers: headers(),
					body: JSON.stringify({ licenseKey: key }),
				});
				const data = await res.json();
				if (data.ok) {
					// Keep the button disabled: the page is about to reload and
					// re-enabling it would open a double-submit window.
					setFlashAndReload(t('saveSuccess', 'License saved successfully.'));
					return;
				}
				showFeedback(data.message || t('saveFailed', 'Could not save license.'), 'error');
			} catch {
				showFeedback(t('networkError', 'Network error. Please try again.'), 'error');
			}
			saveBtn.disabled = false;
			saveBtn.textContent = originalLabel;
		});
	}

	if (clearBtn) {
		clearBtn.addEventListener('click', () => openClearModal());
	}

	if (clearCancel) {
		clearCancel.addEventListener('click', () => closeClearModal());
	}

	if (clearBackdrop) {
		clearBackdrop.addEventListener('click', () => closeClearModal());
	}

	if (clearConfirm) {
		clearConfirm.addEventListener('click', async () => {
			closeClearModal();
			if (clearBtn) {
				clearBtn.disabled = true;
			}
			try {
				const res = await fetch(apiClearLicense, {
					method: 'DELETE',
					headers: headers(),
				});
				const data = await res.json();
				if (data.ok) {
					setFlashAndReload(t('clearSuccess', 'License removed.'));
					return;
				}
				showFeedback(data.message || t('clearFailed', 'Could not remove license.'), 'error');
			} catch {
				showFeedback(t('networkError', 'Network error. Please try again.'), 'error');
			}
			if (clearBtn) {
				clearBtn.disabled = false;
			}
		});
	}

	/* ── Seat removal ────────────────────────────────────────── */

	if (seatListBody) {
		seatListBody.addEventListener('click', async (ev) => {
			const btn = ev.target.closest('.azc-seat-remove');
			if (!btn) {
				return;
			}
			const userId = btn.dataset.userId;
			if (!userId) {
				return;
			}
			const confirmed = Utils.confirmDestructiveAction
				? await Utils.confirmDestructiveAction({
					title: t('removeSeatTitle', 'Remove mobile seat'),
					message: t('removeSeatConfirm', 'Remove mobile seat for this employee?'),
					confirmLabel: t('removeSeat', 'Remove seat'),
					cancelLabel: t('Cancel', 'Cancel'),
					variant: 'danger',
				})
				: null;
			if (!confirmed) {
				return;
			}
			btn.disabled = true;
			hideFeedback();
			try {
				const res = await fetch(apiRemoveSeat, {
					method: 'POST',
					headers: headers(),
					body: JSON.stringify({ userId }),
				});
				const data = await res.json();
				if (data.ok) {
					renderSeatRows(data.seats);
					updateSeatCounts(data.mobileSeatsUsed ?? 0, data.mobileSeatsLimit ?? 0);
					showFeedback(t('seatRemoved', 'Seat removed.'), 'success');
				} else {
					showFeedback(data.message || t('removeFailed', 'Could not remove seat.'), 'error');
				}
			} catch {
				showFeedback(t('networkError', 'Network error. Please try again.'), 'error');
			} finally {
				btn.disabled = false;
			}
		});
	}

	/* ── Seat assignment (searchable combobox) ───────────────── */

	async function assignSeat(userId) {
		closeSearchResults();
		hideFeedback();
		try {
			const res = await fetch(apiSeats, {
				method: 'POST',
				headers: headers(),
				body: JSON.stringify({ userId }),
			});
			const data = await res.json();
			if (data.ok) {
				renderSeatRows(data.seats);
				updateSeatCounts(data.mobileSeatsUsed ?? 0, data.mobileSeatsLimit ?? 0);
				if (userSearch) {
					userSearch.value = '';
					userSearch.focus();
				}
				showFeedback(t('seatAssigned', 'Seat assigned.'), 'success');
			} else {
				showFeedback(data.message || t('assignFailed', 'Could not assign seat.'), 'error');
			}
		} catch {
			showFeedback(t('networkError', 'Network error. Please try again.'), 'error');
		}
	}

	function closeSearchResults() {
		if (!searchResults || !userSearch) {
			return;
		}
		searchResults.hidden = true;
		searchResults.innerHTML = '';
		userSearch.setAttribute('aria-expanded', 'false');
		userSearch.removeAttribute('aria-activedescendant');
		searchActiveIndex = -1;
	}

	function getSearchOptions() {
		return searchResults ? searchResults.querySelectorAll('li[role="option"]') : [];
	}

	// Keyboard focus stays in the input; the highlighted option is conveyed
	// via aria-activedescendant (WAI-ARIA combobox pattern).
	function highlightSearchOption(index) {
		const options = getSearchOptions();
		options.forEach((opt, i) => {
			opt.setAttribute('aria-selected', i === index ? 'true' : 'false');
			opt.classList.toggle('is-active', i === index);
		});
		const active = options[index];
		if (active && userSearch) {
			userSearch.setAttribute('aria-activedescendant', active.id);
			active.scrollIntoView({ block: 'nearest' });
		} else if (userSearch) {
			userSearch.removeAttribute('aria-activedescendant');
		}
	}

	function renderSearchResults(users) {
		if (!searchResults || !userSearch) {
			return;
		}
		searchResults.innerHTML = '';
		searchActiveIndex = -1;
		userSearch.removeAttribute('aria-activedescendant');
		if (users.length === 0) {
			const li = document.createElement('li');
			li.className = 'azc-seat-search-results__empty';
			li.textContent = t('searchNoResults', 'No matching employees found.');
			li.setAttribute('role', 'presentation');
			searchResults.appendChild(li);
		} else {
			users.forEach((u, index) => {
				const li = document.createElement('li');
				li.setAttribute('role', 'option');
				li.setAttribute('aria-selected', 'false');
				li.id = 'azc-seat-option-' + index;
				li.dataset.userId = u.id;
				li.textContent = u.displayName + ' (' + u.id + ')';
				li.addEventListener('mousedown', (e) => {
					// mousedown (not click) so the input never loses focus.
					e.preventDefault();
					assignSeat(u.id);
				});
				searchResults.appendChild(li);
			});
		}
		searchResults.hidden = false;
		userSearch.setAttribute('aria-expanded', 'true');
	}

	if (userSearch && searchResults) {
		userSearch.addEventListener('input', () => {
			clearTimeout(searchTimer);
			const q = userSearch.value.trim();
			if (q.length < 2) {
				closeSearchResults();
				return;
			}
			searchTimer = setTimeout(async () => {
				// Abort the previous in-flight search so a slow older response
				// can never overwrite the results of a newer query.
				if (searchAbort) {
					searchAbort.abort();
				}
				searchAbort = new AbortController();
				try {
					const url = apiSearchUsers + '?q=' + encodeURIComponent(q);
					const res = await fetch(url, {
						headers: { requesttoken: requestToken, 'X-Requested-With': 'XMLHttpRequest' },
						signal: searchAbort.signal,
					});
					const data = await res.json();
					const users = (data.ok && data.users) ? data.users.filter((u) => !u.hasSeat) : [];
					renderSearchResults(users);
				} catch (e) {
					if (e && e.name === 'AbortError') {
						return;
					}
					closeSearchResults();
				}
			}, 250);
		});

		userSearch.addEventListener('keydown', (e) => {
			if (!searchResults || searchResults.hidden) {
				return;
			}
			const options = getSearchOptions();
			if (options.length === 0) {
				if (e.key === 'Escape') {
					closeSearchResults();
				}
				return;
			}
			if (e.key === 'ArrowDown') {
				e.preventDefault();
				searchActiveIndex = Math.min(searchActiveIndex + 1, options.length - 1);
				highlightSearchOption(searchActiveIndex);
			} else if (e.key === 'ArrowUp') {
				e.preventDefault();
				searchActiveIndex = Math.max(searchActiveIndex - 1, 0);
				highlightSearchOption(searchActiveIndex);
			} else if (e.key === 'Home') {
				e.preventDefault();
				searchActiveIndex = 0;
				highlightSearchOption(searchActiveIndex);
			} else if (e.key === 'End') {
				e.preventDefault();
				searchActiveIndex = options.length - 1;
				highlightSearchOption(searchActiveIndex);
			} else if (e.key === 'Enter' && searchActiveIndex >= 0) {
				e.preventDefault();
				const uid = options[searchActiveIndex].dataset.userId;
				if (uid) {
					assignSeat(uid);
				}
			} else if (e.key === 'Escape') {
				e.preventDefault();
				closeSearchResults();
			}
		});

		userSearch.addEventListener('blur', () => {
			// Delay so option mousedown handlers run first.
			window.setTimeout(() => {
				if (document.activeElement !== userSearch) {
					closeSearchResults();
				}
			}, 150);
		});

		document.addEventListener('click', (e) => {
			if (!userSearch.contains(e.target) && !searchResults.contains(e.target)) {
				closeSearchResults();
			}
		});
	}
})();
