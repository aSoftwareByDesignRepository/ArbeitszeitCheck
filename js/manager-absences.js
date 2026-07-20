(function () {
	'use strict';

	const Utils = window.ArbeitszeitCheckUtils || {};
	const Messaging = window.ArbeitszeitCheckMessaging || {};
	const state = {
		limit: 25,
		offset: 0,
		total: 0,
		lastFilters: null,
		loading: false,
		countBeforeLoad: '',
		dateLocale: window.ArbeitszeitCheck?.dateLocale || document.documentElement.lang || undefined,
	};

	function t(key, fallback, vars) {
		const bundle = window.ArbeitszeitCheck?.l10n || {};
		const value = bundle[key];
		if (value !== undefined && value !== '') {
			if (vars && typeof vars === 'object') {
				return String(value).replace(/\{(\w+)\}/g, (_, name) => (
					Object.prototype.hasOwnProperty.call(vars, name) ? String(vars[name]) : '{' + name + '}'
				));
			}
			return value;
		}
		const msgid = fallback || key;
		if (typeof window.t === 'function') {
			return window.t('arbeitszeitcheck', msgid, vars || {});
		}
		if (vars && typeof vars === 'object') {
			return String(msgid).replace(/\{(\w+)\}/g, (_, name) => (
				Object.prototype.hasOwnProperty.call(vars, name) ? String(vars[name]) : '{' + name + '}'
			));
		}
		return msgid;
	}

	function escapeHtml(value) {
		if (value === null || value === undefined) {
			return '';
		}
		const div = document.createElement('div');
		div.textContent = String(value);
		return div.innerHTML;
	}

	function formatDate(value) {
		if (!value) {
			return '-';
		}
		const ymd = String(value).slice(0, 10);
		const api = window.ArbeitszeitCheckTime;
		if (api && /^\d{4}-\d{2}-\d{2}$/.test(ymd)) {
			const parsed = api.parseYmd(ymd);
			return parsed ? api.formatDate(parsed) : '-';
		}
		if (Utils.formatDate) {
			return Utils.formatDate(ymd, 'DD.MM.YYYY') || '-';
		}
		return ymd;
	}

	function formatDays(value) {
		if (value === null || value === undefined || value === '') {
			return '-';
		}
		const num = Number(value);
		if (Number.isNaN(num)) {
			return '-';
		}
		return num.toFixed(1);
	}

	function isPastRecord(entry) {
		const rawEnd = entry && (entry.endDate || entry.end_date || entry.startDate || entry.start_date);
		if (!rawEnd) {
			return false;
		}
		const api = window.ArbeitszeitCheckTime;
		const endYmd = String(rawEnd).slice(0, 10);
		const todayYmd = api ? api.todayYmd() : '';
		if (api && /^\d{4}-\d{2}-\d{2}$/.test(endYmd) && todayYmd) {
			return endYmd < todayYmd;
		}
		return false;
	}

	function setFilterErrorText(errorEl, message) {
		if (!errorEl) {
			return;
		}
		const inner = errorEl.querySelector('.azc-callout__text');
		if (inner) {
			inner.textContent = message;
		} else {
			errorEl.textContent = message;
		}
		errorEl.hidden = !message;
	}

	function setResultsPanel(options) {
		const emptyEl = document.getElementById('employee-absences-empty');
		const tableWrap = document.getElementById('employee-absences-table-wrap');
		const body = document.getElementById('employee-absences-body');
		if (!emptyEl || !tableWrap) {
			return;
		}
		const title = options?.title || '';
		const text = options?.text || '';
		const showTable = options?.showTable === true;

		if (showTable) {
			emptyEl.classList.add('visually-hidden');
			tableWrap.classList.remove('visually-hidden');
			return;
		}

		emptyEl.classList.remove('visually-hidden');
		tableWrap.classList.add('visually-hidden');
		if (body) {
			body.innerHTML = '';
		}
		const titleEl = emptyEl.querySelector('.azc-empty-state__title')
			|| emptyEl.querySelector('.empty-state__title');
		const desc = emptyEl.querySelector('.azc-empty-state__text')
			|| emptyEl.querySelector('.empty-state__description');
		if (titleEl && title) {
			titleEl.textContent = title;
		}
		if (desc) {
			desc.textContent = text;
		}
	}

	function setEmpty(title, text) {
		setResultsPanel({
			title: title || t('Select filters first', 'Select filters first'),
			text: text || t('Choose a date range to load absences.', 'Choose a date range to load absences.'),
		});
	}

	function setLoadingResultsPanel() {
		setResultsPanel({
			title: t('Loading results…', 'Loading results…'),
			text: t('Loading...', 'Loading...'),
		});
	}

	function renderEntries(entries) {
		const body = document.getElementById('employee-absences-body');
		const emptyEl = document.getElementById('employee-absences-empty');
		const tableWrap = document.getElementById('employee-absences-table-wrap');
		if (!body || !emptyEl || !tableWrap) {
			return;
		}

		if (!entries.length) {
			setResultsPanel({
				title: t('No matching absences', 'No matching absences'),
				text: t('No entries found for the selected filters.', 'No entries found for the selected filters.'),
			});
			body.innerHTML = '';
			return;
		}

		body.innerHTML = entries.map((entry) => {
			const pastBadge = isPastRecord(entry)
				? ` <span class="badge badge--past-record">${escapeHtml(t('Past record', 'Past record'))}</span>`
				: '';
			const statusLabel = entry.statusLabel || entry.status || '-';
			const statusVariant = Utils.badgeVariantForAbsenceStatus
				? Utils.badgeVariantForAbsenceStatus(entry.status)
				: 'secondary';
			const statusBadge = Utils.renderBadgeHtml
				? Utils.renderBadgeHtml(statusLabel, statusVariant)
				: `<span class="badge badge--${escapeHtml(statusVariant)}">${escapeHtml(statusLabel)}</span>`;
			const td = (label, html, cls = '') => Utils.responsiveTd
				? Utils.responsiveTd(label, html, cls)
				: `<td${cls ? ` class="${cls}"` : ''}>${html}</td>`;
			return [
			'<tr>',
			td(t('Name', 'Name'), escapeHtml(entry.displayName || entry.userId || '-')),
			td(t('Type', 'Type'), escapeHtml(entry.typeLabel || entry.type || '-')),
			td(t('Start date', 'Start date'), escapeHtml(formatDate(entry.startDate))),
			td(t('End date', 'End date'), escapeHtml(formatDate(entry.endDate))),
			td(t('Days', 'Days'), escapeHtml(formatDays(entry.days))),
			td(t('Status', 'Status'), `${statusBadge}${pastBadge}`),
			td(t('Reason', 'Reason'), escapeHtml(entry.reason || t('No reason', 'No reason')), 'reason-cell'),
			'</tr>',
			].join('');
		}).join('');

		setResultsPanel({ showTable: true });
	}

	function updatePagination() {
		const prevBtn = document.getElementById('employee-absences-prev');
		const nextBtn = document.getElementById('employee-absences-next');
		const indicator = document.getElementById('employee-absences-page-indicator');
		const currentPage = Math.floor(state.offset / state.limit) + 1;
		const totalPages = Math.max(1, Math.ceil(state.total / state.limit));

		if (indicator) {
			indicator.textContent = t('Page {page} of {pages}', 'Page {page} of {pages}')
				.replace('{page}', String(currentPage))
				.replace('{pages}', String(totalPages));
		}
		if (prevBtn) {
			prevBtn.disabled = state.offset <= 0;
		}
		if (nextBtn) {
			nextBtn.disabled = state.offset + state.limit >= state.total;
		}
	}

	function updateCount() {
		const countEl = document.getElementById('employee-absences-count');
		if (!countEl) {
			return;
		}
		if (state.total <= 0 && !state.lastFilters) {
			countEl.textContent = '';
			state.countBeforeLoad = '';
			return;
		}
		const text = t('{count} entries', '{count} entries', { count: state.total });
		countEl.textContent = text;
		state.countBeforeLoad = text;
	}

	function setLoading(isLoading) {
		state.loading = isLoading;
		const results = document.querySelector('.manager-scope-page__results');
		if (results) {
			results.setAttribute('aria-busy', isLoading ? 'true' : 'false');
		}
		const submitBtn = document.getElementById('employee-absences-submit');
		const clearBtn = document.getElementById('employee-absences-clear');
		const prevBtn = document.getElementById('employee-absences-prev');
		const nextBtn = document.getElementById('employee-absences-next');
		if (submitBtn) {
			submitBtn.disabled = isLoading;
			submitBtn.setAttribute('aria-disabled', isLoading ? 'true' : 'false');
		}
		if (clearBtn) {
			clearBtn.disabled = isLoading;
		}
		if (prevBtn) {
			prevBtn.disabled = isLoading || state.offset <= 0;
		}
		if (nextBtn) {
			nextBtn.disabled = isLoading || state.offset + state.limit >= state.total;
		}
		const countEl = document.getElementById('employee-absences-count');
		if (!countEl) {
			return;
		}
		if (isLoading) {
			state.countBeforeLoad = countEl.textContent;
			countEl.textContent = t('Loading...', 'Loading...');
		}
	}

	function clearFilterFieldErrors() {
		const form = document.getElementById('employee-absences-filter-form');
		if (!form) {
			return;
		}
		form.querySelectorAll('[aria-invalid="true"]').forEach((el) => {
			el.removeAttribute('aria-invalid');
		});
		const errorEl = document.getElementById('employee-absences-filter-error');
		setFilterErrorText(errorEl, '');
	}

	function setFilterError(message, focusId) {
		const errorEl = document.getElementById('employee-absences-filter-error');
		setFilterErrorText(errorEl, message);
		if (message && focusId) {
			const focusEl = document.getElementById(focusId);
			if (focusEl) {
				focusEl.setAttribute('aria-invalid', 'true');
				focusEl.focus();
			}
		}
	}

	function resolveLoadErrorFocus(error) {
		const fromHandler = window.ArbeitszeitCheck?.handleManagerListApiError?.(error, {
			picker: employeeFilterPicker,
			searchSelector: '#employee-absences-employee-filter-search',
			clearButtonSelector: '#employee-absences-employee-filter-clear',
			searchFocusId: 'employee-absences-employee-filter-search',
		});
		if (fromHandler) {
			return fromHandler;
		}
		if (error?.status === 400) {
			return 'employee-absences-start-date-filter';
		}
		return null;
	}

	let employeeFilterPicker = null;
	let recordEmployeePicker = null;

	function syncEmployeeFilterPicker(employees) {
		if (!employeeFilterPicker || !Array.isArray(employees)) {
			return;
		}
		const selectedId = employeeFilterPicker.getUserId();
		if (!selectedId) {
			return;
		}
		const match = employees.find((employee) => employee.userId === selectedId);
		if (match) {
			employeeFilterPicker.setSelection(match.userId, match.displayName || match.userId);
		}
	}

	function syncRecordEmployeePicker(employees) {
		if (!recordEmployeePicker || !Array.isArray(employees)) {
			return;
		}
		const selectedId = recordEmployeePicker.getUserId();
		if (!selectedId) {
			return;
		}
		const match = employees.find((employee) => employee.userId === selectedId);
		if (match) {
			recordEmployeePicker.setSelection(match.userId, match.displayName || match.userId);
		}
	}

	function initEmployeePickers() {
		const initPicker = window.ArbeitszeitCheck?.initManagerScopedEmployeePicker;
		if (!initPicker) {
			return;
		}
		if (document.getElementById('employee-absences-employee-filter-search')) {
			employeeFilterPicker = initPicker({
				hiddenSelector: '#employee-absences-employee-filter-id',
				searchSelector: '#employee-absences-employee-filter-search',
				listSelector: '#employee-absences-employee-filter-listbox',
				wrapSelector: '#employee-absences-employee-filter-wrap',
				statusSelector: '#employee-absences-employee-filter-status',
				clearButtonSelector: '#employee-absences-employee-filter-clear',
				idPrefix: 'employee-absences-employee-filter',
				allowAll: true,
			});
		}
		if (document.getElementById('manager-absence-record-employee-search')) {
			recordEmployeePicker = initPicker({
				hiddenSelector: '#manager-absence-record-employee-id',
				searchSelector: '#manager-absence-record-employee-search',
				listSelector: '#manager-absence-record-employee-listbox',
				wrapSelector: '#manager-absence-record-employee-wrap',
				statusSelector: '#manager-absence-record-employee-status',
				idPrefix: 'manager-absence-record-employee',
				allowAll: false,
			});
		}
	}

	function readFiltersFromForm() {
		const form = document.getElementById('employee-absences-filter-form');
		if (!form) {
			return null;
		}
		const formData = new FormData(form);
		const startEl = document.getElementById('employee-absences-start-date-filter');
		const endEl = document.getElementById('employee-absences-end-date-filter');
		const hiddenEl = document.getElementById('employee-absences-employee-filter-id');
		return {
			employeeId: String(formData.get('employee_id') || hiddenEl?.value || '').trim(),
			startDate: String(formData.get('start_date') || startEl?.value || '').trim(),
			endDate: String(formData.get('end_date') || endEl?.value || '').trim(),
			status: String(formData.get('status') || '').trim(),
			type: String(formData.get('type') || '').trim(),
		};
	}

	function buildQuery(filters, isoDates) {
		const startISO = isoDates?.startISO || europeanToYmd(filters.startDate);
		const endISO = isoDates?.endISO || europeanToYmd(filters.endDate);
		const params = new URLSearchParams();
		params.set('startDate', startISO);
		params.set('endDate', endISO);
		params.set('limit', String(state.limit));
		params.set('offset', String(state.offset));
		if (filters.employeeId) {
			params.set('employeeId', filters.employeeId);
		}
		if (filters.status) {
			params.set('status', filters.status);
		}
		if (filters.type) {
			params.set('type', filters.type);
		}
		return params.toString();
	}

	function loadEntries() {
		if (typeof Utils.ajax !== 'function') {
			const message = t('Could not load employee absences.', 'Could not load employee absences.');
			setFilterError(message);
			Messaging?.showError?.(message);
			setEmpty(t('Check your filters', 'Check your filters'), message);
			return;
		}

		const filters = readFiltersFromForm();
		if (!filters) {
			return;
		}

		const validation = validateFilters(filters);
		if (!validation.valid) {
			state.lastFilters = null;
			const message = document.getElementById('employee-absences-filter-error')
				?.querySelector('.azc-callout__text')?.textContent
				|| t('Choose a date range to load absences.', 'Choose a date range to load absences.');
			setEmpty(t('Check your filters', 'Check your filters'), message);
			updateCount();
			updatePagination();
			return;
		}

		if (state.loading) {
			return;
		}

		state.lastFilters = filters;
		setLoading(true);
		setLoadingResultsPanel();
		const ajaxPromise = Utils.ajax(`/apps/arbeitszeitcheck/api/manager/employee-absences?${buildQuery(filters, validation)}`, {
			method: 'GET',
			onSuccess: (data) => {
				try {
					if (!data || data.success === false) {
						const message = data?.error || t('Could not load employee absences.', 'Could not load employee absences.');
						setFilterError(message);
						Messaging?.showError?.(message);
						setEmpty(t('Check your filters', 'Check your filters'), message);
						state.total = 0;
						updateCount();
						updatePagination();
						return;
					}

					if (data.requiresFilters) {
						const message = t('Please select start and end date.', 'Please select start and end date.');
						setFilterError(
							message,
							!filters.startDate ? 'employee-absences-start-date-filter' : 'employee-absences-end-date-filter'
						);
						state.lastFilters = null;
						state.total = 0;
						setEmpty(t('Check your filters', 'Check your filters'), message);
						updateCount();
						updatePagination();
						return;
					}

					clearFilterFieldErrors();
					state.total = Number(data.total || 0);
					const list = Array.isArray(data.employees) ? data.employees : [];
					syncEmployeeFilterPicker(list);
					syncRecordEmployeePicker(list);
					renderEntries(Array.isArray(data.entries) ? data.entries : []);
					updateCount();
					state.countBeforeLoad = document.getElementById('employee-absences-count')?.textContent || '';
					updatePagination();
					syncRecordDatesFromFilter();
				} catch (err) {
					state.total = 0;
					const message = err?.message || t('Could not load employee absences.', 'Could not load employee absences.');
					setFilterError(message);
					Messaging?.showError?.(message);
					setEmpty(t('Check your filters', 'Check your filters'), message);
					updateCount();
					updatePagination();
				}
			},
			onError: (error) => {
				state.total = 0;
				const message = error?.error || error?.message || t('Could not load employee absences.', 'Could not load employee absences.');
				const focusId = resolveLoadErrorFocus(error);
				setFilterError(message, focusId);
				Messaging?.showError?.(message);
				setEmpty(t('Check your filters', 'Check your filters'), message);
				updateCount();
				updatePagination();
			},
		});
		if (ajaxPromise && typeof ajaxPromise.finally === 'function') {
			ajaxPromise.finally(() => {
				setLoading(false);
			});
		} else {
			setLoading(false);
		}
	}

	function syncRecordDatesFromFilter() {
		const fs = document.getElementById('employee-absences-start-date-filter');
		const fe = document.getElementById('employee-absences-end-date-filter');
		const rs = document.getElementById('manager-absence-record-start');
		const re = document.getElementById('manager-absence-record-end');
		if (fs && rs && fs.value) {
			rs.value = fs.value;
		}
		if (fe && re && fe.value) {
			re.value = fe.value;
		}
	}

	/* Past-date awareness for the manager record form.
	 *
	 * Mirrors the behaviour of the employee absence form: when both dates
	 * have been entered and the end date is strictly before today (local
	 * time, matching the visible datepicker), reveal an aria-live hint so
	 * the manager has clear feedback that they are recording a historical
	 * entry. Submission semantics are unchanged - the manager API always
	 * persists as APPROVED - but the visible cue helps prevent typos like
	 * 2024 vs 2025 going unnoticed.
	 */
	function europeanToYmd(value) {
		if (!value || !/^\d{2}\.\d{2}\.\d{4}$/.test(value)) {
			return '';
		}
		const parts = String(value).split('.');
		return parts[2] + '-' + parts[1] + '-' + parts[0];
	}

	function parseEuropeanDate(value) {
		if (!value || !/^\d{2}\.\d{2}\.\d{4}$/.test(value)) {
			return null;
		}
		const parts = String(value).split('.');
		const date = new Date(
			parseInt(parts[2], 10),
			parseInt(parts[1], 10) - 1,
			parseInt(parts[0], 10)
		);
		if (
			Number.isNaN(date.getTime())
			|| date.getFullYear() !== parseInt(parts[2], 10)
			|| date.getMonth() !== parseInt(parts[1], 10) - 1
			|| date.getDate() !== parseInt(parts[0], 10)
		) {
			return null;
		}
		return date;
	}

	function validateFilters(filters) {
		clearFilterFieldErrors();
		const employeeCheck = window.ArbeitszeitCheck?.validateManagerFilterEmployeeSelection?.(
			employeeFilterPicker,
			'#employee-absences-employee-filter-search',
			'#employee-absences-employee-filter-id'
		);
		if (employeeCheck && !employeeCheck.valid) {
			setFilterError(employeeCheck.message, employeeCheck.focusId);
			return { valid: false };
		}
		if (!filters.startDate || !filters.endDate) {
			const message = t('Please select start and end date.', 'Please select start and end date.');
			setFilterError(
				message,
				!filters.startDate ? 'employee-absences-start-date-filter' : 'employee-absences-end-date-filter'
			);
			return { valid: false };
		}
		const startParsed = parseEuropeanDate(filters.startDate);
		const endParsed = parseEuropeanDate(filters.endDate);
		if (!startParsed || !endParsed) {
			const message = t(
				'Invalid date format. Please use dd.mm.yyyy (e.g., 15.01.2024).',
				'Invalid date format. Please use dd.mm.yyyy (e.g., 15.01.2024).'
			);
			setFilterError(
				message,
				!startParsed ? 'employee-absences-start-date-filter' : 'employee-absences-end-date-filter'
			);
			return { valid: false };
		}
		if (startParsed > endParsed) {
			const message = t(
				'Invalid date range. The start date must be before the end date.',
				'Invalid date range. The start date must be before the end date.'
			);
			setFilterError(message, 'employee-absences-start-date-filter');
			return { valid: false };
		}
		const dp = window.ArbeitszeitCheckDatepicker;
		const toISO = dp && typeof dp.convertEuropeanToISO === 'function'
			? dp.convertEuropeanToISO
			: europeanToYmd;
		const startISO = toISO(filters.startDate);
		const endISO = toISO(filters.endDate);
		if (!/^\d{4}-\d{2}-\d{2}$/.test(startISO) || !/^\d{4}-\d{2}-\d{2}$/.test(endISO)) {
			const message = t(
				'Invalid date range. Please use valid dates in YYYY-MM-DD format.',
				'Invalid date range. Please use valid dates in YYYY-MM-DD format.'
			);
			setFilterError(message, 'employee-absences-start-date-filter');
			return { valid: false };
		}
		const maxDays = Number(window.ArbeitszeitCheck?.maxManagerListDateRangeDays) || 365;
		const spanDays = Math.round((endParsed.getTime() - startParsed.getTime()) / 86400000) + 1;
		if (spanDays > maxDays) {
			const message = t('dateRangeTooLong', 'Date range must not exceed %d days. Please narrow the range.')
				.replace('%d', String(maxDays));
			setFilterError(message, 'employee-absences-start-date-filter');
			return { valid: false };
		}
		return { valid: true, startISO, endISO };
	}

	function updateManagerRecordHistoricalHint() {
		const hint = document.getElementById('manager-absence-record-historical-hint');
		const endEl = document.getElementById('manager-absence-record-end');
		if (!hint || !endEl) {
			return;
		}
		const end = parseEuropeanDate(endEl.value);
		if (!end) {
			hint.hidden = true;
			return;
		}
		const endYmd = europeanToYmd(endEl.value);
		const todayYmd = window.ArbeitszeitCheckTime
			? window.ArbeitszeitCheckTime.todayYmd()
			: '';
		hint.hidden = !(endYmd && todayYmd && endYmd < todayYmd);
	}

	function bindRecordHistoricalHint() {
		const startEl = document.getElementById('manager-absence-record-start');
		const endEl = document.getElementById('manager-absence-record-end');
		if (startEl) {
			startEl.addEventListener('change', updateManagerRecordHistoricalHint);
			startEl.addEventListener('input', updateManagerRecordHistoricalHint);
		}
		if (endEl) {
			endEl.addEventListener('change', updateManagerRecordHistoricalHint);
			endEl.addEventListener('input', updateManagerRecordHistoricalHint);
		}
		updateManagerRecordHistoricalHint();
	}

	function bindRecordForm() {
		const form = document.getElementById('manager-absence-record-form');
		const submitBtn = document.getElementById('manager-absence-record-submit');
		if (!form || !submitBtn) {
			return;
		}
		form.addEventListener('submit', (event) => {
			event.preventDefault();
			const typeSel = document.getElementById('manager-absence-record-type');
			const rs = document.getElementById('manager-absence-record-start');
			const re = document.getElementById('manager-absence-record-end');
			const reasonEl = document.getElementById('manager-absence-record-reason');
			const userId = recordEmployeePicker
				? recordEmployeePicker.getUserId()
				: String(document.getElementById('manager-absence-record-employee-id')?.value || '');
			const type = typeSel ? String(typeSel.value || '') : '';
			const dp = window.ArbeitszeitCheckDatepicker;
			const toISO = dp && typeof dp.convertEuropeanToISO === 'function'
				? dp.convertEuropeanToISO
				: (value) => value;
			const startDate = rs ? toISO(String(rs.value || '')) : '';
			const endDate = re ? toISO(String(re.value || '')) : '';
			const reason = reasonEl ? String(reasonEl.value || '') : '';
			if (!userId) {
				Messaging?.showError?.(t('Select an employee', 'Select an employee'));
				const searchEl = document.getElementById('manager-absence-record-employee-search');
				if (searchEl) {
					searchEl.focus();
				}
				return;
			}
			if (!startDate || !endDate) {
				Messaging?.showError?.(t('Please select start and end date.', 'Please select start and end date.'));
				return;
			}
			const original = submitBtn.textContent;
			submitBtn.disabled = true;
			Utils.ajax('/apps/arbeitszeitcheck/api/manager/employee-absences', {
				method: 'POST',
				data: {
					userId,
					type,
					startDate,
					endDate,
					reason,
				},
				onSuccess: () => {
					submitBtn.disabled = false;
					submitBtn.textContent = original;
					if (window.OC && window.OC.Notification && window.OC.Notification.showTemporary) {
						window.OC.Notification.showTemporary(
							t('Absence recorded and approved.', 'Absence recorded and approved.'),
							{ type: 'success' }
						);
					}
					if (reasonEl) {
						reasonEl.value = '';
					}
					state.offset = 0;
					loadEntries();
				},
				onError: (err) => {
					submitBtn.disabled = false;
					submitBtn.textContent = original;
					const message = err?.error || t('Could not save absence.', 'Could not save absence.');
					Messaging?.showError?.(message);
				},
			});
		});
	}

	function toEuropeanDateString(date) {
		const day = String(date.getDate()).padStart(2, '0');
		const month = String(date.getMonth() + 1).padStart(2, '0');
		const year = date.getFullYear();
		return `${day}.${month}.${year}`;
	}

	function setDefaultDateRange(force) {
		const startInput = document.getElementById('employee-absences-start-date-filter');
		const endInput = document.getElementById('employee-absences-end-date-filter');
		if (!startInput || !endInput) {
			return;
		}
		if (!force && (startInput.value || endInput.value)) {
			return;
		}
		const api = window.ArbeitszeitCheckTime;
		if (api) {
			const endYmd = api.todayYmd();
			const endParsed = api.parseYmd(endYmd);
			if (endParsed) {
				const startParsed = new Date(endParsed);
				startParsed.setMonth(startParsed.getMonth() - 1);
				startInput.value = Utils.formatDate
					? Utils.formatDate(startParsed, 'DD.MM.YYYY')
					: toEuropeanDateString(startParsed);
				endInput.value = Utils.formatDate
					? Utils.formatDate(endParsed, 'DD.MM.YYYY')
					: toEuropeanDateString(endParsed);
				return;
			}
		}
		const today = new Date();
		const oneMonthAgo = new Date(today);
		oneMonthAgo.setMonth(oneMonthAgo.getMonth() - 1);
		startInput.value = toEuropeanDateString(oneMonthAgo);
		endInput.value = toEuropeanDateString(today);
	}

	function bindPagination() {
		const prevBtn = document.getElementById('employee-absences-prev');
		const nextBtn = document.getElementById('employee-absences-next');
		if (prevBtn) {
			prevBtn.addEventListener('click', () => {
				state.offset = Math.max(0, state.offset - state.limit);
				loadEntries();
			});
		}
		if (nextBtn) {
			nextBtn.addEventListener('click', () => {
				state.offset += state.limit;
				loadEntries();
			});
		}
	}

	function bindForm() {
		const form = document.getElementById('employee-absences-filter-form');
		const clearBtn = document.getElementById('employee-absences-clear');
		if (!form || !clearBtn) {
			return;
		}

		form.addEventListener('submit', (event) => {
			event.preventDefault();
			state.offset = 0;
			loadEntries();
		});

		clearBtn.addEventListener('click', () => {
			if (state.loading) {
				return;
			}
			form.reset();
			clearFilterFieldErrors();
			setDefaultDateRange(true);
			const searchEl = document.getElementById('employee-absences-employee-filter-search');
			if (searchEl) {
				searchEl.focus();
			}
			state.offset = 0;
			state.total = 0;
			state.lastFilters = null;
			state.countBeforeLoad = '';
			setEmpty(
				t('Select filters first', 'Select filters first'),
				t('Choose a date range to load absences.', 'Choose a date range to load absences.')
			);
			const countEl = document.getElementById('employee-absences-count');
			if (countEl) {
				countEl.textContent = '';
			}
			updatePagination();
			syncRecordDatesFromFilter();
			if (employeeFilterPicker) {
				employeeFilterPicker.clear();
			}
			const tbody = document.getElementById('employee-absences-body');
			const tableWrap = document.getElementById('employee-absences-table-wrap');
			if (tbody) {
				tbody.innerHTML = '';
			}
			if (tableWrap) {
				tableWrap.classList.add('visually-hidden');
			}
		});

		['employee-absences-start-date-filter', 'employee-absences-end-date-filter'].forEach((id) => {
			const input = document.getElementById(id);
			if (input) {
				input.addEventListener('change', () => {
					clearFilterFieldErrors();
					syncRecordDatesFromFilter();
				});
			}
		});
	}

	function init() {
		initEmployeePickers();
		setDefaultDateRange(false);
		syncRecordDatesFromFilter();
		bindForm();
		bindRecordForm();
		bindRecordHistoricalHint();
		bindPagination();
		updatePagination();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
