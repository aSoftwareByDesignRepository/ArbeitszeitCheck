/**
 * Revision-safe month closure UI (time entries page).
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

(function () {
    'use strict';

    const Utils = window.ArbeitszeitCheckUtils || {};

    function $(id) {
        return document.getElementById(id);
    }

    /**
     * Visible status line (WCAG: not screen-reader-only; complements the badge).
     */
    function setFeedback(msg, isError) {
        const el = $('month-closure-feedback');
        if (!el) {
            return;
        }
        el.textContent = msg || '';
        el.classList.toggle('month-closure-feedback--error', Boolean(isError && msg));
        el.classList.toggle('month-closure-feedback--success', Boolean(!isError && msg));
    }

    function announce(msg, isError) {
        setFeedback(msg, isError);
    }

    function setPdfLink(year, month, visible) {
        const a = $('month-closure-pdf');
        if (!a) {
            return;
        }
        if (!visible) {
            a.style.display = 'none';
            a.setAttribute('href', '#');
            return;
        }
        const path = '/apps/arbeitszeitcheck/api/month-closure/pdf?year=' + encodeURIComponent(String(year)) + '&month=' + encodeURIComponent(String(month));
        a.href = (typeof OC !== 'undefined' && OC.generateUrl) ? OC.generateUrl(path) : path;
        a.style.display = 'inline-block';
    }

    function formatYmdLocal(ymd) {
        if (!ymd || typeof ymd !== 'string') {
            return '';
        }
        const d = new Date(ymd + 'T12:00:00');
        return Number.isNaN(d.getTime()) ? ymd : d.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
    }

    function refreshStatus() {
        const y = parseInt($('month-closure-year').value, 10);
        const m = parseInt($('month-closure-month').value, 10);
        const statusEl = $('month-closure-status');
        const btn = $('month-closure-finalize');
        const deadlineEl = $('month-closure-deadline');
        const blockedEl = $('month-closure-blocked');
        statusEl.textContent = '…';
        if (deadlineEl) {
            deadlineEl.hidden = true;
            deadlineEl.textContent = '';
        }
        if (blockedEl) {
            blockedEl.hidden = true;
            blockedEl.textContent = '';
        }
        setFeedback('', false);

        const path = '/apps/arbeitszeitcheck/api/month-closure/status?year=' + encodeURIComponent(String(y)) + '&month=' + encodeURIComponent(String(m));
        Utils.ajax(path, {
            method: 'GET',
            onSuccess: function (data) {
                if (!data || !data.success) {
                    statusEl.textContent = '';
                    if (btn) {
                        btn.disabled = true;
                    }
                    return;
                }
                if (!data.featureEnabled) {
                    statusEl.textContent = '';
                    if (btn) {
                        btn.disabled = true;
                    }
                    if (blockedEl && data.finalizeBlockedMessage) {
                        blockedEl.textContent = data.finalizeBlockedMessage;
                        blockedEl.hidden = false;
                    }
                    setPdfLink(y, m, false);
                    return;
                }

                const grace = typeof data.graceDaysAfterEom === 'number' ? data.graceDaysAfterEom : parseInt(String(data.graceDaysAfterEom || 0), 10);
                const deadline = data.manualFinalizeDeadline;
                const isFinalized = data.status === 'finalized';
                const canFinalize = Boolean(data.canFinalize);

                if (blockedEl && !isFinalized && data.finalizeBlockedMessage) {
                    blockedEl.textContent = data.finalizeBlockedMessage;
                    blockedEl.hidden = false;
                }

                if (deadlineEl && grace > 0 && deadline && !isFinalized && canFinalize) {
                    const formatted = formatYmdLocal(deadline);
                    deadlineEl.textContent = window.t
                        ? window.t('arbeitszeitcheck', 'Please finalize this month by {date} (end of the configured grace period). After that, it may be sealed automatically if it is still open.', { date: formatted })
                        : ('Please finalize by ' + formatted + '.');
                    deadlineEl.hidden = false;
                }

                if (isFinalized) {
                    if (data.autoFinalized) {
                        statusEl.textContent = window.t
                            ? window.t('arbeitszeitcheck', 'Finalized automatically')
                            : 'Finalized automatically';
                    } else {
                        statusEl.textContent = window.t
                            ? window.t('arbeitszeitcheck', 'Finalized')
                            : 'Finalized';
                    }
                    if (btn) {
                        btn.disabled = true;
                    }
                    setPdfLink(y, m, true);
                } else {
                    statusEl.textContent = window.t
                        ? window.t('arbeitszeitcheck', 'Open')
                        : 'Open';
                    if (btn) {
                        btn.disabled = !canFinalize;
                    }
                    setPdfLink(y, m, false);
                }
            },
            onError: function () {
                statusEl.textContent = '';
                if (deadlineEl) {
                    deadlineEl.hidden = true;
                }
                if (blockedEl) {
                    blockedEl.hidden = true;
                }
                if (btn) {
                    btn.disabled = true;
                }
            }
        });
    }

    function init() {
        const yearSel = $('month-closure-year');
        const monthSel = $('month-closure-month');
        if (!yearSel || !monthSel) {
            return;
        }
        const now = new Date();
        const cy = now.getFullYear();
        for (let y = cy; y >= cy - 5; y--) {
            const opt = document.createElement('option');
            opt.value = String(y);
            opt.textContent = String(y);
            if (y === cy) {
                opt.selected = true;
            }
            yearSel.appendChild(opt);
        }
        for (let mo = 1; mo <= 12; mo++) {
            const opt = document.createElement('option');
            opt.value = String(mo);
            opt.textContent = String(mo);
            if (mo === now.getMonth() + 1) {
                opt.selected = true;
            }
            monthSel.appendChild(opt);
        }

        yearSel.addEventListener('change', refreshStatus);
        monthSel.addEventListener('change', refreshStatus);

        const fin = $('month-closure-finalize');
        if (fin) {
            fin.addEventListener('click', function () {
                if (fin.disabled) {
                    return;
                }
                const msg = fin.getAttribute('data-confirm-finalize') || '';
                if (msg && typeof window.confirm === 'function' && !window.confirm(msg)) {
                    return;
                }
                const y = parseInt(yearSel.value, 10);
                const m = parseInt(monthSel.value, 10);
                fin.disabled = true;
                setFeedback('', false);
                Utils.ajax('/apps/arbeitszeitcheck/api/month-closure/finalize', {
                    method: 'POST',
                    data: { year: y, month: m },
                    onSuccess: function (data) {
                        if (data && data.success) {
                            announce(window.t ? window.t('arbeitszeitcheck', 'Month finalized.') : 'Month finalized.', false);
                            refreshStatus();
                        } else {
                            announce((data && data.error) ? data.error : '', true);
                            refreshStatus();
                        }
                        fin.disabled = false;
                    },
                    onError: function (err) {
                        announce(err && err.error ? err.error : 'Error', true);
                        refreshStatus();
                        fin.disabled = false;
                    }
                });
            });
        }

        refreshStatus();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
