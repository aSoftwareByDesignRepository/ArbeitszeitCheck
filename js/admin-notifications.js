/**
 * Admin notification settings.
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */
(function () {
	'use strict';

	const Utils = window.ArbeitszeitCheckUtils || {};
	const Messaging = window.ArbeitszeitCheckMessaging || {};

	function $(selector, context) {
		if (Utils.$) {
			return Utils.$(selector, context);
		}
		const root = context || document;
		return root.querySelector(selector);
	}

	function normalizeRecipients(raw) {
		const parts = String(raw || '')
			.split(',')
			.map((entry) => entry.trim().toLowerCase())
			.filter((entry) => entry.length > 0);
		const unique = [];
		const seen = new Set();
		parts.forEach((entry) => {
			if (!seen.has(entry)) {
				seen.add(entry);
				unique.push(entry);
			}
		});
		return unique;
	}

	function collectMatrix(form, matrixMeta) {
		const matrix = {};
		(matrixMeta.absenceTypes || []).forEach((type) => {
			const typeKey = String(type.key || '');
			if (typeKey === '') {
				return;
			}
			matrix[typeKey] = {};
			(matrixMeta.eventTypes || []).forEach((event) => {
				const eventKey = String(event.key || '');
				if (eventKey === '') {
					return;
				}
				const selector = `input[name="matrix[${typeKey}][${eventKey}]"]`;
				const input = form.querySelector(selector);
				matrix[typeKey][eventKey] = !!(input && input.checked);
			});
		});
		return matrix;
	}

	function setLiveMessage(liveRegion, message, type) {
		if (!liveRegion) {
			return;
		}
		liveRegion.textContent = message || '';
		liveRegion.classList.remove('admin-notifications-live--error', 'admin-notifications-live--success');
		liveRegion.setAttribute('role', type === 'error' ? 'alert' : 'status');
		if (type === 'error') {
			liveRegion.classList.add('admin-notifications-live--error');
		} else if (type === 'success') {
			liveRegion.classList.add('admin-notifications-live--success');
		}
		// Do not scrollIntoView: announcements must not yank the viewport away
		// from the control the admin just used.
	}

	function bindDependentBlock(toggle, blockId) {
		const block = document.getElementById(blockId);
		if (!toggle || !block) {
			return;
		}
		const sync = function () {
			const on = !!toggle.checked;
			block.setAttribute('data-settings-disabled', on ? 'false' : 'true');
			block.querySelectorAll('input, textarea, select, button').forEach((el) => {
				if (el === toggle) {
					return;
				}
				el.disabled = !on;
				if (el.type === 'checkbox' || el.type === 'radio') {
					el.setAttribute('aria-disabled', on ? 'false' : 'true');
				}
			});
		};
		toggle.addEventListener('change', sync);
		sync();
	}

	function collectPremiumPolicy(form) {
		const nightWindows = {
			at: { start: '22:00', end: '05:00' },
			de: { start: '23:00', end: '06:00' },
		};
		const presetHint = form.getAttribute('data-premium-night-preset') || 'at';
		const night = nightWindows[presetHint] || nightWindows.at;
		const defs = [
			{ id: 'overtime_base', label: 'Overtime above daily target', applies_to: 'hours_above_daily_or_weekly_threshold', threshold_ref: 'model_net_daily', onSel: '#premium-cat-ot-on', rateSel: '#premium-cat-ot-rate' },
			{ id: 'sunday', label: 'Sunday', applies_to: 'weekday', weekdays: ['sun'], onSel: '#premium-cat-sun-on', rateSel: '#premium-cat-sun-rate' },
			{ id: 'saturday', label: 'Saturday', applies_to: 'weekday', weekdays: ['sat'], onSel: '#premium-cat-sat-on', rateSel: '#premium-cat-sat-rate' },
			{ id: 'night', label: 'Night', applies_to: 'time_window', window_start: night.start, window_end: night.end, onSel: '#premium-cat-night-on', rateSel: '#premium-cat-night-rate' },
		];
		const categories = [];
		defs.forEach((def) => {
			const on = form.querySelector(def.onSel);
			const rateEl = form.querySelector(def.rateSel);
			const pct = rateEl ? Number(rateEl.value) : 0;
			const rate = Number.isFinite(pct) ? Math.max(0, Math.min(300, pct)) / 100 : 0;
			const row = {
				id: def.id,
				label: def.label,
				rate: rate,
				enabled: !!(on && on.checked),
				applies_to: def.applies_to,
			};
			if (def.weekdays) {
				row.weekdays = def.weekdays;
			}
			if (def.window_start) {
				row.window_start = def.window_start;
				row.window_end = def.window_end;
			}
			if (def.threshold_ref) {
				row.threshold_ref = def.threshold_ref;
			}
			categories.push(row);
		});
		return {
			version: 1,
			currency_mode: 'hours_only',
			stacking: 'max_single_rate',
			holiday_policy: 'treat_as_sunday',
			categories: categories,
		};
	}

	function applyPremiumPreset(form, kind) {
		const rates = {
			at: { ot: 50, sun: 100, sat: 50, night: 50 },
			de: { ot: 50, sun: 100, sat: 50, night: 50 },
			blank: { ot: 0, sun: 0, sat: 0, night: 0 },
		};
		const r = rates[kind] || rates.at;
		form.setAttribute('data-premium-night-preset', kind === 'de' ? 'de' : 'at');
		const map = [
			['#premium-cat-ot-on', '#premium-cat-ot-rate', r.ot, kind !== 'blank'],
			['#premium-cat-sun-on', '#premium-cat-sun-rate', r.sun, kind !== 'blank'],
			['#premium-cat-sat-on', '#premium-cat-sat-rate', r.sat, kind === 'at' || kind === 'de'],
			['#premium-cat-night-on', '#premium-cat-night-rate', r.night, kind !== 'blank'],
		];
		map.forEach(([onSel, rateSel, pct, on]) => {
			const onEl = form.querySelector(onSel);
			const rateEl = form.querySelector(rateSel);
			if (onEl) {
				onEl.checked = !!on;
			}
			if (rateEl) {
				rateEl.value = String(pct);
			}
		});
	}

	function bindPremiumUi(form, onDirty) {
		const enabled = form.querySelector('#premiumSurchargesEnabled');
		const panel = form.querySelector('#premium-surcharges-panel');
		const sync = () => {
			if (!panel) {
				return;
			}
			if (enabled && enabled.checked) {
				panel.removeAttribute('hidden');
			} else {
				panel.setAttribute('hidden', 'hidden');
			}
		};
		if (enabled) {
			enabled.addEventListener('change', sync);
			sync();
		}
		form.querySelectorAll('[data-premium-preset]').forEach((btn) => {
			btn.addEventListener('click', () => {
				applyPremiumPreset(form, btn.getAttribute('data-premium-preset') || 'at');
				if (typeof onDirty === 'function') {
					onDirty();
				}
			});
		});
	}

	function init() {
		const form = $('#admin-notifications-form');
		const saveButton = $('#admin-notifications-save');
		const recipientsField = $('#hrRecipients');
		const overtimeRecipientsField = $('#overtimeRecipients');
		const liveRegion = $('#admin-notifications-live');
		const apiUrl = window.ArbeitszeitCheck && window.ArbeitszeitCheck.adminNotificationsApiUrl;
		const l10n = (window.ArbeitszeitCheck && window.ArbeitszeitCheck.l10n) || {};
		const matrixMeta = (window.ArbeitszeitCheck && window.ArbeitszeitCheck.notificationMatrixMeta) || { absenceTypes: [], eventTypes: [] };
		let formDirty = false;
		let saving = false;

		if (!form || !apiUrl) {
			return;
		}

		const markDirty = function () {
			if (!saving) {
				formDirty = true;
			}
		};
		form.addEventListener('input', markDirty);
		form.addEventListener('change', markDirty);
		window.addEventListener('beforeunload', function (event) {
			if (formDirty && !saving) {
				event.preventDefault();
				event.returnValue = '';
			}
		});

		bindPremiumUi(form, markDirty);
		bindDependentBlock($('#hrNotificationsEnabled'), 'hr-notification-settings');
		bindDependentBlock($('#overtimeTrafficLightEnabled'), 'overtime-trafficlight-settings');
		bindDependentBlock($('#overtimeBankEnabled'), 'overtime-bank-settings');

		form.addEventListener('submit', function (event) {
			event.preventDefault();
			const enabledField = $('#hrNotificationsEnabled');
			const enabled = !!(enabledField && enabledField.checked);
			const recipients = normalizeRecipients(recipientsField ? recipientsField.value : '');
			const overtimeRecipients = normalizeRecipients(overtimeRecipientsField ? overtimeRecipientsField.value : '');
			const matrix = collectMatrix(form, matrixMeta);
			const overtimeMatrix = {
				over: {
					yellow: !!form.querySelector('input[name="overtimeMatrix[over][yellow]"]')?.checked,
					red: !!form.querySelector('input[name="overtimeMatrix[over][red]"]')?.checked,
				},
				under: {
					yellow: !!form.querySelector('input[name="overtimeMatrix[under][yellow]"]')?.checked,
					red: !!form.querySelector('input[name="overtimeMatrix[under][red]"]')?.checked,
				},
			};
			const isChecked = function (value) {
				return value === 'on' || value === '1' || value === 1 || value === true;
			};
			const formData = new FormData(form);
			const vacationCarryoverMaxDaysVal = String(formData.get('vacationCarryoverMaxDays') || '').trim();
			const overtimeTrafficLightEnabled = isChecked(formData.get('overtimeTrafficLightEnabled'));
			const overtimeYellowOver = Number(String(formData.get('overtimeYellowOver') || '5').replace(',', '.'));
			const overtimeRedOver = Number(String(formData.get('overtimeRedOver') || '15').replace(',', '.'));
			const overtimeYellowUnder = Number(String(formData.get('overtimeYellowUnder') || '5').replace(',', '.'));
			const overtimeRedUnder = Number(String(formData.get('overtimeRedUnder') || '15').replace(',', '.'));

			if (enabled && recipients.length === 0) {
				const msg = l10n.invalidRecipients || 'Please enter at least one valid recipient email address.';
				Messaging.showError(msg);
				setLiveMessage(liveRegion, msg, 'error');
				if (recipientsField) {
					recipientsField.focus();
				}
				return;
			}
			if (overtimeTrafficLightEnabled && overtimeRecipients.length === 0) {
				const msg = l10n.invalidBalanceTrafficLightRecipients || 'Please enter at least one valid balance traffic light recipient email address (overtime/undertime).';
				Messaging.showError(msg);
				setLiveMessage(liveRegion, msg, 'error');
				if (overtimeRecipientsField) {
					overtimeRecipientsField.focus();
				}
				return;
			}
			if (!Number.isFinite(overtimeYellowOver) || !Number.isFinite(overtimeRedOver) || !Number.isFinite(overtimeYellowUnder) || !Number.isFinite(overtimeRedUnder)) {
				const msg = l10n.invalidThresholdValues || 'Threshold values must be valid numbers.';
				Messaging.showError(msg);
				setLiveMessage(liveRegion, msg, 'error');
				const firstBad = ['#overtimeYellowOver', '#overtimeRedOver', '#overtimeYellowUnder', '#overtimeRedUnder'].find((sel) => {
					const el = form.querySelector(sel);
					const n = Number(String(el?.value || '').replace(',', '.'));
					return !Number.isFinite(n);
				});
				const focusEl = firstBad ? form.querySelector(firstBad) : null;
				if (focusEl) {
					focusEl.focus();
				}
				return;
			}
			if (overtimeYellowOver > overtimeRedOver || overtimeYellowUnder > overtimeRedUnder) {
				const msg = l10n.invalidThresholdOrder || 'Yellow thresholds must be less than or equal to red thresholds.';
				Messaging.showError(msg);
				setLiveMessage(liveRegion, msg, 'error');
				if (overtimeYellowOver > overtimeRedOver) {
					form.querySelector('#overtimeYellowOver')?.focus();
				} else {
					form.querySelector('#overtimeYellowUnder')?.focus();
				}
				return;
			}
			const bankYellowPct = parseInt(String(formData.get('overtimeBankYellowPercent') || '80'), 10);
			const bankRedPct = parseInt(String(formData.get('overtimeBankRedPercent') || '95'), 10);
			if (bankYellowPct > bankRedPct) {
				const msg = l10n.invalidBankFillOrder || 'Bank fill yellow percent must be less than or equal to red percent.';
				Messaging.showError(msg);
				setLiveMessage(liveRegion, msg, 'error');
				const yellowEl = form.querySelector('#overtimeBankYellowPercent');
				if (yellowEl) {
					yellowEl.focus();
				}
				return;
			}
			if (vacationCarryoverMaxDaysVal !== '') {
				const parsedMax = Number(vacationCarryoverMaxDaysVal.replace(',', '.'));
				if (!Number.isFinite(parsedMax) || parsedMax < 0 || parsedMax > 366) {
					const msg = l10n.invalidCarryoverMaxDays || 'Maximum carryover days must be empty (unlimited) or between 0 and 366';
					Messaging.showError(msg);
					setLiveMessage(liveRegion, msg, 'error');
					const maxDaysEl = form.querySelector('#vacationCarryoverMaxDays');
					if (maxDaysEl) {
						maxDaysEl.focus();
					}
					return;
				}
			}

			saving = true;
			if (saveButton) {
				saveButton.disabled = true;
				saveButton.setAttribute('aria-busy', 'true');
			}
			setLiveMessage(liveRegion, '', null);

			Utils.ajax(apiUrl, {
				method: 'POST',
				data: {
					enabled: enabled,
					recipients: recipients,
					matrix: matrix,
					overtimeTrafficLightEnabled: overtimeTrafficLightEnabled,
					overtimeRecipients: overtimeRecipients,
					overtimeMatrix: overtimeMatrix,
					overtimeYellowOver: overtimeYellowOver,
					overtimeRedOver: overtimeRedOver,
					overtimeYellowUnder: overtimeYellowUnder,
					overtimeRedUnder: overtimeRedUnder,
					overtimeBankEnabled: isChecked(formData.get('overtimeBankEnabled')),
					overtimeBankMaxHours: Number(String(formData.get('overtimeBankMaxHours') || '100').replace(',', '.')),
					overtimeBankYellowPercent: parseInt(String(formData.get('overtimeBankYellowPercent') || '80'), 10),
					overtimeBankRedPercent: parseInt(String(formData.get('overtimeBankRedPercent') || '95'), 10),
					overtimePayoutNotifyInApp: isChecked(formData.get('overtimePayoutNotifyInApp')),
					overtimePayoutNotifyEmail: isChecked(formData.get('overtimePayoutNotifyEmail')),
					overtimeBlockMonthClosurePendingPayout: isChecked(formData.get('overtimeBlockMonthClosurePendingPayout')),
					missingClockInRemindersEnabled: isChecked(formData.get('missingClockInRemindersEnabled')),
					vacationCarryoverExpiryMonth: parseInt(String(formData.get('vacationCarryoverExpiryMonth') || ''), 10),
					vacationCarryoverExpiryDay: parseInt(String(formData.get('vacationCarryoverExpiryDay') || ''), 10),
					vacationCarryoverMaxDays: vacationCarryoverMaxDaysVal,
					vacationRolloverEnabled: isChecked(formData.get('vacationRolloverEnabled')),
					vacationRolloverIncludeUnusedAnnual: isChecked(formData.get('vacationRolloverIncludeUnusedAnnual')),
					vacationProrationMethod: String(formData.get('vacationProrationMethod') || 'twelfths'),
					vacationYearMode: String(formData.get('vacationYearMode') || 'calendar'),
					premiumSurchargesEnabled: isChecked(formData.get('premiumSurchargesEnabled')),
					premiumPolicy: collectPremiumPolicy(form),
					sendIcalApprovedAbsences: isChecked(formData.get('sendIcalApprovedAbsences')),
					sendIcalToSubstitute: isChecked(formData.get('sendIcalToSubstitute')),
					sendIcalToManagers: isChecked(formData.get('sendIcalToManagers')),
					sendEmailSubstitutionRequest: isChecked(formData.get('sendEmailSubstitutionRequest')),
					sendEmailSubstituteApprovedToEmployee: isChecked(formData.get('sendEmailSubstituteApprovedToEmployee')),
					sendEmailSubstituteApprovedToManager: isChecked(formData.get('sendEmailSubstituteApprovedToManager')),
				},
				onSuccess: function (response) {
					saving = false;
					if (saveButton) {
						saveButton.disabled = false;
						saveButton.removeAttribute('aria-busy');
					}
					if (response && response.success) {
						formDirty = false;
						const msg = response.message || l10n.notificationsSaved || 'Notification settings updated successfully';
						Messaging.showSuccess(msg);
						if (recipientsField) {
							recipientsField.value = recipients.join(', ');
						}
						if (overtimeRecipientsField) {
							overtimeRecipientsField.value = overtimeRecipients.join(', ');
						}
						setLiveMessage(liveRegion, msg, 'success');
						return;
					}
					const errorMessage = (response && response.error) || l10n.failedToSaveNotifications || 'Failed to save notification settings';
					Messaging.showError(errorMessage);
					setLiveMessage(liveRegion, errorMessage, 'error');
				},
				onError: function (error) {
					saving = false;
					if (saveButton) {
						saveButton.disabled = false;
						saveButton.removeAttribute('aria-busy');
					}
					const errorMessage = (error && error.error) || l10n.failedToSaveNotifications || 'Failed to save notification settings';
					Messaging.showError(errorMessage);
					setLiveMessage(liveRegion, errorMessage, 'error');
				},
			});
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
