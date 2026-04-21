(function () {
	'use strict';

	// ── Bootstrap ──────────────────────────────────────────────────────────────
	// Config is embedded as non-executable JSON (no CSP nonce required).
	const configEl = document.getElementById('dz-config');
	if (!configEl) {
		return;
	}

	let config;
	try {
		config = JSON.parse(configEl.textContent);
	} catch (_e) {
		return;
	}

	if (!config || typeof config !== 'object') {
		return;
	}

	// l10n is guaranteed to be an object after the guard above
	const l10n = (config.l10n && typeof config.l10n === 'object') ? config.l10n : {};

	// ── CSRF token ─────────────────────────────────────────────────────────────
	const requestToken = (() => {
		const meta = document.head.querySelector('meta[name="requesttoken"]');
		return meta ? meta.getAttribute('content') : '';
	})();

	// ── Fetch helper ───────────────────────────────────────────────────────────
	const api = (url, method = 'GET') => fetch(url, {
		method,
		headers: {
			'Accept': 'application/json',
			'Content-Type': 'application/json',
			'requesttoken': requestToken,
		},
		credentials: 'same-origin',
	});

	// ── Element references ─────────────────────────────────────────────────────
	const statusSectionEl = document.getElementById('dz-status-section');
	const statusCardEl    = document.getElementById('dz-status-card');
	const statusBadgeEl   = document.getElementById('dz-status-badge');
	const statusTextEl    = document.getElementById('dz-status-text');
	const statusIconEl    = document.getElementById('dz-status-icon');
	const workedTodayEl   = document.getElementById('dz-worked-today');
	const sessionEl       = document.getElementById('dz-session-duration');
	const feedbackEl      = document.getElementById('dz-feedback');
	const lastUpdatedEl   = document.getElementById('dz-last-updated');
	const liveStatusEl    = document.getElementById('dz-live-status');
	const managerListEl   = document.getElementById('dz-manager-list');
	const adminListEl     = document.getElementById('dz-admin-list');
	const errorEl         = document.getElementById('dz-error');
	const actionButtons   = ['dz-clock-in', 'dz-start-break', 'dz-end-break', 'dz-clock-out']
		.map((id) => document.getElementById(id))
		.filter(Boolean);

	// ── Status tracking ────────────────────────────────────────────────────────
	let lastKnown = {
		status: 'clocked_out',
		workingTodayHours: 0,
		currentSessionDuration: 0,
	};
	let mutationInFlight = false;

	// ── Helpers ────────────────────────────────────────────────────────────────
	const statusLabel = (status) => {
		switch (status) {
			case 'active': return l10n.working    || 'Working';
			case 'break':  return l10n.onBreak    || 'On Break';
			case 'paused': return l10n.paused     || 'Paused';
			case 'completed': return l10n.clockedOut || 'Clocked Out';
			default:       return l10n.clockedOut || 'Clocked Out';
		}
	};

	const formatDuration = (seconds) => {
		const s = Math.max(0, Math.floor(Number(seconds) || 0));
		const h = Math.floor(s / 3600);
		const m = Math.floor((s % 3600) / 60);
		return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
	};

	const formatHours = (hours) => Number.isFinite(hours) ? hours.toFixed(2) : '0.00';

	const statusIcon = (status) => {
		switch (status) {
			case 'active': return '●';
			case 'break': return '⏸';
			case 'paused': return '⏸';
			default: return '○';
		}
	};

	const formatTime = (date) => new Intl.DateTimeFormat(undefined, {
		hour: '2-digit',
		minute: '2-digit',
		second: '2-digit',
	}).format(date);

	const showFeedback = (message) => {
		if (!feedbackEl) {
			return;
		}
		feedbackEl.textContent = message;
		feedbackEl.removeAttribute('hidden');
	};

	const hideFeedback = () => {
		if (!feedbackEl) {
			return;
		}
		feedbackEl.setAttribute('hidden', '');
	};

	const announce = (message) => {
		if (!liveStatusEl || !message) {
			return;
		}
		liveStatusEl.textContent = '';
		window.setTimeout(() => {
			liveStatusEl.textContent = message;
		}, 10);
	};

	const updateLastRefreshed = () => {
		if (!lastUpdatedEl) {
			return;
		}
		const template = l10n.lastUpdated || 'Last updated: %1$s';
		lastUpdatedEl.textContent = template.replace('%1$s', formatTime(new Date()));
	};

	// Normalise status data regardless of whether it comes from the employee
	// widget API (camelCase) or a raw clock-action response (snake_case).
	const normaliseStatus = (raw) => {
		if (!raw || typeof raw !== 'object') {
			return { status: 'clocked_out', workingTodayHours: 0, currentSessionDuration: 0 };
		}
		return {
			status: String(raw.status ?? 'clocked_out'),
			workingTodayHours: parseFloat(
				raw.workingTodayHours ?? raw.working_today_hours ?? 0
			),
			currentSessionDuration: parseInt(
				raw.currentSessionDuration ?? raw.current_session_duration ?? 0,
				10
			),
		};
	};

	// ── Loading state ──────────────────────────────────────────────────────────
	const setLoading = (loading) => {
		if (statusSectionEl) {
			statusSectionEl.setAttribute('aria-busy', loading ? 'true' : 'false');
		}
	};

	// ── Error display ──────────────────────────────────────────────────────────
	const showError = (message) => {
		if (!errorEl) {
			return;
		}
		errorEl.textContent = message;
		errorEl.removeAttribute('hidden');
	};

	const hideError = () => {
		if (!errorEl) {
			return;
		}
		errorEl.setAttribute('hidden', '');
	};

	const setButtonsLocked = (locked) => {
		actionButtons.forEach((btn) => {
			btn.disabled = locked;
			btn.setAttribute('aria-disabled', locked ? 'true' : 'false');
			btn.classList.toggle('btn--loading', locked);
		});
	};

	const BUTTON_STATES = {
		clocked_out: { 'dz-clock-in': true,  'dz-start-break': false, 'dz-end-break': false, 'dz-clock-out': false },
		active:      { 'dz-clock-in': false, 'dz-start-break': true,  'dz-end-break': false, 'dz-clock-out': true  },
		break:       { 'dz-clock-in': false, 'dz-start-break': false, 'dz-end-break': true,  'dz-clock-out': true },
		paused:      { 'dz-clock-in': true,  'dz-start-break': false, 'dz-end-break': false, 'dz-clock-out': true  },
		completed:   { 'dz-clock-in': true,  'dz-start-break': false, 'dz-end-break': false, 'dz-clock-out': false },
	};

	const updateButtonStates = (status) => {
		if (mutationInFlight) {
			setButtonsLocked(true);
			return;
		}
		const states = BUTTON_STATES[status] ?? BUTTON_STATES.clocked_out;
		Object.entries(states).forEach(([id, enabled]) => {
			const btn = document.getElementById(id);
			if (!btn) {
				return;
			}
			btn.disabled = !enabled;
			btn.setAttribute('aria-disabled', enabled ? 'false' : 'true');
		});
	};

	// ── Render functions ───────────────────────────────────────────────────────
	const renderEmployee = (rawData) => {
		const data = normaliseStatus(rawData);
		const { status, workingTodayHours, currentSessionDuration } = data;
		lastKnown = data;

		// Status badge: visual indicator with data-status for CSS colour coding
		if (statusBadgeEl) {
			statusBadgeEl.dataset.status = status;
			statusBadgeEl.textContent    = statusLabel(status);
		}

		if (statusTextEl) {
			const template = l10n.statusLine || 'Status: %1$s';
			statusTextEl.textContent = template
				.replace('%1$s', statusLabel(status));
		}

		if (statusIconEl) {
			statusIconEl.textContent = statusIcon(status);
		}

		if (workedTodayEl) {
			workedTodayEl.textContent = `${formatHours(workingTodayHours)} h`;
		}

		if (sessionEl) {
			sessionEl.textContent = formatDuration(currentSessionDuration);
		}

		if (statusCardEl) {
			statusCardEl.dataset.status = status;
		}

		updateButtonStates(status);
	};

	// Clears and re-renders a people list (team or company overview).
	const renderPeopleList = (target, rows, emptyMsg) => {
		if (!target) {
			return;
		}

		// Clear previous content safely (no innerHTML)
		while (target.firstChild) {
			target.removeChild(target.firstChild);
		}

		if (!Array.isArray(rows) || rows.length === 0) {
			const msg = document.createElement('p');
			msg.className   = 'dz-empty';
			msg.textContent = emptyMsg || l10n.noEntriesFound || 'No entries found.';
			target.appendChild(msg);
			return;
		}

		const list = document.createElement('ul');
		list.className = 'dz-list';
		list.setAttribute('role', 'list');

		rows.forEach((row) => {
			const item = document.createElement('li');
			item.className    = 'dz-list-item';
			item.dataset.status = String(row.status || 'clocked_out');

			// Badge
			const badge = document.createElement('span');
			badge.className       = 'dz-badge';
			badge.dataset.status  = item.dataset.status;
			badge.setAttribute('aria-hidden', 'true');
			badge.textContent     = statusLabel(item.dataset.status);

			// Text: "Alice: Working (3.25 h)"
			const text = document.createElement('span');
			text.className = 'dz-list-item__text';
			const template = l10n.peopleRow || '%1$s: %2$s (%3$s h)';
			text.textContent = template
				.replace('%1$s', String(row.displayName || ''))
				.replace('%2$s', statusLabel(item.dataset.status))
				.replace('%3$s', parseFloat(row.workingTodayHours || 0).toFixed(2));

			// Accessible label for screen readers (badge text is hidden)
			item.setAttribute('aria-label',
				String(row.displayName || '') + ': ' + statusLabel(item.dataset.status));

			item.appendChild(badge);
			item.appendChild(text);
			list.appendChild(item);
		});

		target.appendChild(list);
	};

	// ── Data loading ───────────────────────────────────────────────────────────
	const loadData = async () => {
		setLoading(true);
		if (!mutationInFlight) {
			hideError();
		}

		// Employee status (always loaded)
		try {
			const resp = await api(config.employeeDataUrl);
			if (resp.status === 401) {
				showError(l10n.sessionExpired ||
					'Your session has expired. Please refresh the page and try again.');
				setLoading(false);
				return;
			}
			if (resp.ok) {
				const json = await resp.json();
				if (json.success && json.data) {
					renderEmployee(json.data);
					updateLastRefreshed();
				}
			}
		} catch (_e) {
			if (!mutationInFlight) {
				showError(l10n.networkError ||
					'Could not load status. Please check your connection.');
			}
		}

		// Team overview (manager)
		if (config.isManager && managerListEl) {
			try {
				const resp = await api(config.managerDataUrl);
				if (resp.ok) {
					const json = await resp.json();
					if (json.success && json.data) {
						renderPeopleList(
							managerListEl,
							json.data.members || [],
							l10n.noTeamMembers || 'No team members found.'
						);
					}
				}
			} catch (_e) {
				// Non-critical: silently leave section empty
			}
		}

		// Company overview (admin)
		if (config.isAdmin && adminListEl) {
			try {
				const resp = await api(config.adminDataUrl);
				if (resp.ok) {
					const json = await resp.json();
					if (json.success && json.data) {
						renderPeopleList(
							adminListEl,
							json.data.users || [],
							l10n.noUsersFound || 'No users found.'
						);
					}
				}
			} catch (_e) {
				// Non-critical: silently leave section empty
			}
		}

		setLoading(false);
	};

	// ── Action wiring ──────────────────────────────────────────────────────────
	const wireAction = (id, url) => {
		const btn = document.getElementById(id);
		if (!btn) {
			return;
		}

		btn.addEventListener('click', async () => {
			if (btn.disabled || mutationInFlight) {
				return;
			}

			mutationInFlight = true;
			setButtonsLocked(true);
			hideError();
			hideFeedback();

			try {
				const resp = await api(url, 'POST');
				const json = await resp.json();

				if (!resp.ok || !json.success) {
					const errMsg = json.error || l10n.actionFailed || 'Action failed';
					if (window.OC?.dialogs?.alert) {
						window.OC.dialogs.alert(errMsg, l10n.errorTitle || 'ArbeitszeitCheck');
					} else {
						showError(errMsg);
					}
					announce(errMsg);
					updateButtonStates(lastKnown.status);
					return;
				}

				if (json.status && typeof json.status === 'object') {
					renderEmployee(json.status);
					updateLastRefreshed();
				} else {
					await loadData();
				}

				const actionLabel = btn.textContent ? btn.textContent.trim() : (l10n.actionDone || 'Action');
				const doneTemplate = l10n.actionDone || '%1$s successful';
				const successMsg = doneTemplate.replace('%1$s', actionLabel);
				showFeedback(successMsg);
				announce(successMsg);
			} catch (_e) {
				showError(l10n.networkError ||
					'Could not load status. Please check your connection.');
				announce(l10n.networkError || 'Could not load status. Please check your connection.');
				updateButtonStates(lastKnown.status);
			} finally {
				mutationInFlight = false;
				updateButtonStates(lastKnown.status);
			}
		});
	};

	wireAction('dz-clock-in',    config.clockInUrl);
	wireAction('dz-start-break', config.startBreakUrl);
	wireAction('dz-end-break',   config.endBreakUrl);
	wireAction('dz-clock-out',   config.clockOutUrl);

	// Initial load + periodic refresh
	loadData();
	const refreshTimer = setInterval(loadData, 30000);

	// Clean up interval on page unload to prevent orphaned timers
	window.addEventListener('beforeunload', () => {
		clearInterval(refreshTimer);
	});
})();
