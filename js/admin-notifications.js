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

	function init() {
		const form = Utils.$('#admin-notifications-form');
		const saveButton = Utils.$('#admin-notifications-save');
		const recipientsField = Utils.$('#hrRecipients');
		const overtimeRecipientsField = Utils.$('#overtimeRecipients');
		const liveRegion = Utils.$('#admin-notifications-live');
		const apiUrl = window.ArbeitszeitCheck && window.ArbeitszeitCheck.adminNotificationsApiUrl;
		const l10n = (window.ArbeitszeitCheck && window.ArbeitszeitCheck.l10n) || {};
		const matrixMeta = (window.ArbeitszeitCheck && window.ArbeitszeitCheck.notificationMatrixMeta) || { absenceTypes: [], eventTypes: [] };

		if (!form || !apiUrl || !recipientsField || !overtimeRecipientsField) {
			return;
		}
		form.addEventListener('submit', function (event) {
			event.preventDefault();
			const enabledField = Utils.$('#hrNotificationsEnabled');
			const enabled = !!(enabledField && enabledField.checked);
			const recipients = normalizeRecipients(recipientsField.value);
			const overtimeRecipients = normalizeRecipients(overtimeRecipientsField.value);
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
			const vacationCarryoverMaxDays = String(formData.get('vacationCarryoverMaxDays') || '').trim();
			const overtimeTrafficLightEnabled = isChecked(formData.get('overtimeTrafficLightEnabled'));
			const overtimeYellowOver = Number(String(formData.get('overtimeYellowOver') || '5').replace(',', '.'));
			const overtimeRedOver = Number(String(formData.get('overtimeRedOver') || '15').replace(',', '.'));
			const overtimeYellowUnder = Number(String(formData.get('overtimeYellowUnder') || '5').replace(',', '.'));
			const overtimeRedUnder = Number(String(formData.get('overtimeRedUnder') || '15').replace(',', '.'));

			if (enabled && recipients.length === 0) {
				Messaging.showError(l10n.invalidRecipients || 'Please enter at least one valid recipient email address.');
				if (liveRegion) {
					liveRegion.textContent = l10n.invalidRecipients || 'Please enter at least one valid recipient email address.';
				}
				recipientsField.focus();
				return;
			}
			if (overtimeTrafficLightEnabled && overtimeRecipients.length === 0) {
				const errorMessage = l10n.invalidBalanceTrafficLightRecipients || 'Please enter at least one valid balance traffic light recipient email address (overtime/undertime).';
				Messaging.showError(errorMessage);
				if (liveRegion) {
					liveRegion.textContent = errorMessage;
				}
				overtimeRecipientsField.focus();
				return;
			}
			if (!Number.isFinite(overtimeYellowOver) || !Number.isFinite(overtimeRedOver) || !Number.isFinite(overtimeYellowUnder) || !Number.isFinite(overtimeRedUnder)) {
				const errorMessage = l10n.invalidThresholdValues || 'Threshold values must be valid numbers.';
				Messaging.showError(errorMessage);
				if (liveRegion) {
					liveRegion.textContent = errorMessage;
				}
				return;
			}
			if (overtimeYellowOver > overtimeRedOver || overtimeYellowUnder > overtimeRedUnder) {
				const errorMessage = l10n.invalidThresholdOrder || 'Yellow thresholds must be less than or equal to red thresholds.';
				Messaging.showError(errorMessage);
				if (liveRegion) {
					liveRegion.textContent = errorMessage;
				}
				return;
			}
			if (vacationCarryoverMaxDays !== '') {
				const parsedMax = Number(vacationCarryoverMaxDays.replace(',', '.'));
				if (!Number.isFinite(parsedMax) || parsedMax < 0 || parsedMax > 366) {
					const errorMessage = l10n.invalidCarryoverMaxDays || 'Maximum carryover days must be empty (unlimited) or between 0 and 366';
					Messaging.showError(errorMessage);
					if (liveRegion) {
						liveRegion.textContent = errorMessage;
					}
					return;
				}
			}

			if (saveButton) {
				saveButton.disabled = true;
			}
			if (liveRegion) {
				liveRegion.textContent = '';
			}

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
					missingClockInRemindersEnabled: isChecked(formData.get('missingClockInRemindersEnabled')),
					vacationCarryoverExpiryMonth: parseInt(String(formData.get('vacationCarryoverExpiryMonth') || ''), 10),
					vacationCarryoverExpiryDay: parseInt(String(formData.get('vacationCarryoverExpiryDay') || ''), 10),
					vacationCarryoverMaxDays: vacationCarryoverMaxDays,
					vacationRolloverEnabled: isChecked(formData.get('vacationRolloverEnabled')),
					vacationRolloverIncludeUnusedAnnual: isChecked(formData.get('vacationRolloverIncludeUnusedAnnual')),
					sendIcalApprovedAbsences: isChecked(formData.get('sendIcalApprovedAbsences')),
					sendIcalToSubstitute: isChecked(formData.get('sendIcalToSubstitute')),
					sendIcalToManagers: isChecked(formData.get('sendIcalToManagers')),
					sendEmailSubstitutionRequest: isChecked(formData.get('sendEmailSubstitutionRequest')),
					sendEmailSubstituteApprovedToEmployee: isChecked(formData.get('sendEmailSubstituteApprovedToEmployee')),
					sendEmailSubstituteApprovedToManager: isChecked(formData.get('sendEmailSubstituteApprovedToManager')),
				},
				onSuccess: function (response) {
					if (saveButton) {
						saveButton.disabled = false;
					}
					if (response && response.success) {
						Messaging.showSuccess(response.message || l10n.notificationsSaved || 'Notification settings updated successfully');
						recipientsField.value = recipients.join(', ');
						overtimeRecipientsField.value = overtimeRecipients.join(', ');
						if (liveRegion) {
							liveRegion.textContent = response.message || l10n.notificationsSaved || 'Notification settings updated successfully';
						}
						return;
					}
					const errorMessage = (response && response.error) || l10n.failedToSaveNotifications || 'Failed to save notification settings';
					Messaging.showError(errorMessage);
					if (liveRegion) {
						liveRegion.textContent = errorMessage;
					}
				},
				onError: function (error) {
					if (saveButton) {
						saveButton.disabled = false;
					}
					const errorMessage = (error && error.error) || l10n.failedToSaveNotifications || 'Failed to save notification settings';
					Messaging.showError(errorMessage);
					if (liveRegion) {
						liveRegion.textContent = errorMessage;
					}
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
