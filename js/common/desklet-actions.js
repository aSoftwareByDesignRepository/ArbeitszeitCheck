/**
 * Pure desklet action helpers (clock-in payload, button enablement, notices).
 * Kept free of DOM so Vitest / mutation gauntlets can prove the contract.
 */
(function (global) {
	'use strict';

	const BUTTON_STATES = Object.freeze({
		clocked_out: Object.freeze({
			'dz-clock-in': true,
			'dz-start-break': false,
			'dz-end-break': false,
			'dz-clock-out': false,
		}),
		active: Object.freeze({
			'dz-clock-in': false,
			'dz-start-break': true,
			'dz-end-break': false,
			'dz-clock-out': true,
		}),
		break: Object.freeze({
			'dz-clock-in': false,
			'dz-start-break': false,
			'dz-end-break': true,
			'dz-clock-out': true,
		}),
		paused: Object.freeze({
			'dz-clock-in': true,
			'dz-start-break': false,
			'dz-end-break': false,
			'dz-clock-out': true,
		}),
		completed: Object.freeze({
			'dz-clock-in': true,
			'dz-start-break': false,
			'dz-end-break': false,
			'dz-clock-out': false,
		}),
	});

	/**
	 * @param {unknown} raw
	 * @returns {{
	 *   status: string,
	 *   workingTodayHours: number,
	 *   currentSessionDuration: number,
	 *   sessionStartFormatted: string,
	 *   clockStampingEnabled: boolean,
	 *   manualTimeEntryEnabled: boolean,
	 *   atDailyMaximum: boolean,
	 * }}
	 */
	function normaliseStatus(raw) {
		if (!raw || typeof raw !== 'object') {
			return {
				status: 'clocked_out',
				workingTodayHours: 0,
				currentSessionDuration: 0,
				sessionStartFormatted: '',
				clockStampingEnabled: true,
				manualTimeEntryEnabled: true,
				atDailyMaximum: false,
			};
		}
		const capture = (raw.timeCapture && typeof raw.timeCapture === 'object')
			? raw.timeCapture
			: {};
		return {
			status: String(raw.status ?? 'clocked_out'),
			workingTodayHours: parseFloat(
				raw.workingTodayHours ?? raw.working_today_hours ?? 0,
			),
			currentSessionDuration: parseInt(
				raw.currentSessionDuration ?? raw.current_session_duration ?? 0,
				10,
			),
			sessionStartFormatted: String(
				raw.sessionStartFormatted ?? raw.session_start_formatted ?? '',
			),
			clockStampingEnabled: capture.clockStampingEnabled !== false,
			manualTimeEntryEnabled: capture.manualTimeEntryEnabled !== false,
			atDailyMaximum: Boolean(raw.atDailyMaximum ?? raw.at_daily_maximum),
		};
	}

	/**
	 * @param {string} status
	 * @param {boolean} clockStampingEnabled
	 * @param {boolean} [atDailyMaximum]
	 * @returns {Record<string, boolean>}
	 */
	function getEffectiveButtonStates(status, clockStampingEnabled, atDailyMaximum = false) {
		const states = { ...(BUTTON_STATES[status] ?? BUTTON_STATES.clocked_out) };
		if (!clockStampingEnabled) {
			states['dz-clock-in'] = false;
		}
		// Daily maximum blocks starting/resuming work (same rule as mobile home).
		if (atDailyMaximum && (status === 'clocked_out' || status === 'paused' || status === 'completed')) {
			states['dz-clock-in'] = false;
		}
		return states;
	}

	/**
	 * @param {string} status
	 * @param {boolean} atDailyMaximum
	 */
	function shouldShowDailyMaxNotice(status, atDailyMaximum) {
		return Boolean(atDailyMaximum)
			&& (status === 'clocked_out' || status === 'paused' || status === 'completed');
	}

	/**
	 * @param {string} status
	 * @param {boolean} clockStampingEnabled
	 * @param {boolean} atDailyMaximum
	 */
	function canShowProjectPicker(status, clockStampingEnabled, atDailyMaximum) {
		return Boolean(
			getEffectiveButtonStates(status, clockStampingEnabled, atDailyMaximum)['dz-clock-in'],
		);
	}

	/**
	 * Build JSON body for desklet clock-in. Empty / whitespace project → omit key
	 * so the server treats it as “no project” (not an invalid id).
	 *
	 * @param {string|null|undefined} projectSelectValue
	 * @returns {Record<string, string>}
	 */
	function buildClockInJsonBody(projectSelectValue) {
		const body = {};
		if (projectSelectValue == null) {
			return body;
		}
		const trimmed = String(projectSelectValue).trim();
		if (trimmed !== '') {
			body.projectCheckProjectId = trimmed;
		}
		return body;
	}

	/**
	 * Prefer server message / error_code-aware copy over opaque HTTP status text.
	 *
	 * @param {{ error?: unknown, status?: number, data?: unknown }|null|undefined} result
	 * @param {Record<string, string>} [l10n]
	 * @returns {string}
	 */
	function resolveActionErrorMessage(result, l10n = {}) {
		const body = (result && result.data && typeof result.data === 'object')
			? result.data
			: {};
		const fromResult = (result && typeof result.error === 'string' && result.error.trim() !== '')
			? result.error.trim()
			: '';
		const fromBodyError = (typeof body.error === 'string' && body.error.trim() !== '')
			? body.error.trim()
			: '';
		const fromBodyMessage = (typeof body.message === 'string' && body.message.trim() !== '')
			? body.message.trim()
			: '';
		return fromResult
			|| fromBodyError
			|| fromBodyMessage
			|| l10n.actionFailed
			|| 'Action failed';
	}

	const api = {
		BUTTON_STATES,
		normaliseStatus,
		getEffectiveButtonStates,
		shouldShowDailyMaxNotice,
		canShowProjectPicker,
		buildClockInJsonBody,
		resolveActionErrorMessage,
	};

	global.AzcDeskletActions = api;

	if (typeof module !== 'undefined' && module.exports) {
		module.exports = api;
	}
})(typeof window !== 'undefined' ? window : globalThis);
