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

	const apiLicense = page.dataset.apiLicense || '';
	const apiClearLicense = page.dataset.apiClearLicense || '';
	const apiSeats = page.dataset.apiSeats || '';
	const apiRemoveSeat = page.dataset.apiRemoveSeat || '';
	const apiSearchUsers = page.dataset.apiSearchUsers || '';
	const requestToken = page.dataset.requesttoken || '';
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
	const userSearch = document.getElementById('azc-seat-user-search');
	const searchResults = document.getElementById('azc-seat-search-results');
	const clearBackdrop = document.getElementById('azc-license-clear-backdrop');
	const clearModal = document.getElementById('azc-license-clear-modal');
	const clearCancel = document.getElementById('azc-license-clear-cancel');
	const clearConfirm = document.getElementById('azc-license-clear-confirm');

	let searchTimer = null;
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
		return String(s).replace(/"/g, '&quot;');
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

	function updateOverview(data) {
		const lic = data.license || data;
		const mobileUsed = data.mobileSeatsUsed ?? 0;
		const mobileLimit = data.mobileSeatsLimit ?? lic.mobileSeats ?? 0;
		const terminalUsed = data.terminalDevicesUsed ?? 0;
		const terminalLimit = data.terminalDevicesLimit ?? lic.terminalDevices ?? 0;

		updateMeter(
			document.getElementById('azc-license-mobile-used'),
			document.getElementById('azc-license-mobile-limit'),
			document.getElementById('azc-license-mobile-meter'),
			mobileUsed,
			mobileLimit,
		);
		updateMeter(
			document.getElementById('azc-license-terminal-used'),
			document.getElementById('azc-license-terminal-limit'),
			document.getElementById('azc-license-terminal-meter'),
			terminalUsed,
			terminalLimit,
		);

		if (seatCount && mobileLimit > 0) {
			seatCount.textContent = mobileUsed + ' / ' + mobileLimit;
		}

		if (userSearch) {
			const full = mobileLimit > 0 && mobileUsed >= mobileLimit;
			userSearch.disabled = full;
			userSearch.setAttribute('aria-disabled', full ? 'true' : 'false');
		}
	}

	function updateStatus(data) {
		if (!data) {
			return;
		}
		if (statusPanel) {
			statusPanel.hidden = false;
		}
		const lic = data.license || data;
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
		updateOverview(data);
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
		seats.forEach((seat) => {
			const tr = document.createElement('tr');
			tr.dataset.userId = seat.userId;
			tr.innerHTML =
				'<td data-label="Employee">' + escapeHtml(seat.displayName) + '</td>' +
				'<td data-label="User ID"><code class="azc-license-user-id">' + escapeHtml(seat.userId) + '</code></td>' +
				'<td data-label="Assigned">' + escapeHtml(formatAssignedAt(seat.assignedAt)) + '</td>' +
				'<td data-label="Actions" class="actions-cell">' +
				'<button type="button" class="azc-btn azc-btn--secondary azc-btn--small azc-seat-remove" data-user-id="' + escapeAttr(seat.userId) + '">' +
				escapeHtml(removeLabel) + '</button></td>';
			seatListBody.appendChild(tr);
		});
	}

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

	function closeSearchResults() {
		if (!searchResults || !userSearch) {
			return;
		}
		searchResults.hidden = true;
		searchResults.innerHTML = '';
		userSearch.setAttribute('aria-expanded', 'false');
		searchActiveIndex = -1;
	}

	function highlightSearchOption(index) {
		if (!searchResults) {
			return;
		}
		const buttons = searchResults.querySelectorAll('button[role="option"]');
		buttons.forEach((btn, i) => {
			btn.setAttribute('aria-selected', i === index ? 'true' : 'false');
		});
		if (buttons[index]) {
			buttons[index].focus();
		}
	}

	if (saveBtn && keyInput) {
		saveBtn.addEventListener('click', async () => {
			const key = keyInput.value.trim();
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
					showFeedback(t('saveSuccess', 'License saved successfully.'), 'success');
					window.location.reload();
				} else {
					showFeedback(data.message || t('saveFailed', 'Could not save license.'), 'error');
				}
			} catch {
				showFeedback(t('networkError', 'Network error. Please try again.'), 'error');
			} finally {
				saveBtn.disabled = false;
				saveBtn.textContent = originalLabel;
			}
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
					showFeedback(t('clearSuccess', 'License removed.'), 'success');
					window.location.reload();
				} else {
					showFeedback(data.message || t('clearFailed', 'Could not remove license.'), 'error');
				}
			} catch {
				showFeedback(t('networkError', 'Network error. Please try again.'), 'error');
			} finally {
				if (clearBtn) {
					clearBtn.disabled = false;
				}
			}
		});
	}

	document.addEventListener('keydown', (e) => {
		if (clearModal && !clearModal.hidden && e.key === 'Escape') {
			e.preventDefault();
			closeClearModal();
		}
	});

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
			if (!window.confirm(t('removeSeatConfirm', 'Remove mobile seat for this employee?'))) {
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
					updateStatus(data);
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
				updateStatus(data);
				if (userSearch) {
					userSearch.value = '';
				}
				showFeedback(t('seatAssigned', 'Seat assigned.'), 'success');
			} else {
				showFeedback(data.message || t('assignFailed', 'Could not assign seat.'), 'error');
			}
		} catch {
			showFeedback(t('networkError', 'Network error. Please try again.'), 'error');
		}
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
				try {
					const url = apiSearchUsers + '?q=' + encodeURIComponent(q);
					const res = await fetch(url, { headers: { requesttoken: requestToken } });
					const data = await res.json();
					searchResults.innerHTML = '';
					searchActiveIndex = -1;
					const users = (data.ok && data.users) ? data.users.filter((u) => !u.hasSeat) : [];
					if (users.length === 0) {
						const li = document.createElement('li');
						li.className = 'azc-seat-search-results__empty';
						li.textContent = t('searchNoResults', 'No matching employees found.');
						li.setAttribute('role', 'presentation');
						searchResults.appendChild(li);
						searchResults.hidden = false;
						userSearch.setAttribute('aria-expanded', 'true');
						return;
					}
					users.forEach((u, index) => {
						const li = document.createElement('li');
						li.setAttribute('role', 'presentation');
						const btn = document.createElement('button');
						btn.type = 'button';
						btn.setAttribute('role', 'option');
						btn.id = 'azc-seat-option-' + index;
						btn.textContent = u.displayName + ' (' + u.id + ')';
						btn.dataset.userId = u.id;
						btn.addEventListener('click', () => assignSeat(u.id));
						li.appendChild(btn);
						searchResults.appendChild(li);
					});
					searchResults.hidden = false;
					userSearch.setAttribute('aria-expanded', 'true');
				} catch {
					closeSearchResults();
				}
			}, 250);
		});

		userSearch.addEventListener('keydown', (e) => {
			if (!searchResults || searchResults.hidden) {
				return;
			}
			const options = searchResults.querySelectorAll('button[role="option"]');
			if (options.length === 0) {
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
			} else if (e.key === 'Enter' && searchActiveIndex >= 0) {
				e.preventDefault();
				const uid = options[searchActiveIndex].dataset.userId;
				if (uid) {
					assignSeat(uid);
				}
			} else if (e.key === 'Escape') {
				closeSearchResults();
			}
		});

		document.addEventListener('click', (e) => {
			if (!userSearch.contains(e.target) && !searchResults.contains(e.target)) {
				closeSearchResults();
			}
		});
	}
})();
