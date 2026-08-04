/**
 * Working Time Models JavaScript for arbeitszeitcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

(function() {
    'use strict';

    const Utils = window.ArbeitszeitCheckUtils || {};
    const Components = window.ArbeitszeitCheckComponents || {};
    const Messaging = window.ArbeitszeitCheckMessaging || {};

    /** Prefer server-injected l10n; window.t may be unavailable. */
    function wtmMsg(key, englishFallback) {
        const v = window.ArbeitszeitCheck?.l10n?.[key];
        if (v !== undefined && v !== '') {
            return v;
        }
        if (typeof window.t === 'function' && englishFallback) {
            return window.t('arbeitszeitcheck', englishFallback);
        }
        return englishFallback || '';
    }

    /** Option tags for working-time model type; labels from working-time-models.php l10n. */
    function modelTypeSelectOptions(selectedType) {
        const rows = [
            ['full_time', 'fullTime', 'Full-Time'],
            ['part_time', 'partTime', 'Part-Time'],
            ['flexible', 'flexible', 'Flexible'],
            ['trust_based', 'trustBased', 'Trust-Based'],
            ['shift_work', 'shiftWork', 'Shift Work'],
        ];
        return rows.map(function(row) {
            const value = row[0];
            const l10nKey = row[1];
            const en = row[2];
            const sel = selectedType === value ? ' selected' : '';
            return '<option value="' + value + '"' + sel + '>' + Utils.escapeHtml(wtmMsg(l10nKey, en)) + '</option>';
        }).join('');
    }

    function parseLocalizedDecimal(value, fallback) {
        if (value === null || value === undefined || value === '') {
            return fallback;
        }
        const normalized = String(value).trim().replace(/\s+/g, '').replace(',', '.');
        if (!/^-?\d+(\.\d+)?$/.test(normalized)) {
            return fallback;
        }
        const parsed = Number(normalized);
        if (!Number.isFinite(parsed)) {
            return fallback;
        }
        return parsed;
    }

    function banssWeekdayPreset() {
        const long = {
            work: true,
            start: '07:00',
            end: '16:15',
            breaks: [{ start: '12:15', end: '13:00', paid: false }],
        };
        const fri = {
            work: true,
            start: '07:00',
            end: '11:45',
            breaks: [{ start: '09:00', end: '09:15', paid: false }],
        };
        const off = { work: false };
        return {
            version: 1,
            days: {
                mon: long, tue: long, wed: long, thu: long, fri: fri, sat: off, sun: off,
            },
        };
    }

    function weekdayScheduleSectionHtml(prefix, existingSchedule) {
        const days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        const labels = {
            mon: wtmMsg('monday', 'Monday'),
            tue: wtmMsg('tuesday', 'Tuesday'),
            wed: wtmMsg('wednesday', 'Wednesday'),
            thu: wtmMsg('thursday', 'Thursday'),
            fri: wtmMsg('friday', 'Friday'),
            sat: wtmMsg('saturday', 'Saturday'),
            sun: wtmMsg('sunday', 'Sunday'),
        };
        const schedule = existingSchedule && existingSchedule.days ? existingSchedule : null;
        const title = wtmMsg('weekdaySchedule', 'Weekday times (optional)');
        const lead = wtmMsg(
            'weekdayScheduleHelp',
            'Set different hours per weekday and fixed unpaid breaks. Weekly and daily hours update automatically. Leave empty to use the simple weekly hours above.'
        );
        const presetLabel = wtmMsg('weekdaySchedulePreset', 'Mo–Thu long / Fri short');
        const workLabel = wtmMsg('workDay', 'Work');
        const startLabel = wtmMsg('start', 'Start');
        const endLabel = wtmMsg('end', 'End');
        const breakLabel = wtmMsg('breakWindow', 'Break');
        const netLabel = wtmMsg('netHours', 'Net');

        let rows = '';
        days.forEach((day) => {
            const row = schedule && schedule.days[day] ? schedule.days[day] : { work: false };
            const work = !!row.work;
            const br = (row.breaks && row.breaks[0]) ? row.breaks[0] : { start: '', end: '' };
            rows += `
                <tr data-day="${day}">
                    <th scope="row">${Utils.escapeHtml(labels[day])}</th>
                    <td><input type="checkbox" class="wtm-day-work" id="${prefix}-${day}-work" ${work ? 'checked' : ''} aria-label="${Utils.escapeHtml(labels[day] + ' ' + workLabel)}"></td>
                    <td><input type="time" class="form-input wtm-day-start" id="${prefix}-${day}-start" value="${Utils.escapeHtml(row.start || '')}" ${work ? '' : 'disabled'} aria-label="${Utils.escapeHtml(labels[day] + ' ' + startLabel)}"></td>
                    <td><input type="time" class="form-input wtm-day-end" id="${prefix}-${day}-end" value="${Utils.escapeHtml(row.end || '')}" ${work ? '' : 'disabled'} aria-label="${Utils.escapeHtml(labels[day] + ' ' + endLabel)}"></td>
                    <td><input type="time" class="form-input wtm-day-break-start" id="${prefix}-${day}-bstart" value="${Utils.escapeHtml(br.start || '')}" ${work ? '' : 'disabled'} aria-label="${Utils.escapeHtml(labels[day] + ' ' + breakLabel + ' ' + startLabel)}"></td>
                    <td><input type="time" class="form-input wtm-day-break-end" id="${prefix}-${day}-bend" value="${Utils.escapeHtml(br.end || '')}" ${work ? '' : 'disabled'} aria-label="${Utils.escapeHtml(labels[day] + ' ' + breakLabel + ' ' + endLabel)}"></td>
                    <td class="wtm-day-net" id="${prefix}-${day}-net" aria-live="polite">—</td>
                </tr>`;
        });

        return `
            <fieldset class="wtm-weekday-schedule" data-schedule-prefix="${prefix}">
                <legend class="form-label">${Utils.escapeHtml(title)}</legend>
                <p class="form-help" id="${prefix}-schedule-help">${Utils.escapeHtml(lead)}</p>
                <p>
                    <button type="button" class="azc-btn azc-btn--secondary azc-btn--sm" data-action="apply-weekday-preset" aria-describedby="${prefix}-schedule-help">
                        ${Utils.escapeHtml(presetLabel)}
                    </button>
                    <button type="button" class="azc-btn azc-btn--tertiary azc-btn--sm" data-action="clear-weekday-schedule">
                        ${Utils.escapeHtml(wtmMsg('clearSchedule', 'Clear weekday times'))}
                    </button>
                </p>
                <div class="table-container" role="region" aria-label="${Utils.escapeHtml(title)}">
                    <table class="table wtm-weekday-table">
                        <thead>
                            <tr>
                                <th scope="col">${Utils.escapeHtml(wtmMsg('day', 'Day'))}</th>
                                <th scope="col">${Utils.escapeHtml(workLabel)}</th>
                                <th scope="col">${Utils.escapeHtml(startLabel)}</th>
                                <th scope="col">${Utils.escapeHtml(endLabel)}</th>
                                <th scope="col">${Utils.escapeHtml(breakLabel)} ${Utils.escapeHtml(startLabel)}</th>
                                <th scope="col">${Utils.escapeHtml(breakLabel)} ${Utils.escapeHtml(endLabel)}</th>
                                <th scope="col">${Utils.escapeHtml(netLabel)}</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
                <p class="wtm-week-total" id="${prefix}-week-total" aria-live="polite"></p>
            </fieldset>`;
    }

    function parseHmToMinutes(hm) {
        if (!hm || !/^\d{1,2}:\d{2}$/.test(hm)) {
            return null;
        }
        const parts = hm.split(':');
        const h = Number(parts[0]);
        const m = Number(parts[1]);
        if (!Number.isFinite(h) || !Number.isFinite(m) || h > 23 || m > 59) {
            return null;
        }
        return h * 60 + m;
    }

    function netHoursFromRow(start, end, bStart, bEnd) {
        const s = parseHmToMinutes(start);
        const e = parseHmToMinutes(end);
        if (s === null || e === null || e <= s) {
            return null;
        }
        let unpaid = 0;
        const bs = parseHmToMinutes(bStart);
        const be = parseHmToMinutes(bEnd);
        if (bs !== null && be !== null && be > bs) {
            unpaid = be - bs;
        }
        return Math.max(0, (e - s - unpaid) / 60);
    }

    function refreshWeekdayScheduleTotals(fieldset) {
        if (!fieldset) {
            return;
        }
        const prefix = fieldset.getAttribute('data-schedule-prefix') || 'wtm';
        let week = 0;
        let anyWork = false;
        fieldset.querySelectorAll('tr[data-day]').forEach((tr) => {
            const day = tr.getAttribute('data-day');
            const work = tr.querySelector('.wtm-day-work')?.checked;
            const netEl = document.getElementById(`${prefix}-${day}-net`);
            const start = tr.querySelector('.wtm-day-start');
            const end = tr.querySelector('.wtm-day-end');
            const bStart = tr.querySelector('.wtm-day-break-start');
            const bEnd = tr.querySelector('.wtm-day-break-end');
            [start, end, bStart, bEnd].forEach((el) => {
                if (el) {
                    el.disabled = !work;
                }
            });
            if (!work) {
                if (netEl) {
                    netEl.textContent = '—';
                }
                return;
            }
            anyWork = true;
            const net = netHoursFromRow(start?.value, end?.value, bStart?.value, bEnd?.value);
            if (netEl) {
                netEl.textContent = net === null ? '—' : `${net.toFixed(2)} h`;
            }
            if (net !== null) {
                week += net;
            }
        });
        const totalEl = document.getElementById(`${prefix}-week-total`);
        if (totalEl) {
            totalEl.textContent = anyWork
                ? (wtmMsg('weekTotalNet', 'Week total (net): {h} h').replace('{h}', week.toFixed(2)))
                : '';
        }
        const form = fieldset.closest('form');
        const weekly = form?.querySelector('[name="weeklyHours"]');
        const daily = form?.querySelector('[name="dailyHours"]');
        const workDays = form?.querySelector('[name="workDaysPerWeek"]');
        const workCount = fieldset.querySelectorAll('.wtm-day-work:checked').length;
        if (anyWork) {
            if (weekly) {
                weekly.value = String(week.toFixed(2));
            }
            if (daily && workCount > 0) {
                daily.value = String((week / workCount).toFixed(2));
            }
            if (workDays && workCount > 0) {
                workDays.value = String(workCount);
            }
        }
        // When a weekday matrix is active, scalars are derived — keep them readable but not editable.
        [weekly, daily, workDays].forEach((el) => {
            if (!el) {
                return;
            }
            el.readOnly = anyWork;
            if (anyWork) {
                el.setAttribute('aria-readonly', 'true');
            } else {
                el.removeAttribute('aria-readonly');
            }
        });
    }

    function bindWeekdayScheduleFieldset(fieldset) {
        if (!fieldset || fieldset.dataset.bound === '1') {
            return;
        }
        fieldset.dataset.bound = '1';
        fieldset.addEventListener('change', () => refreshWeekdayScheduleTotals(fieldset));
        fieldset.addEventListener('input', () => refreshWeekdayScheduleTotals(fieldset));
        const presetBtn = fieldset.querySelector('[data-action="apply-weekday-preset"]');
        if (presetBtn) {
            presetBtn.addEventListener('click', () => {
                applyScheduleToFieldset(fieldset, banssWeekdayPreset());
                refreshWeekdayScheduleTotals(fieldset);
            });
        }
        const clearBtn = fieldset.querySelector('[data-action="clear-weekday-schedule"]');
        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                fieldset.querySelectorAll('.wtm-day-work').forEach((cb) => {
                    cb.checked = false;
                });
                fieldset.querySelectorAll('input[type="time"]').forEach((el) => {
                    el.value = '';
                });
                refreshWeekdayScheduleTotals(fieldset);
            });
        }
        refreshWeekdayScheduleTotals(fieldset);
    }

    function applyScheduleToFieldset(fieldset, schedule) {
        const prefix = fieldset.getAttribute('data-schedule-prefix') || 'wtm';
        const days = schedule.days || {};
        Object.keys(days).forEach((day) => {
            const row = days[day] || {};
            const work = fieldset.querySelector(`#${prefix}-${day}-work`);
            const start = fieldset.querySelector(`#${prefix}-${day}-start`);
            const end = fieldset.querySelector(`#${prefix}-${day}-end`);
            const bStart = fieldset.querySelector(`#${prefix}-${day}-bstart`);
            const bEnd = fieldset.querySelector(`#${prefix}-${day}-bend`);
            if (work) {
                work.checked = !!row.work;
            }
            if (start) {
                start.value = row.start || '';
            }
            if (end) {
                end.value = row.end || '';
            }
            const br = (row.breaks && row.breaks[0]) ? row.breaks[0] : {};
            if (bStart) {
                bStart.value = br.start || '';
            }
            if (bEnd) {
                bEnd.value = br.end || '';
            }
        });
    }

    function collectWeekdaySchedule(fieldset) {
        if (!fieldset) {
            return null;
        }
        const prefix = fieldset.getAttribute('data-schedule-prefix') || 'wtm';
        const days = {};
        let any = false;
        fieldset.querySelectorAll('tr[data-day]').forEach((tr) => {
            const day = tr.getAttribute('data-day');
            const work = !!tr.querySelector('.wtm-day-work')?.checked;
            if (!work) {
                days[day] = { work: false };
                return;
            }
            any = true;
            const start = tr.querySelector('.wtm-day-start')?.value || '';
            const end = tr.querySelector('.wtm-day-end')?.value || '';
            const bStart = tr.querySelector('.wtm-day-break-start')?.value || '';
            const bEnd = tr.querySelector('.wtm-day-break-end')?.value || '';
            const breaks = [];
            if (bStart && bEnd) {
                breaks.push({ start: bStart, end: bEnd, paid: false });
            }
            days[day] = { work: true, start, end, breaks };
        });
        if (!any) {
            return null;
        }
        return { version: 1, days };
    }

    function buildBreakRulesPayload(form, existingBreakRules) {
        const fieldset = form.querySelector('.wtm-weekday-schedule');
        const schedule = collectWeekdaySchedule(fieldset);
        const base = (existingBreakRules && typeof existingBreakRules === 'object' && !Array.isArray(existingBreakRules))
            ? { ...existingBreakRules }
            : {};
        if (schedule) {
            base.weekday_schedule = schedule;
        } else {
            delete base.weekday_schedule;
        }
        return Object.keys(base).length ? base : null;
    }

    /**
     * Initialize models page
     */
    function init() {
        bindEvents();
    }

    /**
     * Bind event listeners
     */
    function bindEvents() {
        const createBtn = Utils.$('#create-model');
        if (createBtn) {
            Utils.on(createBtn, 'click', showCreateModal);
        }

        const editButtons = Utils.$$('[data-action="edit-model"]');
        editButtons.forEach(btn => {
            Utils.on(btn, 'click', handleEditModel);
        });

        const duplicateButtons = Utils.$$('[data-action="duplicate-model"]');
        duplicateButtons.forEach(btn => {
            Utils.on(btn, 'click', handleDuplicateModel);
        });

        const deleteButtons = Utils.$$('[data-action="delete-model"]');
        deleteButtons.forEach(btn => {
            Utils.on(btn, 'click', handleDeleteModel);
        });
    }

    /**
     * Show create model modal
     */
    function showCreateModal() {
        const t = (s) => (window.t ? window.t('arbeitszeitcheck', s) : s);
        const title = window.ArbeitszeitCheck?.l10n?.createModel || t('Create Working Time Model');
        const createLabel = window.ArbeitszeitCheck?.l10n?.create || t('Create');
        const cancelLabel = window.ArbeitszeitCheck?.l10n?.cancel || t('Cancel');
        const nameLabel = window.ArbeitszeitCheck?.l10n?.name || t('Name');
        const descriptionLabel = window.ArbeitszeitCheck?.l10n?.description || t('Description');
        const typeLabel = window.ArbeitszeitCheck?.l10n?.type || t('Type');
        const weeklyHoursLabel = window.ArbeitszeitCheck?.l10n?.weeklyHours || t('Weekly Hours');
        const dailyHoursLabel = window.ArbeitszeitCheck?.l10n?.dailyHours || t('Daily Hours');
        const workDaysPerWeekLabel = window.ArbeitszeitCheck?.l10n?.workDaysPerWeek || t('Work days per week');
        const isDefaultLabel = window.ArbeitszeitCheck?.l10n?.isDefault || t('Set as Default');
        
        const formContent = `
            <form id="create-model-form" class="form">
                <div class="form-group">
                    <label for="model-name" class="form-label">${nameLabel} <span class="form-required">*</span></label>
                    <input type="text" id="model-name" name="name" class="form-input" required 
                           placeholder="${nameLabel}" aria-describedby="model-name-help">
                    <p id="model-name-help" class="form-help">${window.ArbeitszeitCheck?.l10n?.modelNameHelp || 'Enter a name for this work schedule (e.g., "Full-Time", "Part-Time")'}</p>
                </div>
                <div class="form-group">
                    <label for="model-description" class="form-label">${descriptionLabel}</label>
                    <textarea id="model-description" name="description" class="form-textarea" rows="3"
                              placeholder="${descriptionLabel}"></textarea>
                </div>
                <div class="form-group">
                    <label for="model-type" class="form-label">${typeLabel}</label>
                    <select id="model-type" name="type" class="form-select">
                        ${modelTypeSelectOptions()}
                    </select>
                </div>
                <div class="form-group">
                    <label for="model-weekly-hours" class="form-label">${weeklyHoursLabel} <span class="form-required">*</span></label>
                    <input type="text" id="model-weekly-hours" name="weeklyHours" class="form-input" 
                           inputmode="decimal" pattern="^[0-9]+([\\.,][0-9]{1,2})?$" min="0" max="168" step="0.01" value="40" required
                           aria-describedby="model-weekly-hours-help">
                    <p id="model-weekly-hours-help" class="form-help">${window.ArbeitszeitCheck?.l10n?.weeklyHoursHelp || ''}</p>
                </div>
                <div class="form-group">
                    <label for="model-daily-hours" class="form-label">${dailyHoursLabel} <span class="form-required">*</span></label>
                    <input type="text" id="model-daily-hours" name="dailyHours" class="form-input" 
                           inputmode="decimal" pattern="^[0-9]+([\\.,][0-9]{1,2})?$" min="0" max="24" step="0.01" value="8" required
                           aria-describedby="model-daily-hours-help">
                    <p id="model-daily-hours-help" class="form-help">${window.ArbeitszeitCheck?.l10n?.dailyHoursHelp || ''}</p>
                </div>
                <div class="form-group">
                    <label for="model-work-days-per-week" class="form-label">${workDaysPerWeekLabel} <span class="form-required">*</span></label>
                    <input type="text" id="model-work-days-per-week" name="workDaysPerWeek" class="form-input"
                           inputmode="decimal" pattern="^[0-9]+([\\.,][0-9]{1,2})?$" min="1" max="7" step="0.01" value="5" required
                           aria-describedby="model-work-days-per-week-help">
                    <p id="model-work-days-per-week-help" class="form-help">${window.ArbeitszeitCheck?.l10n?.workDaysPerWeekHelp || ''}</p>
                </div>
                <div class="form-group">
                    <div class="form-checkbox">
                        <input type="checkbox" id="model-is-default" name="isDefault" value="1">
                        <label for="model-is-default">${isDefaultLabel}</label>
                    </div>
                </div>
                ${weekdayScheduleSectionHtml('create-wtm', null)}
                <div class="form-actions">
                    <button type="submit" class="btn btn--primary">${createLabel}</button>
                    <button type="button" class="btn btn--secondary" data-action="close-modal">${cancelLabel}</button>
                </div>
            </form>
        `;

        const modal = Components.createModal({
            id: 'create-model-modal',
            title: title,
            content: formContent,
            size: 'lg',
            closable: true,
            onClose: function() {
                const modalEl = document.getElementById('create-model-modal');
                if (modalEl && modalEl.parentNode) {
                    modalEl.parentNode.remove();
                }
            }
        });

        Components.openModal('create-model-modal');

        // Handle form submission
        const form = document.getElementById('create-model-form');
        if (form) {
            bindWeekdayScheduleFieldset(form.querySelector('.wtm-weekday-schedule'));
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                handleCreateModel(form);
            });
        }

        // Handle cancel button
        const cancelBtn = modal.querySelector('[data-action="close-modal"]');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function() {
                Components.closeModal(modal);
            });
        }
    }

    /**
     * Handle edit model
     */
    function handleEditModel(e) {
        const modelId = e.target.dataset.modelId;
        if (!modelId) return;

        Utils.ajax('/apps/arbeitszeitcheck/api/admin/working-time-models/' + modelId, {
            method: 'GET',
            onSuccess: function(data) {
                if (data.success && data.model) {
                    showEditModal(data.model);
                } else {
                    const errorMsg = (window.ArbeitszeitCheck?.l10n?.failedToLoadModel || (window.t && window.t('arbeitszeitcheck', 'Failed to load model'))) || 'Failed to load model';
                    Messaging.showError(errorMsg);
                }
            },
            onError: function(_error) {
                const errorMsg = (window.ArbeitszeitCheck?.l10n?.failedToLoadModel || (window.t && window.t('arbeitszeitcheck', 'Failed to load model'))) || 'Failed to load model';
                Messaging.showError(errorMsg);
            }
        });
    }

    /**
     * Duplicate an existing model as a new model.
     */
    function handleDuplicateModel(e) {
        const button = e.currentTarget || e.target;
        const modelId = button.dataset.modelId;
        if (!modelId) {
            return;
        }
        if (button.disabled) {
            return;
        }
        button.disabled = true;

        Utils.ajax('/apps/arbeitszeitcheck/api/admin/working-time-models/' + modelId, {
            method: 'GET',
            onSuccess: function(data) {
                button.disabled = false;
                if (!data.success || !data.model) {
                    const errorMsg = window.ArbeitszeitCheck?.l10n?.failedToCopyModel || 'Failed to copy model';
                    Messaging.showError(errorMsg);
                    return;
                }

                showDuplicateModal(data.model);
            },
            onError: function(_error) {
                button.disabled = false;
                const errorMsg = window.ArbeitszeitCheck?.l10n?.failedToCopyModel || 'Failed to copy model';
                Messaging.showError(errorMsg);
            }
        });
    }

    /**
     * Show duplicate modal with editable target name.
     */
    function showDuplicateModal(model) {
        const copyTitle = window.ArbeitszeitCheck?.l10n?.copyModelTitle || 'Copy Working Time Model';
        const copyLabel = window.ArbeitszeitCheck?.l10n?.copy || 'Copy';
        const cancelLabel = window.ArbeitszeitCheck?.l10n?.cancel || 'Cancel';
        const nameLabel = window.ArbeitszeitCheck?.l10n?.name || 'Name';
        const sourceLabel = window.ArbeitszeitCheck?.l10n?.sourceModel || 'Source model';
        const copyNoun = window.ArbeitszeitCheck?.l10n?.copyNoun || 'Copy';
        const suggestedName = getUniqueCopyName(String(model.name || 'Model'), copyNoun);

        const content = `
            <form id="duplicate-model-form" class="form">
                <div class="form-group">
                    <label class="form-label">${sourceLabel}</label>
                    <div class="form-help" role="note">${Utils.escapeHtml(model.name || '')}</div>
                </div>
                <div class="form-group">
                    <label for="duplicate-model-name" class="form-label">${nameLabel} <span class="form-required">*</span></label>
                    <input type="text" id="duplicate-model-name" name="name" class="form-input" required value="${Utils.escapeHtml(suggestedName)}">
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn--secondary" data-action="close-modal">${cancelLabel}</button>
                    <button type="submit" class="btn btn--primary">${copyLabel}</button>
                </div>
            </form>
        `;

        const modal = Components.createModal({
            id: 'duplicate-model-modal',
            title: copyTitle,
            content: content,
            size: 'md',
            closable: true,
            onClose: function() {
                const modalEl = document.getElementById('duplicate-model-modal');
                if (modalEl && modalEl.parentNode) {
                    modalEl.parentNode.remove();
                }
            }
        });

        Components.openModal('duplicate-model-modal');

        const form = document.getElementById('duplicate-model-form');
        if (form) {
            form.addEventListener('submit', function(ev) {
                ev.preventDefault();
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn?.disabled) {
                    return;
                }
                const targetName = String(new FormData(form).get('name') || '').trim();
                if (!targetName) {
                    Messaging.showError(window.ArbeitszeitCheck?.l10n?.failedToCopyModel || 'Failed to copy model');
                    return;
                }
                if (submitBtn) {
                    submitBtn.disabled = true;
                }

                const payload = {
                    name: targetName,
                    description: model.description || null,
                    type: model.type || 'full_time',
                    weeklyHours: Number(model.weeklyHours) || 40,
                    dailyHours: Number(model.dailyHours) || 8,
                    workDaysPerWeek: Number(model.workDaysPerWeek) || 5,
                    breakRules: model.breakRules || [],
                    overtimeRules: model.overtimeRules || [],
                    isDefault: false
                };

                Utils.ajax('/apps/arbeitszeitcheck/api/admin/working-time-models', {
                    method: 'POST',
                    data: payload,
                    onSuccess: function(createResponse) {
                        if (submitBtn) {
                            submitBtn.disabled = false;
                        }
                        if (createResponse.success) {
                            Components.closeModal(document.getElementById('duplicate-model-modal'));
                            const successMsg = window.ArbeitszeitCheck?.l10n?.modelCopied || 'Model copied successfully';
                            Messaging.showSuccess(successMsg);
                            setTimeout(() => location.reload(), 700);
                        } else {
                            const errorMsg = createResponse.error || window.ArbeitszeitCheck?.l10n?.failedToCopyModel || 'Failed to copy model';
                            Messaging.showError(errorMsg);
                        }
                    },
                    onError: function(_error) {
                        if (submitBtn) {
                            submitBtn.disabled = false;
                        }
                        const errorMsg = window.ArbeitszeitCheck?.l10n?.failedToCopyModel || 'Failed to copy model';
                        Messaging.showError(errorMsg);
                    }
                });
            });
        }

        const cancelBtn = modal.querySelector('[data-action="close-modal"]');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function() {
                Components.closeModal(modal);
            });
        }
    }

    function getExistingModelNamesLowerCase() {
        const rows = Utils.$$('#models-tbody tr[data-model-id]');
        const names = new Set();
        rows.forEach((row) => {
            const nameCell = row.querySelector('td:first-child');
            const name = String(nameCell?.textContent || '').trim().toLowerCase();
            if (name) {
                names.add(name);
            }
        });
        return names;
    }

    function getUniqueCopyName(baseName, copyNoun) {
        const existing = getExistingModelNamesLowerCase();
        const primary = `${baseName} (${copyNoun})`;
        if (!existing.has(primary.toLowerCase())) {
            return primary;
        }
        let n = 2;
        let candidate = `${baseName} (${copyNoun} ${n})`;
        while (existing.has(candidate.toLowerCase())) {
            n += 1;
            candidate = `${baseName} (${copyNoun} ${n})`;
        }
        return candidate;
    }

    /**
     * Show edit modal
     */
    function showEditModal(model) {
        if (!model || !model.id) {
            const errorMsg = (window.ArbeitszeitCheck?.l10n?.invalidModelData || window.t && window.t('arbeitszeitcheck', 'Invalid model data')) || 'Invalid model data';
            Messaging.showError(errorMsg);
            return;
        }

        const title = window.ArbeitszeitCheck?.l10n?.editModel || 'Edit Working Time Model';
        const saveLabel = window.ArbeitszeitCheck?.l10n?.save || 'Save';
        const cancelLabel = window.ArbeitszeitCheck?.l10n?.cancel || 'Cancel';
        const nameLabel = window.ArbeitszeitCheck?.l10n?.name || 'Name';
        const descriptionLabel = window.ArbeitszeitCheck?.l10n?.description || 'Description';
        const typeLabel = window.ArbeitszeitCheck?.l10n?.type || 'Type';
        const weeklyHoursLabel = window.ArbeitszeitCheck?.l10n?.weeklyHours || 'Weekly Hours';
        const dailyHoursLabel = window.ArbeitszeitCheck?.l10n?.dailyHours || 'Daily Hours';
        const workDaysPerWeekLabel = window.ArbeitszeitCheck?.l10n?.workDaysPerWeek || 'Work days per week';
        const isDefaultLabel = window.ArbeitszeitCheck?.l10n?.isDefault || 'Set as Default';
        
        const formContent = `
            <form id="edit-model-form" class="form">
                <input type="hidden" id="model-id" name="id" value="${model.id}">
                <div class="form-group">
                    <label for="edit-model-name" class="form-label">${nameLabel} <span class="form-required">*</span></label>
                    <input type="text" id="edit-model-name" name="name" class="form-input" required 
                           value="${Utils.escapeHtml(model.name || '')}" placeholder="${nameLabel}">
                </div>
                <div class="form-group">
                    <label for="edit-model-description" class="form-label">${descriptionLabel}</label>
                    <textarea id="edit-model-description" name="description" class="form-textarea" rows="3"
                              placeholder="${descriptionLabel}">${Utils.escapeHtml(model.description || '')}</textarea>
                </div>
                <div class="form-group">
                    <label for="edit-model-type" class="form-label">${typeLabel}</label>
                    <select id="edit-model-type" name="type" class="form-select">
                        ${modelTypeSelectOptions(model.type)}
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit-model-weekly-hours" class="form-label">${weeklyHoursLabel} <span class="form-required">*</span></label>
                    <input type="text" id="edit-model-weekly-hours" name="weeklyHours" class="form-input" 
                           inputmode="decimal" pattern="^[0-9]+([\\.,][0-9]{1,2})?$" min="0" max="168" step="0.01" value="${model.weeklyHours || 40}" required
                           aria-describedby="edit-model-weekly-hours-help">
                    <p id="edit-model-weekly-hours-help" class="form-help">${window.ArbeitszeitCheck?.l10n?.weeklyHoursHelp || ''}</p>
                </div>
                <div class="form-group">
                    <label for="edit-model-daily-hours" class="form-label">${dailyHoursLabel} <span class="form-required">*</span></label>
                    <input type="text" id="edit-model-daily-hours" name="dailyHours" class="form-input" 
                           inputmode="decimal" pattern="^[0-9]+([\\.,][0-9]{1,2})?$" min="0" max="24" step="0.01" value="${model.dailyHours || 8}" required
                           aria-describedby="edit-model-daily-hours-help">
                    <p id="edit-model-daily-hours-help" class="form-help">${window.ArbeitszeitCheck?.l10n?.dailyHoursHelp || ''}</p>
                </div>
                <div class="form-group">
                    <label for="edit-model-work-days-per-week" class="form-label">${workDaysPerWeekLabel} <span class="form-required">*</span></label>
                    <input type="text" id="edit-model-work-days-per-week" name="workDaysPerWeek" class="form-input"
                           inputmode="decimal" pattern="^[0-9]+([\\.,][0-9]{1,2})?$" min="1" max="7" step="0.01" value="${model.workDaysPerWeek || 5}" required
                           aria-describedby="edit-model-work-days-per-week-help">
                    <p id="edit-model-work-days-per-week-help" class="form-help">${window.ArbeitszeitCheck?.l10n?.workDaysPerWeekHelp || ''}</p>
                </div>
                <div class="form-group">
                    <div class="form-checkbox">
                        <input type="checkbox" id="edit-model-is-default" name="isDefault" value="1" ${model.isDefault ? 'checked' : ''}>
                        <label for="edit-model-is-default">${isDefaultLabel}</label>
                    </div>
                </div>
                ${weekdayScheduleSectionHtml('edit-wtm', (model.breakRules && model.breakRules.weekday_schedule) ? model.breakRules.weekday_schedule : null)}
                <div class="form-actions">
                    <button type="button" class="btn btn--secondary" data-action="close-modal">${cancelLabel}</button>
                    <button type="submit" class="btn btn--primary">${saveLabel}</button>
                </div>
            </form>
        `;

        const modal = Components.createModal({
            id: 'edit-model-modal',
            title: title,
            content: formContent,
            size: 'lg',
            closable: true,
            onClose: function() {
                const modalEl = document.getElementById('edit-model-modal');
                if (modalEl && modalEl.parentNode) {
                    modalEl.parentNode.remove();
                }
            }
        });

        Components.openModal('edit-model-modal');

        // Handle form submission
        const form = document.getElementById('edit-model-form');
        if (form) {
            bindWeekdayScheduleFieldset(form.querySelector('.wtm-weekday-schedule'));
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                handleUpdateModel(form, model.id, model.breakRules || null);
            });
        }

        // Handle cancel button
        const cancelBtn = modal.querySelector('[data-action="close-modal"]');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function() {
                Components.closeModal(modal);
            });
        }
    }

    /**
     * Handle create model form submission
     */
    function handleCreateModel(form) {
        const formData = new FormData(form);
        const breakRules = buildBreakRulesPayload(form, null);
        const data = {
            name: formData.get('name'),
            description: formData.get('description') || null,
            type: formData.get('type') || 'full_time',
            weeklyHours: parseLocalizedDecimal(formData.get('weeklyHours'), 40),
            dailyHours: parseLocalizedDecimal(formData.get('dailyHours'), 8),
            workDaysPerWeek: parseLocalizedDecimal(formData.get('workDaysPerWeek'), 5),
            isDefault: formData.get('isDefault') === '1'
        };
        if (breakRules) {
            data.breakRules = breakRules;
        }

        Utils.ajax('/apps/arbeitszeitcheck/api/admin/working-time-models', {
            method: 'POST',
            data: data,
            onSuccess: function(response) {
                if (response.success) {
                    const successMsg = window.ArbeitszeitCheck?.l10n?.modelCreated || (window.t && window.t('arbeitszeitcheck', 'Model created successfully')) || 'Model created successfully';
                    Messaging.showSuccess(successMsg);
                    Components.closeModal(document.getElementById('create-model-modal'));
                    // Reload page to show new model
                    setTimeout(() => location.reload(), 1000);
                } else {
                    const errorMsg = response.error || (window.ArbeitszeitCheck?.l10n?.failedToCreateModel || (window.t && window.t('arbeitszeitcheck', 'Failed to create model'))) || 'Failed to create model';
                    Messaging.showError(errorMsg);
                }
            },
            onError: function(_error) {
                const errorMsg = window.ArbeitszeitCheck?.l10n?.failedToCreateModel || (window.t && window.t('arbeitszeitcheck', 'Failed to create model')) || 'Failed to create model';
                Messaging.showError(errorMsg);
            }
        });
    }

    /**
     * Handle update model form submission
     */
    function handleUpdateModel(form, modelId, existingBreakRules) {
        const formData = new FormData(form);
        const breakRules = buildBreakRulesPayload(form, existingBreakRules);
        const data = {
            name: formData.get('name'),
            description: formData.get('description') || null,
            type: formData.get('type') || 'full_time',
            weeklyHours: parseLocalizedDecimal(formData.get('weeklyHours'), 40),
            dailyHours: parseLocalizedDecimal(formData.get('dailyHours'), 8),
            workDaysPerWeek: parseLocalizedDecimal(formData.get('workDaysPerWeek'), 5),
            isDefault: formData.get('isDefault') === '1'
        };
        if (breakRules) {
            data.breakRules = breakRules;
        } else if (existingBreakRules && typeof existingBreakRules === 'object') {
            // Explicitly clear weekday schedule while keeping other break rule keys
            const cleared = { ...existingBreakRules };
            delete cleared.weekday_schedule;
            data.breakRules = cleared;
        }

        Utils.ajax('/apps/arbeitszeitcheck/api/admin/working-time-models/' + modelId, {
            method: 'PUT',
            data: data,
            onSuccess: function(response) {
                if (response.success) {
                    const successMsg = window.ArbeitszeitCheck?.l10n?.modelUpdated || 
                                        (window.t && window.t('arbeitszeitcheck', 'Model updated successfully')) || 
                                        'Model updated successfully';
                    Messaging.showSuccess(successMsg);
                    Components.closeModal(document.getElementById('edit-model-modal'));
                    // Reload page to show updated model
                    setTimeout(() => location.reload(), 1000);
                } else {
                    const errorMsg = response.error || (window.ArbeitszeitCheck?.l10n?.failedToUpdateModel || (window.t && window.t('arbeitszeitcheck', 'Failed to update model'))) || 'Failed to update model';
                    Messaging.showError(errorMsg);
                }
            },
            onError: function(_error) {
                const errorMsg = window.ArbeitszeitCheck?.l10n?.failedToUpdateModel || (window.t && window.t('arbeitszeitcheck', 'Failed to update model')) || 'Failed to update model';
                Messaging.showError(errorMsg);
            }
        });
    }

    /**
     * Handle delete model with fail-closed destructive confirmation (audit-critical).
     */
    async function handleDeleteModel(e) {
        const button = e.currentTarget || e.target;
        const modelId = button.dataset.modelId;
        if (!modelId) {
            return;
        }

        const row = button.closest('tr');
        const rawName = row?.querySelector('td:first-child')?.textContent?.trim();
        const modelName = rawName || window.ArbeitszeitCheck?.l10n?.thisWorkSchedule || (window.t && window.t('arbeitszeitcheck', 'this work schedule')) || 'this work schedule';

        const title = window.ArbeitszeitCheck?.l10n?.deleteModelTitle ||
            (window.t && window.t('arbeitszeitcheck', 'Delete working time model')) ||
            'Delete working time model';

        const bodyTemplate = window.ArbeitszeitCheck?.l10n?.confirmDeleteModelWithName ||
            window.ArbeitszeitCheck?.l10n?.confirmDeleteModel ||
            (window.t && window.t(
                'arbeitszeitcheck',
                'Are you sure you want to delete "{name}"?\n\nThis will permanently remove this work schedule. If any employees are using this schedule, you should assign them to a different schedule first.\n\nThis action cannot be undone.'
            )) ||
            'Are you sure you want to delete "{name}"?\n\nThis will permanently remove this work schedule. If any employees are using this schedule, you should assign them to a different schedule first.\n\nThis action cannot be undone.';

        const message = String(bodyTemplate)
            .replace(/\{name\}/g, modelName)
            .replace(/\\n/g, '\n')
            .replace(/\n/g, '\n');

        const confirmed = await Utils.confirmDestructiveAction({
            title,
            message,
            variant: 'danger',
            requireTypedConfirm: true,
            typedConfirmPhrase: 'DELETE',
        });
        if (!confirmed) {
            return;
        }

        Utils.ajax('/apps/arbeitszeitcheck/api/admin/working-time-models/' + modelId, {
            method: 'DELETE',
            onSuccess: function(data) {
                if (data.success) {
                    const successMsg = window.ArbeitszeitCheck?.l10n?.modelDeleted ||
                        (window.t && window.t('arbeitszeitcheck', 'Working time model deleted successfully')) ||
                        'Working time model deleted successfully';
                    Messaging.showSuccess(successMsg);
                    location.reload();
                } else {
                    const errorMsg = data.error ||
                        (window.ArbeitszeitCheck?.l10n?.failedToDeleteModel ||
                            (window.t && window.t('arbeitszeitcheck', 'Failed to delete model'))) ||
                        'Failed to delete model';
                    Messaging.showError(errorMsg);
                }
            },
            onError: function(_error) {
                const errorMsg = window.ArbeitszeitCheck?.l10n?.failedToDeleteModel ||
                    (window.t && window.t('arbeitszeitcheck', 'Failed to delete model')) ||
                    'Failed to delete model';
                Messaging.showError(errorMsg);
            }
        });
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
