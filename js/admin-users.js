/**
 * Admin Users JavaScript for arbeitszeitcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

(function() {
    'use strict';

    const Utils = window.ArbeitszeitCheckUtils || {};
    const Components = window.ArbeitszeitCheckComponents || {};
    const Messaging = window.ArbeitszeitCheckMessaging || {};

    function parseLocalizedDecimal(value) {
        if (value === null || value === undefined || value === '') {
            return undefined;
        }
        const normalized = String(value).trim().replace(/\s+/g, '').replace(',', '.');
        if (!/^-?\d+(\.\d+)?$/.test(normalized)) {
            return undefined;
        }
        const parsed = Number(normalized);
        return Number.isFinite(parsed) ? parsed : undefined;
    }

    function localizedEntitlementSourceLabel(source, t) {
        if (source === 'manual') return t('sourceManual', 'Manual');
        if (source === 'manual_exception') return t('sourceManualException', 'Manual exception');
        if (source === 'simple_model') return t('sourceSimpleModel', 'Model based');
        if (source === 'tariff') return t('sourceTariff', 'Tariff');
        return source || t('notAvailable', 'Not available');
    }

    function formatPreviewDays(days) {
        const parsed = Number(days);
        if (!Number.isFinite(parsed)) {
            return '0';
        }
        return Number.isInteger(parsed) ? String(parsed) : String(Math.round(parsed * 100) / 100);
    }

    function humanEntitlementLayerLabel(code, t) {
        const key = String(code || '');
        const labels = {
            L0: t('organisationDefault', 'Organisation default'),
            L1: t('workingTimeModelDefault', 'Working time model default'),
            L2: t('teamPolicy', 'Team policy'),
            L3: t('individualRule', 'Individual rule'),
            legacy: t('legacyFallback', 'Legacy fallback (25 d.)'),
            inherit: t('vacationModeInherit', 'Inherit from team / model / organisation'),
        };
        return labels[key] || key;
    }

    /**
     * Plain-language summary for HR (never raw JSON).
     *
     * @param {object|string|null|undefined} trace
     * @param {function(string, string): string} t
     * @returns {string}
     */
    function buildEntitlementPreviewSummary(trace, t) {
        if (!trace) {
            return '';
        }
        if (typeof trace === 'string') {
            return trace;
        }
        if (typeof trace !== 'object') {
            return '';
        }
        if (trace.formula && trace.inputs) {
            const workDays = trace.inputs.work_days_per_week;
            const referenceDays = trace.inputs.reference_days;
            const referenceWeekDays = trace.inputs.reference_week_days;
            if (workDays && referenceDays && referenceWeekDays) {
                return `${trace.formula} (${referenceDays}, ${workDays}/${referenceWeekDays})`;
            }
            return String(trace.formula);
        }
        const matched = trace.matched_layer || trace.winner?.layer;
        if (matched) {
            const layerLabel = humanEntitlementLayerLabel(matched, t);
            const template = t(
                'previewResolvedByLayer',
                'Determined by: {layer}.'
            );
            return template.replace('{layer}', layerLabel);
        }
        const layers = Array.isArray(trace.layers_evaluated) ? trace.layers_evaluated : [];
        const hit = layers.find((row) => row && row.matched === true);
        if (hit && hit.layer) {
            const layerLabel = humanEntitlementLayerLabel(hit.layer, t);
            const days = hit.days != null ? formatPreviewDays(hit.days) : '';
            const mode = hit.mode || hit.reason || '';
            const parts = [t('previewResolvedByLayer', 'Determined by: {layer}.').replace('{layer}', layerLabel)];
            if (days) {
                parts.push(`${days} ${t('vacationDays', 'vacation days')}`);
            }
            if (mode) {
                parts.push(String(mode));
            }
            return parts.join(' ');
        }
        if (trace.degraded) {
            return t(
                'previewDegradedHint',
                'Resolution ran in a degraded state — open technical details or check layered vacation settings.'
            );
        }
        return '';
    }

    /**
     * Audit-oriented JSON for the optional &lt;details&gt; block only.
     *
     * @param {object|null|undefined} trace
     * @returns {string}
     */
    function buildEntitlementPreviewTechnical(trace) {
        if (!trace || typeof trace !== 'object') {
            return '';
        }
        try {
            const json = JSON.stringify(trace, null, 2);
            return json && json !== '{}' ? json : '';
        } catch (e) {
            return '';
        }
    }

    /**
     * @param {number|string} days
     * @param {string} sourceLabel
     * @param {string} summaryText
     * @param {object|string|null|undefined} traceObject
     * @param {function(string, string): string} t
     * @param {object|null|undefined} prorationPreview
     */
    function paintEntitlementPreview(previewEl, days, sourceLabel, summaryText, traceObject, t, prorationPreview) {
        if (!previewEl) {
            return;
        }
        const value = previewEl.querySelector('.entitlement-preview__value');
        const meta = previewEl.querySelector('.entitlement-preview__meta');
        const summary = previewEl.querySelector('.entitlement-preview__summary');
        const details = previewEl.querySelector('.entitlement-preview__details');
        const technical = previewEl.querySelector('.entitlement-preview__technical code');

        if (value) {
            value.textContent = `${formatPreviewDays(days)} ${t('vacationDays', 'vacation days')}`;
        }
        if (meta) {
            meta.textContent = sourceLabel || '';
        }

        const summaryLine = summaryText
            || buildEntitlementPreviewSummary(traceObject, t);
        if (summary) {
            summary.textContent = summaryLine;
            summary.hidden = !summaryLine;
        }

        const technicalJson = buildEntitlementPreviewTechnical(
            traceObject && typeof traceObject === 'object' ? traceObject : null
        );
        if (details) {
            details.hidden = !technicalJson;
        }
        if (technical) {
            technical.textContent = technicalJson;
        }

        paintProrationNote(previewEl, prorationPreview, t);
    }

    /**
     * Create, update, or remove the proration note under the entitlement preview.
     *
     * @param {HTMLElement|null} previewEl
     * @param {object|null|undefined} prorationPreview
     * @param {function(string, string): string} t
     */
    function paintProrationNote(previewEl, prorationPreview, t) {
        if (!previewEl) {
            return;
        }
        let prorationEl = previewEl.querySelector('.entitlement-preview__proration');
        const noteHtml = buildProrationNoteHtml(prorationPreview, t);
        if (!noteHtml) {
            if (prorationEl) {
                prorationEl.remove();
            }
            return;
        }
        if (!prorationEl) {
            prorationEl = document.createElement('p');
            prorationEl.className = 'entitlement-preview__proration';
            const anchor = previewEl.querySelector('.entitlement-preview__meta')
                || previewEl.querySelector('.entitlement-preview__value');
            if (anchor && anchor.parentNode) {
                anchor.insertAdjacentElement('afterend', prorationEl);
            } else {
                previewEl.appendChild(prorationEl);
            }
        }
        prorationEl.outerHTML = noteHtml;
    }

    /**
     * Map simulate/profile API fields to the preview proration shape.
     *
     * @param {object|null|undefined} resp
     * @returns {object|null}
     */
    function buildProrationPreviewFromResponse(resp) {
        if (!resp || !resp.prorated) {
            return null;
        }
        return {
            days: resp.proratedEntitlementDays,
            fullYearDays: resp.fullYearEntitlementDays != null
                ? resp.fullYearEntitlementDays
                : resp.effectiveEntitlementDays,
            prorated: true,
            prorationMethod: resp.prorationMethod,
            monthsCovered: resp.monthsCovered,
            employedInYear: resp.employedInYear,
        };
    }

    /**
     * Headline days for the preview: prorated when applicable.
     *
     * @param {object|null|undefined} resp
     * @returns {number}
     */
    function previewDaysFromResponse(resp) {
        if (!resp) {
            return 0;
        }
        if (resp.prorated && resp.proratedEntitlementDays != null) {
            return Number(resp.proratedEntitlementDays);
        }
        return Number(resp.effectiveEntitlementDays || 0);
    }

    function buildEntitlementPreviewHtml(entitlementPreview, t) {
        const days = entitlementPreview ? formatPreviewDays(entitlementPreview.days) : '';
        const valueText = entitlementPreview
            ? `${days} ${t('vacationDays', 'vacation days')}`
            : t('notAvailable', 'Not available');
        const metaText = entitlementPreview
            ? localizedEntitlementSourceLabel(entitlementPreview.source, t)
            : '';
        const trace = entitlementPreview?.calculationTrace || null;
        const summaryText = buildEntitlementPreviewSummary(trace, t);
        const technicalJson = buildEntitlementPreviewTechnical(trace);
        const detailsBlock = technicalJson
            ? `<details class="entitlement-preview__details">
                    <summary>${Utils.escapeHtml(t('previewTechnicalDetails', 'Technical details (audit)'))}</summary>
                    <pre class="entitlement-preview__technical"><code>${Utils.escapeHtml(technicalJson)}</code></pre>
               </details>`
            : '';

        return `<p class="entitlement-preview__value">${Utils.escapeHtml(valueText)}</p>
            <p class="entitlement-preview__meta">${Utils.escapeHtml(metaText)}</p>
            ${buildProrationNoteHtml(entitlementPreview, t)}
            <p class="entitlement-preview__summary"${summaryText ? '' : ' hidden'}>${Utils.escapeHtml(summaryText)}</p>
            ${detailsBlock}`;
    }

    /**
     * Human-readable note explaining a pro-rata reduction for a partial
     * employment year, e.g. "Prorated for partial year: 20 of 30 days
     * (employed 8 of 12 months)". Returns an empty string when the full annual
     * entitlement applies, so the preview stays uncluttered for the common case.
     */
    function buildProrationNoteHtml(entitlementPreview, t) {
        if (!entitlementPreview || !entitlementPreview.prorated) {
            return '';
        }
        const full = formatPreviewDays(entitlementPreview.fullYearDays);
        const prorated = formatPreviewDays(entitlementPreview.days);
        let note;
        if (entitlementPreview.employedInYear === false) {
            note = t('entitlementNotEmployedThisYear', 'No entitlement this year: the employment period does not cover this calendar year.');
        } else if (entitlementPreview.prorationMethod === 'daily') {
            note = (t('entitlementProratedDaily', 'Prorated for partial year: {prorated} of {full} days (daily method).'))
                .replace('{prorated}', prorated)
                .replace('{full}', full);
        } else {
            const months = String(entitlementPreview.monthsCovered != null ? entitlementPreview.monthsCovered : '');
            note = (t('entitlementProratedTwelfths', 'Prorated for partial year: {prorated} of {full} days (employed {months} of 12 months).'))
                .replace('{prorated}', prorated)
                .replace('{full}', full)
                .replace('{months}', months);
        }
        return `<p class="entitlement-preview__proration">${Utils.escapeHtml(note)}</p>`;
    }

    /** Prefer server-injected l10n; window.t may be unavailable. */
    function auMsg(key, englishFallback) {
        const v = window.ArbeitszeitCheck?.l10n?.[key];
        if (v !== undefined && v !== '') {
            return v;
        }
        if (typeof window.t === 'function' && englishFallback) {
            return window.t('arbeitszeitcheck', englishFallback);
        }
        return englishFallback || '';
    }

    let searchTimeout = null;
    const USERS_TABLE_COLS = 8;
    const cfg = window.ArbeitszeitCheck && window.ArbeitszeitCheck.adminUsersConfig
        ? window.ArbeitszeitCheck.adminUsersConfig
        : {};
    const USERS_PAGE_SIZE = Number(cfg.pageSize) > 0 ? Number(cfg.pageSize) : 50;
    const USERS_MIN_SEARCH = Number(cfg.minSearchLength) >= 0 ? Number(cfg.minSearchLength) : 2;
    let listOffset = Number(cfg.initialOffset) >= 0 ? Number(cfg.initialOffset) : 0;
    let listSearch = '';
    let listTotal = Number(cfg.initialTotal) >= 0 ? Number(cfg.initialTotal) : 0;
    let listTruncated = false;

    function buildApiUrl(path) {
        if (Utils && typeof Utils.resolveUrl === 'function') {
            return Utils.resolveUrl(path);
        }
        const oc = (typeof window !== 'undefined' && window.OC) || (typeof OC !== 'undefined' ? OC : null);
        if (oc && typeof oc.generateUrl === 'function') {
            return oc.generateUrl(path);
        }
        return path;
    }

    function fetchTariffRuleSets() {
        return new Promise((resolve) => {
            Utils.ajax(buildApiUrl('/apps/arbeitszeitcheck/api/admin/tariff-rule-sets'), {
                method: 'GET',
                onSuccess: function(data) {
                    if (data && data.success && Array.isArray(data.ruleSets)) {
                        resolve(data.ruleSets);
                        return;
                    }
                    resolve([]);
                },
                onError: function() {
                    resolve([]);
                }
            });
        });
    }

    /**
     * Initialize users page
     */
    function init() {
        bindEvents();
        const initialShown = Number(cfg.initialShown) >= 0 ? Number(cfg.initialShown) : 0;
        const initialTotal = Number(cfg.initialTotal) >= 0 ? Number(cfg.initialTotal) : initialShown;
        listTotal = initialTotal;
        updateUsersPagination(initialShown, initialTotal, {});
    }

    /**
     * Bind event listeners
     */
    function bindEvents() {
        const searchInput = Utils.$('#user-search');
        if (searchInput) {
            Utils.on(searchInput, 'input', handleSearch);
        }

        const refreshBtn = Utils.$('#refresh-users');
        if (refreshBtn) {
            Utils.on(refreshBtn, 'click', function() {
                const searchInput = Utils.$('#user-search');
                if (searchInput) {
                    searchInput.value = '';
                }
                listSearch = '';
                listOffset = 0;
                loadUsers();
            });
        }

        const prevBtn = Utils.$('#users-page-prev');
        const nextBtn = Utils.$('#users-page-next');
        if (prevBtn) {
            Utils.on(prevBtn, 'click', function() {
                if (listOffset <= 0 || listSearch !== '') {
                    return;
                }
                listOffset = Math.max(0, listOffset - USERS_PAGE_SIZE);
                loadUsers();
            });
        }
        if (nextBtn) {
            Utils.on(nextBtn, 'click', function() {
                if (listSearch !== '' || listOffset + USERS_PAGE_SIZE >= listTotal) {
                    return;
                }
                listOffset += USERS_PAGE_SIZE;
                loadUsers();
            });
        }

        const editButtons = Utils.$$('[data-action="edit-user"]');
        editButtons.forEach(btn => { Utils.on(btn, 'click', handleEditUser); });
        const historyButtons = Utils.$$('[data-action="history-user"]');
        historyButtons.forEach(btn => { Utils.on(btn, 'click', handleHistoryUser); });
    }

    /**
     * Handle search input
     */
    function handleSearch(e) {
        const query = e.target.value.trim();
        listSearch = query;
        listOffset = 0;

        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            loadUsers();
        }, 300);
    }

    /**
     * Load users from API (paginated browse or search).
     */
    function loadUsers() {
        const tbody = Utils.$('#users-tbody');
        if (!tbody) {
            return;
        }

        const search = listSearch.trim();
        if (search.length > 0 && search.length < USERS_MIN_SEARCH) {
            tbody.innerHTML = '<tr><td colspan="' + USERS_TABLE_COLS + '" class="text-center">' + Utils.escapeHtml(auMsg('searchMinLength', 'Type at least 2 characters to search.')) + '</td></tr>';
            updateUsersPagination(0, 0, { searchPending: true });
            return;
        }

        tbody.innerHTML = '<tr><td colspan="' + USERS_TABLE_COLS + '" class="text-center">' + auMsg('loadingEllipsis', 'Loading…') + '</td></tr>';

        const params = new URLSearchParams({ limit: String(USERS_PAGE_SIZE) });
        if (search !== '') {
            params.set('search', search);
        } else {
            params.set('offset', String(listOffset));
        }
        const url = buildApiUrl('/apps/arbeitszeitcheck/api/admin/users') + '?' + params.toString();

        Utils.ajax(url, {
            method: 'GET',
            onSuccess: function(data) {
                if (data.success && data.users) {
                    listTotal = Number.isFinite(data.total) ? Number(data.total) : data.users.length;
                    listTruncated = !!data.truncated;
                    if (typeof data.offset === 'number' && search === '') {
                        listOffset = data.offset;
                    }
                    renderUsers(data.users, listTotal);
                } else {
                    tbody.innerHTML = '<tr><td colspan="' + USERS_TABLE_COLS + '" class="text-center">' + auMsg('errorLoadingUsers', 'Error loading users') + '</td></tr>';
                    updateUsersPagination(0, 0, {});
                }
            },
            onError: function() {
                tbody.innerHTML = '<tr><td colspan="' + USERS_TABLE_COLS + '" class="text-center">' + auMsg('errorLoadingUsers', 'Error loading users') + '</td></tr>';
                if (Messaging && Messaging.showError) {
                    Messaging.showError(auMsg('failedToLoadUsersRetry', 'Failed to load users. Please try again.'));
                }
                updateUsersPagination(0, 0, {});
            },
        });
    }

    function formatPaginationText(shown, total, options) {
        const opts = options || {};
        if (opts.searchPending) {
            return auMsg('searchMinLength', 'Type at least 2 characters to search.');
        }
        const count = Number.isFinite(shown) ? shown : 0;
        const totalCount = Number.isFinite(total) ? total : count;
        if (listSearch.trim() !== '') {
            let text = auMsg('searchMatches', '{count} employees match your search')
                .replace('{count}', String(count));
            if (listTruncated || count >= USERS_PAGE_SIZE) {
                text += ' ' + auMsg('searchRefineHint', 'More than {count} matches — refine your search to find a specific person.')
                    .replace('{count}', String(count));
            }
            return text;
        }
        if (totalCount <= 0 || count <= 0) {
            return auMsg('noUsersFound', 'No users found');
        }
        const from = listOffset + 1;
        const to = listOffset + count;
        return auMsg('showingEmployeesRange', 'Showing employees {from}–{to} of {total}')
            .replace('{from}', String(from))
            .replace('{to}', String(to))
            .replace('{total}', String(totalCount));
    }

    function updateUsersPagination(shown, total, options) {
        const meta = document.getElementById('users-pagination');
        const textEl = document.getElementById('users-pagination-text');
        const pager = document.getElementById('users-pager');
        const prevBtn = Utils.$('#users-page-prev');
        const nextBtn = Utils.$('#users-page-next');
        const text = formatPaginationText(shown, total, options);
        if (textEl) {
            textEl.textContent = text;
        } else if (meta) {
            meta.textContent = text;
        }

        const browseMode = listSearch.trim() === '' && !(options && options.searchPending);
        const showPager = browseMode && total > USERS_PAGE_SIZE;
        if (pager) {
            pager.hidden = !showPager;
        }
        if (prevBtn) {
            prevBtn.disabled = !showPager || listOffset <= 0;
        }
        if (nextBtn) {
            nextBtn.disabled = !showPager || listOffset + USERS_PAGE_SIZE >= total;
        }
    }

    /**
     * Render users table
     */
    function renderUsers(users, total) {
        const tbody = Utils.$('#users-tbody');
        if (!tbody) return;

        const totalCount = Number.isFinite(total) ? total : users.length;
        updateUsersPagination(users.length, totalCount, {});

        if (users.length === 0) {
            tbody.innerHTML = '<tr><td colspan="' + USERS_TABLE_COLS + '" class="text-center">' + auMsg('noUsersFound', 'No users found') + '</td></tr>';
            return;
        }

        const formatDate = (iso) => {
            if (!iso) return '-';
            const m = iso.match(/^(\d{4})-(\d{2})-(\d{2})$/);
            return m ? m[3] + '.' + m[2] + '.' + m[1] : iso;
        };
        const ongoingLabel = auMsg('ongoing', 'ongoing');

        tbody.innerHTML = users.map(user => {
            const vacation = user.vacationDaysPerYear != null ? String(user.vacationDaysPerYear) : '-';
            const start = user.workingTimeModelStartDate || null;
            const end = user.workingTimeModelEndDate || null;
            const validity = start ? (formatDate(start) + (end ? ' – ' + formatDate(end) : ' – ' + ongoingLabel)) : '-';
            const stichtag = user.overtimeTrackingFrom || '';
            const stichtagCell = stichtag
                ? `<span class="badge badge--info">${Utils.escapeHtml(formatDate(stichtag))}</span>`
                : `<span class="badge badge--warning">${Utils.escapeHtml(auMsg('notSet', 'Not set'))}</span>`;
            const td = (label, html, cls = '') => Utils.responsiveTd
                ? Utils.responsiveTd(label, html, cls)
                : `<td${cls ? ` class="${cls}"` : ''}>${html}</td>`;
            return `
            <tr data-user-id="${Utils.escapeHtml(user.userId)}">
                ${td(auMsg('colName', 'Name'), Utils.escapeHtml(user.displayName))}
                ${td(auMsg('colEmail', 'Email'), Utils.escapeHtml(user.email || '-'))}
                ${td(auMsg('workingTimeModel', 'Working Time Model'), user.workingTimeModel
                    ? Utils.escapeHtml(user.workingTimeModel.name)
                    : `<span class="text-muted">${auMsg('notAssigned', 'Not assigned')}</span>`)}
                ${td(auMsg('vacationDaysCol', 'Vacation days'), Utils.escapeHtml(vacation))}
                ${td(auMsg('colValidFromTo', 'Valid from / to'), Utils.escapeHtml(validity))}
                ${td(auMsg('colOvertimeStichtag', 'Overtime Stichtag'), stichtagCell)}
                ${td(auMsg('status', 'Status'), `<span class="badge badge--${user.enabled ? 'success' : 'error'}">
                        ${user.enabled
                        ? auMsg('enabled', 'Enabled')
                        : auMsg('disabled', 'Disabled')}
                    </span>`)}
                ${td(auMsg('actions', 'Actions'), `<div class="user-actions azc-table-actions" role="group" aria-label="${Utils.escapeHtml(auMsg('actions', 'Actions'))}">
                        <button type="button" class="btn btn--sm btn--tertiary" 
                            data-action="history-user" 
                            data-user-id="${Utils.escapeHtml(user.userId)}"
                            data-user-name="${Utils.escapeHtml(user.displayName || user.userId)}"
                            aria-label="${Utils.escapeHtml(auMsg('history', 'History'))}">
                            ${Utils.escapeHtml(auMsg('history', 'History'))}
                        </button>
                        <button type="button" class="btn btn--sm btn--secondary" 
                            data-action="edit-user" 
                            data-user-id="${Utils.escapeHtml(user.userId)}"
                            aria-label="${Utils.escapeHtml(auMsg('edit', 'Edit'))}">
                            ${Utils.escapeHtml(auMsg('edit', 'Edit'))}
                        </button>
                    </div>`, 'actions-cell')}
            </tr>
        `;
        }).join('');

        const editButtons = Utils.$$('[data-action="edit-user"]');
        editButtons.forEach(btn => {
            Utils.on(btn, 'click', handleEditUser);
        });
        const historyButtons = Utils.$$('[data-action="history-user"]');
        historyButtons.forEach(btn => {
            Utils.on(btn, 'click', handleHistoryUser);
        });
    }

    /**
     * Handle history user
     */
    function handleHistoryUser(e) {
        const btn = e.currentTarget;
        const userId = btn.dataset.userId;
        const userName = btn.dataset.userName || userId;
        if (!userId) return;
        showHistoryModal(userId, userName);
    }

    /**
     * Handle edit user
     */
    function handleEditUser(e) {
        const btn = e.currentTarget || (e.target && e.target.closest ? e.target.closest('[data-action="edit-user"]') : null);
        const userId = btn && btn.dataset ? btn.dataset.userId : '';
        if (!userId) return;

        // Load user details and show modal
        Utils.ajax(buildApiUrl('/apps/arbeitszeitcheck/api/admin/users/' + encodeURIComponent(userId)), {
            method: 'GET',
            onSuccess: function(data) {
                if (data.success && data.user) {
                    showEditUserModal(data.user);
                } else {
                    const errorMsg = auMsg('failedToLoadUserDetails', 'Failed to load user details');
                    Messaging.showError(errorMsg);
                }
            },
            onError: function(_error) {
                Messaging.showError(auMsg('failedToLoadUserDetails', 'Failed to load user details'));
            }
        });
    }

    /**
     * Show edit user modal
     */
    function showEditUserModal(user) {
        if (!user || !user.userId) {
            const errorMsg = auMsg('invalidUserData', 'Invalid user data');
            Messaging.showError(errorMsg);
            return;
        }
        const models = Array.isArray(user.availableWorkingTimeModels) ? user.availableWorkingTimeModels : [];
        fetchTariffRuleSets().then((ruleSets) => {
            showEditUserModalWithModels(user, models, ruleSets);
        });
    }

    /**
     * Show history modal for a user
     */
    function showHistoryModal(userId, userName) {
        const t = (key, english) => auMsg(key, english);
        const title = t('assignmentHistory', 'Assignment history') + ': ' + (userName || userId);
        const closeLabel = t('close', 'Close');
        const loadingText = auMsg('loadingEllipsis', 'Loading…');

        const content = `
            <p class="history-modal__loading" id="history-modal-loading">${loadingText}</p>
            <div id="history-modal-content" class="history-modal__content" style="display:none;"></div>
        `;

        const modal = Components.createModal({
            id: 'history-modal',
            title: title,
            content: content,
            size: 'md',
            closable: true,
            onClose: function() {
                const el = document.getElementById('history-modal');
                if (el && el.parentNode) el.parentNode.remove();
            }
        });

        Components.openModal('history-modal');

        const closeBtn = modal.querySelector('[data-action="close-modal"]');
        if (!closeBtn && modal.querySelector('.modal-close')) {
            modal.querySelector('.modal-close').setAttribute('aria-label', closeLabel);
        }

        Utils.ajax(buildApiUrl('/apps/arbeitszeitcheck/api/admin/users/' + encodeURIComponent(userId) + '/working-time-model/history'), {
            method: 'GET',
            onSuccess: function(data) {
                const loadingEl = document.getElementById('history-modal-loading');
                const contentEl = document.getElementById('history-modal-content');
                if (!loadingEl || !contentEl) return;
                loadingEl.style.display = 'none';
                if (data.success && Array.isArray(data.history) && data.history.length > 0) {
                    const formatDate = (iso) => {
                        if (!iso) return '–';
                        const m = String(iso).match(/^(\d{4})-(\d{2})-(\d{2})$/);
                        return m ? m[3] + '.' + m[2] + '.' + m[1] : iso;
                    };
                    const workScheduleHdr = Utils.escapeHtml(t('workSchedule', 'Work schedule'));
                    const vacationDaysHdr = Utils.escapeHtml(t('vacationDaysCol', 'Vacation days'));
                    const validFromHdr = Utils.escapeHtml(t('validFrom', 'Valid from'));
                    const validToHdr = Utils.escapeHtml(t('validTo', 'Valid to'));
                    const statusHdr = Utils.escapeHtml(t('status', 'Status'));
                    const ongoingVal = Utils.escapeHtml(t('ongoing', 'ongoing'));
                    const activeVal = Utils.escapeHtml(t('active', 'Active'));
                    const endedVal = Utils.escapeHtml(t('ended', 'Ended'));
                    const rows = data.history.map(item => {
                        const model = Utils.escapeHtml(item.modelName);
                        const vacation = String(item.vacationDaysPerYear);
                        const from = formatDate(item.startDate);
                        const to = formatDate(item.endDate) || ongoingVal;
                        const status = item.isActive
                            ? '<span class="badge badge--success">' + activeVal + '</span>'
                            : '<span class="badge badge--secondary">' + endedVal + '</span>';
                        const td = (label, html) => Utils.responsiveTd
                            ? Utils.responsiveTd(label, html)
                            : '<td>' + html + '</td>';
                        return '<tr>'
                            + td(workScheduleHdr, model)
                            + td(vacationDaysHdr, vacation)
                            + td(validFromHdr, from)
                            + td(validToHdr, to)
                            + td(statusHdr, status)
                            + '</tr>';
                    }).join('');
                    contentEl.innerHTML = '<div class="table-container" role="region" aria-label="' + Utils.escapeHtml(t('assignmentHistory', 'Assignment history')) + '">' +
                        '<table class="table table--hover azc-table--responsive history-modal__table" role="table" aria-label="' + Utils.escapeHtml(t('assignmentHistory', 'Assignment history')) + '">' +
                        '<thead><tr>' +
                        '<th scope="col">' + workScheduleHdr + '</th>' +
                        '<th scope="col">' + vacationDaysHdr + '</th>' +
                        '<th scope="col">' + validFromHdr + '</th>' +
                        '<th scope="col">' + validToHdr + '</th>' +
                        '<th scope="col">' + statusHdr + '</th>' +
                        '</tr></thead><tbody>' + rows + '</tbody></table></div>';
                } else {
                    contentEl.innerHTML = '<p class="history-modal__empty">' + Utils.escapeHtml(t('noAssignmentHistory', 'No assignment history')) + '</p>';
                }
                contentEl.style.display = 'block';
            },
            onError: function() {
                const loadingEl = document.getElementById('history-modal-loading');
                const contentEl = document.getElementById('history-modal-content');
                if (!loadingEl || !contentEl) return;
                loadingEl.style.display = 'none';
                contentEl.innerHTML = '<p class="history-modal__empty">' + Utils.escapeHtml(auMsg('errorLoadingHistory', 'Error loading assignment history')) + '</p>';
                contentEl.style.display = 'block';
            }
        });
    }

    /**
     * Show edit user modal with working time models loaded
     */
    function showEditUserModalWithModels(user, models, ruleSets) {
        const t = (key, english) => auMsg(key, english);
        const title = t('editUser', 'Edit User') + ': ' + (user.displayName || user.userId);
        const saveLabel = t('save', 'Save');
        const cancelLabel = t('cancel', 'Cancel');
        const modelLabel = t('workingTimeModel', 'Working Time Model');
        const vacationDaysLabel = t('vacationDaysPerYear', 'Vacation Days Per Year');
        const policyModeLabel = t('vacationModeSimpleLabel', 'How should annual vacation be calculated?');
        const carryoverLabel = t('vacationCarryoverLabel', 'Vacation carryover (opening balance)');
        const carryoverYearLabel = t('vacationCarryoverYearLabel', 'Year for carryover balance');
        const startDateLabel = t('startDate', 'Start Date');
        const endDateLabel = t('endDateOptional', 'End Date (Optional)');
        const noModelLabel = t('noModel', 'No Model Assigned');
        const germanStateLabel = t('germanStateLabel', 'Federal state for holidays / calendar');
        const germanStateHelp = t('germanStateHelp', 'Select the federal state whose holiday calendar applies to this person. If not set, the global default state is used.');
        const germanStateDefault = t('germanStateDefault', 'Use global default state');
        const datePlaceholder = Utils.escapeHtml(t('ddmmYYYY', 'dd.mm.yyyy'));

        const DEFAULT_VACATION_DAYS = 25; // German standard; must match Constants::DEFAULT_VACATION_DAYS_PER_YEAR
        const vacation = user.vacationDaysPerYear ?? user.userWorkingTimeModel?.vacationDaysPerYear ?? DEFAULT_VACATION_DAYS;
        const carryover = user.vacationCarryoverDays != null ? String(user.vacationCarryoverDays) : '0';
        const carryYear = user.vacationCarryoverYear != null ? String(user.vacationCarryoverYear) : String(new Date().getFullYear());
        const overtimeTrackingFrom = user.overtimeTrackingFrom || '';
        const overtimeTrackingVal = (overtimeTrackingFrom && convertISOToEuropean(overtimeTrackingFrom)) || '';
        const overtimeOpening = user.overtimeOpeningBalanceHours != null ? String(user.overtimeOpeningBalanceHours) : '0';
        const overtimeOpeningYear = user.overtimeOpeningBalanceYear != null ? String(user.overtimeOpeningBalanceYear) : String(new Date().getFullYear());
        const startIso = user.workingTimeModelStartDate ?? user.userWorkingTimeModel?.startDate ?? null;
        const endIso = user.workingTimeModelEndDate ?? user.userWorkingTimeModel?.endDate ?? null;
        const startVal = (startIso && convertISOToEuropean(startIso)) || '';
        const endVal = (endIso && convertISOToEuropean(endIso)) || '';
        const employmentStartIso = user.employmentStart ?? null;
        const employmentEndIso = user.employmentEnd ?? null;
        const employmentStartVal = (employmentStartIso && convertISOToEuropean(employmentStartIso)) || '';
        const employmentEndVal = (employmentEndIso && convertISOToEuropean(employmentEndIso)) || '';
        const currentState = user.germanState || '';
        const policy = user.vacationPolicy || {};
        const inheritLowerLayers = !!policy.inheritLowerLayers;
        // A user without an explicit L3 policy already resolves entitlement by
        // falling through to the lower layers (team → model → organisation →
        // legacy default). Reflect that reality by defaulting the dropdown to
        // "inherit" instead of "manual_fixed": the latter would require a
        // manual-days value the operator never entered and would make a
        // no-op "open and save" fail server-side validation (the historic
        // "Benutzer konnte nicht aktualisiert werden" bug).
        const hasExplicitPolicy = !!(user.vacationPolicy && user.vacationPolicy.vacationMode);
        const rawMode = hasExplicitPolicy ? (policy.vacationMode || 'manual_fixed') : 'inherit';
        // REQ-WF-04 — surface the "inherit" sentinel as a first-class option in
        // the dropdown so HR can flip an individual into "follow team/model/org"
        // without having to know that empty fields + manual_fixed magically mean
        // anything. The controller already accepts either representation
        // (sentinel mode or the boolean column) and persists both in sync.
        const currentMode = (inheritLowerLayers || rawMode === 'inherit') ? 'inherit' : rawMode;
        const manualDays = policy.manualDays != null ? String(policy.manualDays) : '';
        const ruleSetId = policy.tariffRuleSetId != null ? String(policy.tariffRuleSetId) : '';
        const overrideReason = policy.overrideReason || '';
        const entitlementPreview = user.entitlementPreview || null;
        const timeCapture = user.timeCapture || {};
        const orgCapture = getOrganizationTimeCapture(user);
        const preferences = timeCapture.preferences || timeCapture;
        const clockStampingEnabled = preferences.clockStampingEnabled !== false;
        const manualTimeEntryEnabled = preferences.manualTimeEntryEnabled !== false;
        const orgClockDisabled = !orgCapture.clockStampingEnabled;
        const orgManualDisabled = !orgCapture.manualTimeEntryEnabled;

        let modelOptions = `<option value="">${noModelLabel}</option>`;
        models.forEach(model => {
            const selected = user.workingTimeModel && user.workingTimeModel.id === model.id ? 'selected' : '';
            modelOptions += `<option value="${model.id}" ${selected}>${Utils.escapeHtml(model.name)}</option>`;
        });

        const states = (window.ArbeitszeitCheck && window.ArbeitszeitCheck.states) || [];
        let stateOptions = `<option value="">${Utils.escapeHtml(germanStateDefault)}</option>`;
        states.forEach(state => {
            const selected = currentState === state.code ? 'selected' : '';
            stateOptions += `<option value="${Utils.escapeHtml(state.code)}" ${selected}>${Utils.escapeHtml(state.label)}</option>`;
        });

        let tariffRuleSetOptions = `<option value="">${Utils.escapeHtml(t('notAvailable', 'Not available'))}</option>`;
        (Array.isArray(ruleSets) ? ruleSets : []).forEach(ruleSet => {
            const id = String(ruleSet.id || '');
            if (!id || !Utils.isAssignableTariffRuleSet(ruleSet, { keepId: ruleSetId })) {
                return;
            }
            const selected = ruleSetId === id ? 'selected' : '';
            const st = ruleSet.statusLabel || ruleSet.status || '';
            const status = st ? ` (${String(st)})` : '';
            const label = String(ruleSet.displayName || `${ruleSet.tariffCode || ''} ${ruleSet.version || ''}`) + status;
            tariffRuleSetOptions += `<option value="${Utils.escapeHtml(id)}" ${selected}>${Utils.escapeHtml(label)}</option>`;
        });

        const policyId = policy.id != null ? String(policy.id) : '';
        const policyEffectiveFromIso = policy.effectiveFrom || '';

        const formContent = `
            <form id="edit-user-form" class="form">
                <input type="hidden" id="user-id" name="userId" value="${Utils.escapeHtml(user.userId)}">
                <input type="hidden" id="user-vacation-policy-id" name="vacationPolicyId" value="${Utils.escapeHtml(policyId)}">
                <input type="hidden" id="user-loaded-wtm-start" name="loadedWtmStart" value="${Utils.escapeHtml(startIso || '')}">
                <input type="hidden" id="user-policy-effective-from" name="policyEffectiveFrom" value="${Utils.escapeHtml(policyEffectiveFromIso)}">
                <div class="user-edit-steps" role="note">
                    <p class="user-edit-steps__title">${Utils.escapeHtml(t('quickSetupTitle', 'Quick setup in 3 steps'))}</p>
                    <ol class="user-edit-steps__list">
                        <li>${Utils.escapeHtml(t('quickSetupStepWorkSchedule', 'Choose work schedule and state for holidays'))}</li>
                        <li>${Utils.escapeHtml(t('quickSetupStepMode', 'Choose vacation calculation mode'))}</li>
                        <li>${Utils.escapeHtml(t('quickSetupStepPreview', 'Check preview, then save'))}</li>
                    </ol>
                </div>
                <section class="user-edit-section" aria-labelledby="user-edit-assignment-heading">
                    <h3 id="user-edit-assignment-heading" class="user-edit-section__heading">${Utils.escapeHtml(t('workSchedule', 'Work schedule'))}</h3>
                <div class="form-group">
                    <label for="user-model" class="form-label">${modelLabel}</label>
                    <select id="user-model" name="workingTimeModelId" class="form-select" aria-describedby="user-model-help">
                        ${modelOptions}
                    </select>
                    <p id="user-model-help" class="form-help">${t('selectWorkScheduleHelp', 'Select a work schedule to assign to this employee')}</p>
                </div>
                <div class="form-group">
                    <label for="user-german-state" class="form-label">${germanStateLabel}</label>
                    <select id="user-german-state" name="germanState" class="form-select" aria-describedby="user-german-state-help">
                        ${stateOptions}
                    </select>
                    <p id="user-german-state-help" class="form-help">${germanStateHelp}</p>
                </div>
                </section>
                <section class="user-edit-section user-edit-section--capture" aria-labelledby="user-edit-capture-heading">
                    <h3 id="user-edit-capture-heading" class="user-edit-section__heading">${Utils.escapeHtml(t('timeRecordingMethods', 'Time recording'))}</h3>
                    <p id="user-edit-capture-intro" class="form-help user-edit-capture__intro">${Utils.escapeHtml(t('timeRecordingMethodsIntro', 'Choose how this employee may record working time. At least one method must stay enabled.'))}</p>
                    ${(orgClockDisabled || orgManualDisabled) ? `<p id="user-edit-capture-org-note" class="form-help form-help--note user-edit-capture__org-note">${Utils.escapeHtml(t('timeRecordingOrgRestrictionNote', 'Greyed-out options are disabled organisation-wide in Global settings. You can only restrict this person further.'))}</p>` : ''}
                    <div class="user-edit-capture__grid" role="group" aria-labelledby="user-edit-capture-heading" aria-describedby="user-edit-capture-intro user-edit-capture-error${orgClockDisabled || orgManualDisabled ? ' user-edit-capture-org-note' : ''}">
                        <label class="user-edit-capture__card${orgClockDisabled ? ' user-edit-capture__card--locked' : ''}">
                            <input type="checkbox" id="user-clock-stamping" name="clockStampingEnabled" value="1" class="user-edit-capture__checkbox"${clockStampingEnabled ? ' checked' : ''}${orgClockDisabled ? ' disabled aria-disabled="true"' : ''} data-user-preference="${clockStampingEnabled ? '1' : '0'}">
                            <span class="user-edit-capture__card-body">
                                <span class="user-edit-capture__card-title">${Utils.escapeHtml(t('clockStampingLabel', 'Clock in / out (stamping)'))}</span>
                                <span class="user-edit-capture__card-text">${Utils.escapeHtml(t('clockStampingHelp', 'Live punch clock on the dashboard and in the mobile app.'))}</span>
                            </span>
                        </label>
                        <label class="user-edit-capture__card${orgManualDisabled ? ' user-edit-capture__card--locked' : ''}">
                            <input type="checkbox" id="user-manual-entry" name="manualTimeEntryEnabled" value="1" class="user-edit-capture__checkbox"${manualTimeEntryEnabled ? ' checked' : ''}${orgManualDisabled ? ' disabled aria-disabled="true"' : ''} data-user-preference="${manualTimeEntryEnabled ? '1' : '0'}">
                            <span class="user-edit-capture__card-body">
                                <span class="user-edit-capture__card-title">${Utils.escapeHtml(t('manualTimeEntryLabel', 'Manual time entries'))}</span>
                                <span class="user-edit-capture__card-text">${Utils.escapeHtml(t('manualTimeEntryHelp', 'Add completed work blocks by date and time in the web app.'))}</span>
                            </span>
                        </label>
                    </div>
                    <p id="user-edit-capture-error" class="form-error user-edit-capture__error" role="alert" hidden></p>
                </section>
                <section class="user-edit-section" aria-labelledby="user-edit-vacation-heading">
                    <h3 id="user-edit-vacation-heading" class="user-edit-section__heading">${Utils.escapeHtml(t('vacationDays', 'Vacation days'))}</h3>
                <div class="form-group">
                    <label for="user-vacation-days" class="form-label">${vacationDaysLabel}</label>
                    <input type="number" id="user-vacation-days" name="vacationDaysPerYear" class="form-input" min="0" max="365" value="${vacation}" aria-describedby="user-vacation-help">
                    <p id="user-vacation-help" class="form-help">${t('vacationDaysHelp', 'Number of vacation days per year (standard in Germany: 25 days)')}</p>
                </div>
                <div class="form-group">
                    <label for="user-vacation-mode" class="form-label">${policyModeLabel}</label>
                    <select id="user-vacation-mode" name="vacationMode" class="form-select" aria-describedby="user-vacation-mode-help">
                        <option value="inherit" ${currentMode === 'inherit' ? 'selected' : ''}>${t('vacationModeInherit', 'Inherit from team / model / organisation')}</option>
                        <option value="manual_fixed" ${currentMode === 'manual_fixed' ? 'selected' : ''}>${t('manualFixedSimple', 'Fixed value per person')}</option>
                        <option value="model_based_simple" ${currentMode === 'model_based_simple' ? 'selected' : ''}>${t('modelBasedSimple', 'Automatic from work schedule')}</option>
                        <option value="tariff_rule_based" ${currentMode === 'tariff_rule_based' ? 'selected' : ''}>${t('tariffRuleBased', 'Tariff rule')}</option>
                        <option value="manual_exception" ${currentMode === 'manual_exception' ? 'selected' : ''}>${t('manualExceptionSimple', 'Manual exception (with reason)')}</option>
                    </select>
                    <p id="user-vacation-mode-help" class="form-help">${t('vacationModeHelpSimpleInherit', 'Inherit follows the deepest team policy, then the work-schedule default, then the organisation default. Fixed/automatic/tariff/exception set an individual rule for this employee.')}</p>
                </div>
                <div class="form-group">
                    <label for="user-manual-days" class="form-label">${t('manualDays', 'Manual annual days')}</label>
                    <input type="text" id="user-manual-days" name="manualDays" class="form-input" inputmode="decimal" pattern="^[0-9]+([\\.,][0-9]{1,2})?$" value="${Utils.escapeHtml(manualDays)}">
                    <p class="form-help">${t('manualDaysHelp', 'Example: 30 or 24.5 days per year')}</p>
                </div>
                <div class="form-group">
                    <label for="user-tariff-rule-set-id" class="form-label">${t('tariffRuleSetLabel', 'Tariff rule set')}</label>
                    <select id="user-tariff-rule-set-id" name="tariffRuleSetId" class="form-select">
                        ${tariffRuleSetOptions}
                    </select>
                    <p class="form-help">${t('tariffRuleSetHelp', 'Choose the active tariff rule set that should apply to this person.')}</p>
                </div>
                <div class="form-group">
                    <label for="user-override-reason" class="form-label">${t('overrideReason', 'Override reason')}</label>
                    <textarea id="user-override-reason" name="overrideReason" class="form-textarea" rows="2">${Utils.escapeHtml(overrideReason)}</textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">${t('effectiveEntitlement', 'Effective entitlement preview')}</label>
                    <div id="user-entitlement-preview" class="entitlement-preview" aria-live="polite">
                        ${buildEntitlementPreviewHtml(entitlementPreview, t)}
                    </div>
                </div>
                <div class="form-group">
                    <label for="user-vacation-carryover" class="form-label">${carryoverLabel}</label>
                    <input type="text" id="user-vacation-carryover" name="vacationCarryoverDays" class="form-input"
                           inputmode="decimal" pattern="^[0-9]+([\\.,][0-9]{1,2})?$" autocomplete="off"
                           value="${Utils.escapeHtml(carryover)}" aria-describedby="user-carryover-help">
                    <p id="user-carryover-help" class="form-help">${t('vacationCarryoverHelp', 'Opening balance of carryover days for the selected calendar year (Resturlaub), e.g. from HR or migration. This is not the annual vacation entitlement from the working time model. The last day carryover can be used is set globally in Admin settings.')} ${t('vacationCarryoverHelpDecimals', 'Up to two decimal places are allowed, e.g. 1.5 or 4.25 — comma or dot both work.')}</p>
                </div>
                <div class="form-group">
                    <label for="user-vacation-carryover-year" class="form-label">${carryoverYearLabel}</label>
                    <input type="text" id="user-vacation-carryover-year" name="vacationCarryoverYear" class="form-input" inputmode="numeric" pattern="\\d{4}" maxlength="4" autocomplete="off" value="${carryYear}" aria-describedby="user-carryover-year-help">
                    <p id="user-carryover-year-help" class="form-help">${t('vacationCarryoverYearHelp', 'The calendar year this opening balance applies to (same year as in employees’ vacation statistics—usually the current year). When a new year starts or after migrating from another system, set the Resturlaub opening balance for that year here or use the CSV import command; the app does not roll balances forward automatically.')}</p>
                </div>
                </section>
                <section class="user-edit-section" aria-labelledby="user-edit-overtime-heading">
                    <h3 id="user-edit-overtime-heading" class="user-edit-section__heading">${Utils.escapeHtml(t('overtimeSettings', 'Overtime balance'))}</h3>
                <div class="form-group">
                    <label for="user-overtime-tracking-from" class="form-label">${Utils.escapeHtml(t('overtimeTrackingFrom', 'Overtime tracking from (Stichtag)'))}</label>
                    <input type="text" id="user-overtime-tracking-from" name="overtimeTrackingFrom" class="form-input datepicker-input" placeholder="${datePlaceholder}" pattern="\\d{2}\\.\\d{2}\\.\\d{4}" maxlength="10" value="${Utils.escapeHtml(overtimeTrackingVal)}" autocomplete="off" aria-describedby="user-overtime-tracking-from-help">
                    <p id="user-overtime-tracking-from-help" class="form-help">${Utils.escapeHtml(t('overtimeTrackingFromHelp', 'Leave empty for legacy calculation from 1 January. When set, year-to-date overtime counts only from this date.'))} ${Utils.escapeHtml(t('formatDdmmyyyy', 'Format: dd.mm.yyyy'))}</p>
                </div>
                <div class="form-group">
                    <label for="user-overtime-opening" class="form-label">${Utils.escapeHtml(t('overtimeOpeningBalance', 'Opening overtime balance (hours)'))}</label>
                    <input type="text" id="user-overtime-opening" name="overtimeOpeningBalanceHours" class="form-input" inputmode="decimal" value="${Utils.escapeHtml(overtimeOpening)}" aria-describedby="user-overtime-opening-help">
                    <p id="user-overtime-opening-help" class="form-help">${Utils.escapeHtml(t('overtimeOpeningBalanceHelp', 'Eröffnungssaldo in hours for the selected year (can be negative).'))}</p>
                </div>
                <div class="form-group">
                    <label for="user-overtime-opening-year" class="form-label">${Utils.escapeHtml(t('overtimeOpeningBalanceYear', 'Year for opening balance'))}</label>
                    <input type="text" id="user-overtime-opening-year" name="overtimeOpeningBalanceYear" class="form-input" inputmode="numeric" pattern="\\d{4}" maxlength="4" autocomplete="off" value="${Utils.escapeHtml(overtimeOpeningYear)}" aria-describedby="user-overtime-opening-year-help">
                    <p id="user-overtime-opening-year-help" class="form-help">${Utils.escapeHtml(t('yearFourDigitsHelp', 'Enter a four-digit year (e.g. 2026).'))}</p>
                </div>
                </section>
                <section class="user-edit-section" aria-labelledby="user-edit-validity-heading">
                    <h3 id="user-edit-validity-heading" class="user-edit-section__heading">${Utils.escapeHtml(t('validFrom', 'Valid from'))}</h3>
                <div class="form-group">
                    <label for="user-start-date" class="form-label">${startDateLabel}</label>
                    <input type="text" id="user-start-date" name="startDate" class="form-input datepicker-input" placeholder="${datePlaceholder}" pattern="\\d{2}\\.\\d{2}\\.\\d{4}" maxlength="10" value="${startVal}" autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="user-end-date" class="form-label">${endDateLabel}</label>
                    <input type="text" id="user-end-date" name="endDate" class="form-input datepicker-input" placeholder="${datePlaceholder}" pattern="\\d{2}\\.\\d{2}\\.\\d{4}" maxlength="10" value="${endVal}" autocomplete="off">
                    <p class="form-help">${t('endDateHelp', 'Leave empty if the assignment has no end date')}</p>
                </div>
                </section>
                <section class="user-edit-section" aria-labelledby="user-edit-employment-heading">
                    <h3 id="user-edit-employment-heading" class="user-edit-section__heading">${Utils.escapeHtml(t('employmentPeriod', 'Employment period (for pro-rata vacation)'))}</h3>
                    <p id="user-edit-employment-intro" class="form-help form-help--block">${Utils.escapeHtml(t('employmentPeriodIntro', 'Set the hire date (and leaving date, if any). When the employment does not cover the whole calendar year, the annual vacation entitlement is reduced proportionally. Leave both empty for the full annual entitlement.'))}</p>
                <div class="form-group">
                    <label for="user-employment-start" class="form-label">${Utils.escapeHtml(t('employmentStart', 'Employment start date (Eintrittsdatum)'))}</label>
                    <input type="text" id="user-employment-start" name="employmentStart" class="form-input datepicker-input" placeholder="${datePlaceholder}" pattern="\\d{2}\\.\\d{2}\\.\\d{4}" maxlength="10" value="${employmentStartVal}" autocomplete="off" aria-describedby="user-employment-start-help">
                    <p id="user-employment-start-help" class="form-help">${Utils.escapeHtml(t('employmentStartHelp', 'First day of employment. Vacation for the year of hire is prorated from this date.'))} ${Utils.escapeHtml(t('formatDdmmyyyy', 'Format: dd.mm.yyyy'))}</p>
                </div>
                <div class="form-group">
                    <label for="user-employment-end" class="form-label">${Utils.escapeHtml(t('employmentEnd', 'Employment end date (Austrittsdatum)'))}</label>
                    <input type="text" id="user-employment-end" name="employmentEnd" class="form-input datepicker-input" placeholder="${datePlaceholder}" pattern="\\d{2}\\.\\d{2}\\.\\d{4}" maxlength="10" value="${employmentEndVal}" autocomplete="off" aria-describedby="user-employment-end-help">
                    <p id="user-employment-end-help" class="form-help">${Utils.escapeHtml(t('employmentEndHelp', 'Last day of employment. Leave empty for ongoing employment. Vacation for the year of leaving is prorated up to this date.'))}</p>
                </div>
                </section>
                <div class="form-actions">
                    <button type="button" class="btn btn--secondary" data-action="close-modal">${cancelLabel}</button>
                    <button type="submit" class="btn btn--primary">${saveLabel}</button>
                </div>
            </form>
        `;

        const modal = Components.createModal({
            id: 'edit-user-modal',
            title: title,
            content: formContent,
            size: 'md',
            closable: true,
            onClose: function() {
                const el = document.getElementById('edit-user-modal');
                if (el && el.parentNode) el.parentNode.remove();
            }
        });

        Components.openModal('edit-user-modal');

        const dp = window.ArbeitszeitCheckDatepicker;
        if (dp && dp.initializeDatepicker) {
            ['user-start-date', 'user-end-date', 'user-overtime-tracking-from', 'user-employment-start', 'user-employment-end'].forEach((id) => {
                const el = document.getElementById(id);
                if (el) {
                    dp.initializeDatepicker(el, {});
                }
            });
        }

        const form = document.getElementById('edit-user-form');
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                handleUpdateUser(form, user.userId);
            });
        }

        const vacationModeEl = document.getElementById('user-vacation-mode');
        const manualDaysEl = document.getElementById('user-manual-days');
        const tariffRuleSetEl = document.getElementById('user-tariff-rule-set-id');
        const overrideReasonEl = document.getElementById('user-override-reason');
        const modelEl = document.getElementById('user-model');
        const previewEl = document.getElementById('user-entitlement-preview');
        const startDateEl = document.getElementById('user-start-date');
        const employmentStartEl = document.getElementById('user-employment-start');
        const employmentEndEl = document.getElementById('user-employment-end');
        let previewTimer = null;
        const previewToISO = resolveToISO();

        const readEmploymentDraftFromForm = function() {
            const startRaw = String(employmentStartEl?.value || '').trim();
            const endRaw = String(employmentEndEl?.value || '').trim();
            return {
                start: startRaw ? (previewToISO(startRaw) || '') : '',
                end: endRaw ? (previewToISO(endRaw) || '') : '',
            };
        };

        const renderEntitlementPreview = function(days, sourceLabel, summaryOrTrace, traceObject, prorationPreview) {
            const summaryText = typeof summaryOrTrace === 'string' ? summaryOrTrace : '';
            const trace = traceObject != null
                ? traceObject
                : (typeof summaryOrTrace === 'object' ? summaryOrTrace : null);
            paintEntitlementPreview(previewEl, days, sourceLabel, summaryText, trace, t, prorationPreview || null);
        };

        const computeLocalPreview = function() {
            const mode = String(vacationModeEl?.value || 'manual_fixed');
            if (mode === 'tariff_rule_based' && !(tariffRuleSetEl?.value)) {
                renderEntitlementPreview(
                    0,
                    t('sourceTariff', 'Tariff'),
                    t('previewSelectTariffRuleSet', 'Select a tariff rule set to see the preview.'),
                    null,
                    null
                );
                return;
            }

            const payload = {
                userId: user.userId,
                asOfDate: (startDateEl?.value && previewToISO(startDateEl.value))
                    || (window.ArbeitszeitCheckTime ? window.ArbeitszeitCheckTime.todayYmd() : new Date().toISOString().slice(0, 10)),
                employment: readEmploymentDraftFromForm(),
                draftPolicy: {
                    vacationMode: mode,
                    inheritLowerLayers: mode === 'inherit',
                    manualDays: mode === 'inherit' ? null : parseLocalizedDecimal(manualDaysEl?.value),
                    tariffRuleSetId: mode === 'inherit' ? null : (tariffRuleSetEl?.value ? parseInt(String(tariffRuleSetEl.value), 10) : null),
                    overrideReason: (overrideReasonEl?.value || '').toString()
                }
            };

            Utils.ajax(buildApiUrl('/apps/arbeitszeitcheck/api/admin/vacation-policy/simulate'), {
                method: 'POST',
                data: payload,
                onSuccess: function(resp) {
                    if (!resp || !resp.success) {
                        renderEntitlementPreview(
                            0,
                            t('notAvailable', 'Not available'),
                            resp?.error || t('previewTraceError', 'Preview unavailable.'),
                            null,
                            null
                        );
                        return;
                    }
                    const src = localizedEntitlementSourceLabel(resp.source, t);
                    const trace = resp.calculationTrace || null;
                    renderEntitlementPreview(
                        previewDaysFromResponse(resp),
                        src,
                        buildEntitlementPreviewSummary(trace, t),
                        trace,
                        buildProrationPreviewFromResponse(resp)
                    );
                },
                onError: function() {
                    renderEntitlementPreview(
                        0,
                        t('notAvailable', 'Not available'),
                        t('previewTraceError', 'Preview unavailable.'),
                        null,
                        null
                    );
                }
            });
        };

        const triggerPreview = function() {
            if (previewTimer) {
                clearTimeout(previewTimer);
            }
            previewTimer = setTimeout(computeLocalPreview, 220);
        };

        const toggleVacationModeFields = function() {
            const mode = String(vacationModeEl?.value || 'manual_fixed');
            const isInherit = mode === 'inherit';
            const isManual = !isInherit && (mode === 'manual_fixed' || mode === 'manual_exception');
            const isTariff = !isInherit && mode === 'tariff_rule_based';
            if (manualDaysEl) {
                manualDaysEl.disabled = !isManual;
                manualDaysEl.closest('.form-group')?.classList.toggle('is-disabled', !isManual);
            }
            if (tariffRuleSetEl) {
                tariffRuleSetEl.disabled = !isTariff;
                tariffRuleSetEl.closest('.form-group')?.classList.toggle('is-disabled', !isTariff);
            }
            if (overrideReasonEl) {
                const needsReason = !isInherit && mode === 'manual_exception';
                overrideReasonEl.disabled = !needsReason;
                overrideReasonEl.required = needsReason;
                overrideReasonEl.closest('.form-group')?.classList.toggle('is-disabled', !needsReason);
            }
            triggerPreview();
        };
        if (vacationModeEl) {
            vacationModeEl.addEventListener('change', toggleVacationModeFields);
            toggleVacationModeFields();
        }
        [manualDaysEl, tariffRuleSetEl, overrideReasonEl, modelEl, startDateEl, employmentStartEl, employmentEndEl].forEach((el) => {
            if (!el) {
                return;
            }
            el.addEventListener('input', triggerPreview);
            el.addEventListener('change', triggerPreview);
        });
        triggerPreview();

        bindTimeCaptureValidation(form, orgCapture);

        const cancelBtn = modal.querySelector('[data-action="close-modal"]');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function() { Components.closeModal(modal); });
        }
    }

    function getOrganizationTimeCapture(user) {
        const org = (user && user.organizationTimeCapture)
            || (window.ArbeitszeitCheck && window.ArbeitszeitCheck.adminUsersConfig
                && window.ArbeitszeitCheck.adminUsersConfig.organizationTimeCapture)
            || { clockStampingEnabled: true, manualTimeEntryEnabled: true };
        return {
            clockStampingEnabled: org.clockStampingEnabled !== false,
            manualTimeEntryEnabled: org.manualTimeEntryEnabled !== false,
        };
    }

    function bindTimeCaptureValidation(form, orgCapture) {
        const org = orgCapture || getOrganizationTimeCapture(null);
        const clockEl = form.querySelector('#user-clock-stamping');
        const manualEl = form.querySelector('#user-manual-entry');
        const errorEl = form.querySelector('#user-edit-capture-error');
        if (!clockEl || !manualEl || !errorEl) {
            return;
        }
        const validate = () => {
            const clockEffective = org.clockStampingEnabled && clockEl.checked;
            const manualEffective = org.manualTimeEntryEnabled && manualEl.checked;
            const ok = clockEffective || manualEffective;
            errorEl.hidden = ok;
            if (!ok) {
                errorEl.textContent = auMsg(
                    'timeCaptureAtLeastOne',
                    'Enable clock in/out or manual time entries — at least one method is required.'
                );
            }
            return ok;
        };
        clockEl.addEventListener('change', validate);
        manualEl.addEventListener('change', validate);
        form.addEventListener('submit', (event) => {
            if (!validate()) {
                event.preventDefault();
                errorEl.focus();
            }
        });
    }

    function readTimeCapturePayload(form) {
        const clockEl = form.querySelector('#user-clock-stamping');
        const manualEl = form.querySelector('#user-manual-entry');
        const readPreference = (el) => {
            if (!el) {
                return false;
            }
            if (el.disabled) {
                return el.getAttribute('data-user-preference') === '1';
            }
            el.setAttribute('data-user-preference', el.checked ? '1' : '0');
            return el.checked;
        };
        return {
            clockStampingEnabled: readPreference(clockEl),
            manualTimeEntryEnabled: readPreference(manualEl),
        };
    }

    /**
     * Remove every inline field error previously rendered by {@link setFieldError}
     * and reset the related ARIA wiring so re-validation starts from a clean slate.
     */
    function clearFieldErrors(form) {
        if (!form) {
            return;
        }
        form.querySelectorAll('.field-error[data-field-error]').forEach((el) => {
            const ownerId = el.getAttribute('data-field-error');
            const owner = ownerId ? form.querySelector('#' + escapeSelector(ownerId)) : null;
            if (owner) {
                owner.removeAttribute('aria-invalid');
                owner.classList.remove('form-input--error');
                const tokens = (owner.getAttribute('aria-describedby') || '')
                    .split(/\s+/)
                    .filter((token) => token && token !== el.id);
                if (tokens.length) {
                    owner.setAttribute('aria-describedby', tokens.join(' '));
                } else {
                    owner.removeAttribute('aria-describedby');
                }
            }
            el.remove();
        });
    }

    /** Minimal CSS.escape fallback so we can target generated ids safely. */
    function escapeSelector(value) {
        if (window.CSS && typeof window.CSS.escape === 'function') {
            return window.CSS.escape(value);
        }
        return String(value).replace(/[^a-zA-Z0-9_-]/g, '\\$&');
    }

    /**
     * Render an accessible, screen-reader-announced error directly beneath the
     * offending field and link it via aria-describedby. Returns the field so
     * callers can track the first invalid control for focus management.
     */
    function setFieldError(field, message) {
        if (!field) {
            return null;
        }
        const group = field.closest('.form-group') || field.parentNode;
        if (!group) {
            return field;
        }
        const fieldId = field.id || ('azc-field-' + Math.random().toString(36).slice(2));
        if (!field.id) {
            field.id = fieldId;
        }
        const errorId = fieldId + '-field-error';
        let errorEl = group.querySelector('.field-error[data-field-error="' + fieldId + '"]');
        if (!errorEl) {
            errorEl = document.createElement('p');
            errorEl.className = 'field-error';
            errorEl.id = errorId;
            errorEl.setAttribute('role', 'alert');
            errorEl.setAttribute('data-field-error', fieldId);
            group.appendChild(errorEl);
        }
        errorEl.textContent = message;
        field.setAttribute('aria-invalid', 'true');
        field.classList.add('form-input--error');
        const tokens = (field.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
        if (!tokens.includes(errorId)) {
            tokens.push(errorId);
            field.setAttribute('aria-describedby', tokens.join(' '));
        }
        return field;
    }

    /** Map server-side validation keys to form controls for inline feedback. */
    const SERVER_FIELD_SELECTORS = {
        manualDays: '#user-manual-days',
        tariffRuleSetId: '#user-tariff-rule-set-id',
        overrideReason: '#user-override-reason',
        vacationMode: '#user-vacation-mode',
        effectiveFrom: '#user-start-date',
        effectiveTo: '#user-end-date',
        startDate: '#user-start-date',
        endDate: '#user-end-date',
        trackingFrom: '#user-overtime-tracking-from',
        openingBalanceYear: '#user-overtime-opening-year',
        openingBalanceHours: '#user-overtime-opening',
        employmentStart: '#user-employment-start',
        employmentEnd: '#user-employment-end',
    };

    /**
     * Paint field-level errors returned by PUT …/profile ({ errors: { field: msg } }).
     *
     * @returns {HTMLElement|null} first field marked invalid
     */
    function applyServerFieldErrors(form, errors) {
        if (!form || !errors || typeof errors !== 'object') {
            return null;
        }
        let firstInvalid = null;
        Object.keys(errors).forEach((key) => {
            const selector = SERVER_FIELD_SELECTORS[key];
            const field = selector ? form.querySelector(selector) : null;
            const raw = errors[key];
            const message = Array.isArray(raw) ? String(raw[0] || '') : String(raw || '');
            if (!message) {
                return;
            }
            const marked = setFieldError(field, message);
            if (!firstInvalid && marked) {
                firstInvalid = marked;
            }
        });
        return firstInvalid;
    }

    /**
     * Validate the whole edit-user form on the client BEFORE any request is sent.
     *
     * The save uses a single atomic profile endpoint; client validation still
     * prevents round-trips and surfaces specific, localized, accessible messages.
     *
     * @returns {HTMLElement|null} the first invalid field, or null when valid.
     */
    function validateUserEditForm(form) {
        clearFieldErrors(form);
        let firstInvalid = null;
        const markInvalid = (field, message) => {
            const marked = setFieldError(field, message);
            if (!firstInvalid && marked) {
                firstInvalid = marked;
            }
        };

        // 1. Time recording: at least one method must remain enabled. Reuse the
        // dedicated capture error region so live and submit feedback stay aligned.
        const clockEl = form.querySelector('#user-clock-stamping');
        const manualEl = form.querySelector('#user-manual-entry');
        const captureErrorEl = form.querySelector('#user-edit-capture-error');
        if (clockEl && manualEl && !clockEl.checked && !manualEl.checked) {
            if (captureErrorEl) {
                captureErrorEl.hidden = false;
                captureErrorEl.textContent = auMsg('timeCaptureAtLeastOne', 'Enable clock in/out or manual time entries — at least one method is required.');
            }
            if (!firstInvalid) {
                firstInvalid = clockEl;
            }
        } else if (captureErrorEl) {
            captureErrorEl.hidden = true;
        }

        // 2. Vacation calculation mode cross-field requirements (mirror the server).
        const modeEl = form.querySelector('#user-vacation-mode');
        const mode = String(modeEl?.value || 'inherit');
        const manualDaysEl = form.querySelector('#user-manual-days');
        const tariffEl = form.querySelector('#user-tariff-rule-set-id');
        const reasonEl = form.querySelector('#user-override-reason');
        if (mode === 'manual_fixed' || mode === 'manual_exception') {
            const days = parseLocalizedDecimal(manualDaysEl?.value);
            if (days === undefined) {
                markInvalid(manualDaysEl, auMsg('manualDaysRequired', 'Enter the annual vacation days (e.g. 30 or 24.5).'));
            } else if (days < 0 || days > 366) {
                markInvalid(manualDaysEl, auMsg('manualDaysRange', 'Vacation days must be between 0 and 366.'));
            }
        }
        if (mode === 'manual_exception' && !(reasonEl?.value || '').trim()) {
            markInvalid(reasonEl, auMsg('overrideReasonRequired', 'A reason is required for a manual exception.'));
        }
        if (mode === 'tariff_rule_based' && !(tariffEl?.value || '')) {
            markInvalid(tariffEl, auMsg('tariffRuleSetRequired', 'Select a tariff rule set.'));
        }

        // 3. Legacy "vacation days per year" assignment field (0–365, integer).
        const vacationDaysEl = form.querySelector('#user-vacation-days');
        if (vacationDaysEl && String(vacationDaysEl.value || '').trim() !== '') {
            const n = Number(vacationDaysEl.value);
            if (!Number.isFinite(n) || n < 0 || n > 365) {
                markInvalid(vacationDaysEl, auMsg('vacationDaysRange', 'Vacation days per year must be between 0 and 365.'));
            }
        }

        // 4. Vacation carryover (Resturlaub) days + year.
        const carryoverEl = form.querySelector('#user-vacation-carryover');
        if (carryoverEl && String(carryoverEl.value || '').trim() !== '') {
            const carry = parseLocalizedDecimal(carryoverEl.value);
            if (carry === undefined || carry < 0 || carry > 366) {
                markInvalid(carryoverEl, auMsg('carryoverRange', 'Carryover must be a number between 0 and 366.'));
            }
        }
        const carryoverYearEl = form.querySelector('#user-vacation-carryover-year');
        if (carryoverYearEl && String(carryoverYearEl.value || '').trim() !== '') {
            if (!/^\d{4}$/.test(String(carryoverYearEl.value).trim())) {
                markInvalid(carryoverYearEl, auMsg('yearFourDigitsHelp', 'Enter a four-digit year (e.g. 2026).'));
            } else {
                const y = parseInt(carryoverYearEl.value, 10);
                if (y < 2000 || y > 2100) {
                    markInvalid(carryoverYearEl, auMsg('yearRange2000', 'Year must be between 2000 and 2100.'));
                }
            }
        }

        // 5. Overtime opening balance year (required, four digits, 2000–2100) + hours.
        const otYearEl = form.querySelector('#user-overtime-opening-year');
        const otYearRaw = String(otYearEl?.value || '').trim();
        if (!/^\d{4}$/.test(otYearRaw)) {
            markInvalid(otYearEl, auMsg('yearFourDigitsHelp', 'Enter a four-digit year (e.g. 2026).'));
        } else {
            const y = parseInt(otYearRaw, 10);
            if (y < 2000 || y > 2100) {
                markInvalid(otYearEl, auMsg('openingBalanceYearRange', 'Opening balance year must be between 2000 and 2100.'));
            }
        }
        const otHoursEl = form.querySelector('#user-overtime-opening');
        if (otHoursEl && String(otHoursEl.value || '').trim() !== '') {
            const hours = parseLocalizedDecimal(otHoursEl.value);
            if (hours === undefined || hours < -9999 || hours > 9999) {
                markInvalid(otHoursEl, auMsg('openingBalanceHoursRange', 'Opening balance hours must be a number between -9999 and 9999.'));
            }
        }

        // 6. Assignment validity window: strict ISO after dd.mm.yyyy conversion (never
        // send unconverted German dates to the API — see issue #15 / CHANGELOG 1.3.13).
        const startEl = form.querySelector('#user-start-date');
        const endEl = form.querySelector('#user-end-date');
        const toISO = resolveToISO();
        const isIso = (s) => /^\d{4}-\d{2}-\d{2}$/.test(s);
        const assertValidOptionalDate = (field, raw) => {
            const trimmed = String(raw || '').trim();
            if (!trimmed) {
                return;
            }
            if (!isIso(toISO(trimmed))) {
                markInvalid(
                    field,
                    auMsg('invalidDateDdmmyyyy', 'Please enter a valid date (dd.mm.yyyy).')
                );
            }
        };
        assertValidOptionalDate(startEl, startEl?.value);
        assertValidOptionalDate(endEl, endEl?.value);
        const trackingEl = form.querySelector('#user-overtime-tracking-from');
        assertValidOptionalDate(trackingEl, trackingEl?.value);
        const startIso = toISO(String(startEl?.value || '').trim());
        const endIso = toISO(String(endEl?.value || '').trim());
        if (isIso(startIso) && isIso(endIso) && endIso < startIso) {
            markInvalid(endEl, auMsg('endDateAfterStart', 'The end date must be on or after the start date.'));
        }

        // 7. Employment period: valid optional dates and start <= end.
        const empStartEl = form.querySelector('#user-employment-start');
        const empEndEl = form.querySelector('#user-employment-end');
        assertValidOptionalDate(empStartEl, empStartEl?.value);
        assertValidOptionalDate(empEndEl, empEndEl?.value);
        const empStartIso = toISO(String(empStartEl?.value || '').trim());
        const empEndIso = toISO(String(empEndEl?.value || '').trim());
        if (isIso(empStartIso) && isIso(empEndIso) && empEndIso < empStartIso) {
            markInvalid(empEndEl, auMsg('employmentEndAfterStart', 'The employment end date must be on or after the employment start date.'));
        }

        return firstInvalid;
    }

    /**
     * Handle update user form submission
     */
    function convertISOToEuropean(s) {
        if (!s || !/^\d{4}-\d{2}-\d{2}$/.test(s)) return s;
        const p = s.split('-');
        return p[2] + '.' + p[1] + '.' + p[0];
    }

    /**
     * Resolve a European→ISO date converter. Prefer the shared datepicker module
     * but fall back to a local implementation so the save never sends an
     * unconverted `dd.mm.yyyy` value to the strict server-side parser (the
     * historic cause of the "Benutzer konnte nicht aktualisiert werden" 400 when
     * the datepicker asset failed to load).
     */
    function resolveToISO() {
        const dp = window.ArbeitszeitCheckDatepicker;
        if (dp && typeof dp.convertEuropeanToISO === 'function') {
            return dp.convertEuropeanToISO;
        }
        return convertEuropeanToISOLocal;
    }

    function convertEuropeanToISOLocal(value) {
        const s = String(value == null ? '' : value).trim();
        if (!s) return '';
        // Already ISO — leave untouched.
        if (/^\d{4}-\d{2}-\d{2}$/.test(s)) return s;
        const m = s.match(/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/);
        if (!m) return s;
        const day = m[1].padStart(2, '0');
        const month = m[2].padStart(2, '0');
        const year = m[3];
        return year + '-' + month + '-' + day;
    }

    function todayYmd() {
        if (window.ArbeitszeitCheckTime && typeof window.ArbeitszeitCheckTime.todayYmd === 'function') {
            return window.ArbeitszeitCheckTime.todayYmd();
        }
        return new Date().toISOString().slice(0, 10);
    }

    /**
     * Build the four independent payloads from the validated form. Field-level
     * validity is assumed (see {@link validateUserEditForm}); this function only
     * shapes the data for the respective endpoints.
     */
    function buildUpdatePayloads(form) {
        const formData = new FormData(form);
        const toISO = resolveToISO();

        const workingTimeModel = {
            workingTimeModelId: formData.get('workingTimeModelId') ? parseInt(formData.get('workingTimeModelId'), 10) : null,
            vacationDaysPerYear: formData.get('vacationDaysPerYear') ? parseInt(formData.get('vacationDaysPerYear'), 10) : null,
            vacationCarryoverDays: formData.get('vacationCarryoverDays') !== null && formData.get('vacationCarryoverDays') !== ''
                ? parseLocalizedDecimal(formData.get('vacationCarryoverDays'))
                : undefined,
            vacationCarryoverYear: formData.get('vacationCarryoverYear') ? parseInt(String(formData.get('vacationCarryoverYear')), 10) : undefined,
            startDate: toISO(formData.get('startDate') || '') || null,
            endDate: toISO(formData.get('endDate') || '') || null,
            germanState: (formData.get('germanState') || '').toString()
        };

        const mode = (formData.get('vacationMode') || 'inherit').toString();
        const isInherit = mode === 'inherit';
        const isManual = mode === 'manual_fixed' || mode === 'manual_exception';
        const policyIdRaw = formData.get('vacationPolicyId');
        const policyId = policyIdRaw && String(policyIdRaw).trim() !== ''
            ? parseInt(String(policyIdRaw), 10)
            : null;
        const loadedWtmStart = String(formData.get('loadedWtmStart') || '').trim();
        const policyEffectiveFrom = String(formData.get('policyEffectiveFrom') || '').trim();
        const newStartIso = workingTimeModel.startDate || '';
        // Keep the existing policy row when the assignment start date did not change.
        // Using only the work-schedule start date would spawn duplicate policy rows on
        // every no-op save (effective_from drift vs. the row being edited).
        let vacationEffectiveFrom = newStartIso || todayYmd();
        if (policyId && policyEffectiveFrom && loadedWtmStart && newStartIso === loadedWtmStart) {
            vacationEffectiveFrom = policyEffectiveFrom;
        }
        const vacationPolicy = {
            policyId: policyId,
            vacationMode: mode,
            inheritLowerLayers: isInherit,
            manualDays: (!isInherit && isManual) ? (parseLocalizedDecimal(formData.get('manualDays')) ?? null) : null,
            tariffRuleSetId: (!isInherit && mode === 'tariff_rule_based' && formData.get('tariffRuleSetId'))
                ? parseInt(String(formData.get('tariffRuleSetId')), 10)
                : null,
            overrideReason: (!isInherit && mode === 'manual_exception') ? (formData.get('overrideReason') || '').toString() : '',
            effectiveFrom: vacationEffectiveFrom,
            effectiveTo: workingTimeModel.endDate || null
        };

        const timeCapture = readTimeCapturePayload(form);

        const trackingRaw = String(form.querySelector('#user-overtime-tracking-from')?.value || '').trim();
        const trackingIso = trackingRaw ? (toISO(trackingRaw) || null) : null;
        const overtime = {
            trackingFrom: trackingIso,
            openingBalance: {
                year: parseInt(String(form.querySelector('#user-overtime-opening-year')?.value || '').trim(), 10),
                hours: form.querySelector('#user-overtime-opening')?.value || '0'
            }
        };

        // Employment period (Eintritts-/Austrittsdatum) drives pro-rata vacation
        // entitlement in partial years. Empty strings clear the stored value.
        const employmentStartRaw = String(form.querySelector('#user-employment-start')?.value || '').trim();
        const employmentEndRaw = String(form.querySelector('#user-employment-end')?.value || '').trim();
        const employment = {
            start: employmentStartRaw ? (toISO(employmentStartRaw) || '') : '',
            end: employmentEndRaw ? (toISO(employmentEndRaw) || '') : ''
        };

        return { workingTimeModel, vacationPolicy, timeCapture, overtime, employment };
    }

    /**
     * Issue a PUT and normalise both transport-level (HTTP) and application-level
     * ({success:false}) failures into a thrown error carrying a user-facing
     * `.error` message, so the orchestration can surface a specific reason.
     */
    async function apiPut(path, data) {
        const response = await Utils.ajax(buildApiUrl(path), { method: 'PUT', data: data });
        if (!response || response.success === false) {
            const err = new Error((response && response.error) || auMsg('failedToUpdateUser', 'Failed to update user'));
            err.error = (response && response.error) || err.message;
            throw err;
        }
        return response;
    }

    async function handleUpdateUser(form, userId) {
        const firstInvalid = validateUserEditForm(form);
        if (firstInvalid) {
            if (typeof firstInvalid.focus === 'function') {
                firstInvalid.focus();
            }
            Messaging.showError(auMsg('formHasErrors', 'Please correct the highlighted fields and try again.'));
            return;
        }

        const payloads = buildUpdatePayloads(form);
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalLabel = submitBtn ? submitBtn.textContent : '';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.setAttribute('aria-busy', 'true');
            submitBtn.textContent = auMsg('saving', 'Saving…');
        }

        try {
            await apiPut('/apps/arbeitszeitcheck/api/admin/users/' + encodeURIComponent(userId) + '/profile', payloads);

            Messaging.showSuccess(auMsg('userUpdated', 'User updated successfully'));
            Components.closeModal(document.getElementById('edit-user-modal'));
            loadUsers();
        } catch (error) {
            const serverErrors = error && error.data && error.data.errors;
            const serverField = applyServerFieldErrors(form, serverErrors);
            if (serverField && typeof serverField.focus === 'function') {
                serverField.focus();
            }
            const message = (error && error.error) ? error.error : auMsg('failedToUpdateUser', 'Failed to update user');
            Messaging.showError(
                serverField
                    ? auMsg('formHasErrors', 'Please correct the highlighted fields and try again.')
                    : message
            );
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.removeAttribute('aria-busy');
                submitBtn.textContent = originalLabel;
            }
        }
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
