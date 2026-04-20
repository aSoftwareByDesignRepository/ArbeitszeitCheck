/**
 * Manager / admin: revision PDFs — choose month first, then list people with actionable sealed data.
 *
 * Config: read from data-* on .manager-month-closures-page (server-rendered), with optional
 * window.OCA.ArbeitszeitCheck.managerMonthClosures fallback. URLs must never rely on executable
 * inline scripts alone (CSP / load order).
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

(function () {
    'use strict';

    /**
     * @returns {{ revisionPdfAvailableMonthsUrl: string, revisionPdfUsersForMonthUrl: string, pdfUrlBase: string }}
     */
    function readConfigFromDom() {
        const root = document.querySelector('.manager-month-closures-page');
        const fromWin = (window.OCA && window.OCA.ArbeitszeitCheck && window.OCA.ArbeitszeitCheck.managerMonthClosures) || {};
        if (!root || !root.dataset) {
            return {
                revisionPdfAvailableMonthsUrl: fromWin.revisionPdfAvailableMonthsUrl || '',
                revisionPdfUsersForMonthUrl: fromWin.revisionPdfUsersForMonthUrl || '',
                pdfUrlBase: fromWin.pdfUrlBase || '',
            };
        }
        const ds = root.dataset;
        return {
            revisionPdfAvailableMonthsUrl: ds.revisionPdfAvailableMonthsUrl || fromWin.revisionPdfAvailableMonthsUrl || '',
            revisionPdfUsersForMonthUrl: ds.revisionPdfUsersForMonthUrl || fromWin.revisionPdfUsersForMonthUrl || '',
            pdfUrlBase: ds.pdfUrlBase !== undefined ? String(ds.pdfUrlBase) : (fromWin.pdfUrlBase || ''),
        };
    }

    /**
     * @returns {Record<string, string>}
     */
    function readL10nMap() {
        const fromWin = (window.ArbeitszeitCheck && window.ArbeitszeitCheck.l10n) || {};
        const el = document.getElementById('manager-mc-l10n-json');
        if (!el || !el.textContent) {
            return fromWin;
        }
        try {
            const parsed = JSON.parse(el.textContent);
            if (parsed && typeof parsed === 'object') {
                return Object.assign({}, fromWin, parsed);
            }
        } catch (e) {
            // ignore parse errors; use window fallback
        }
        return fromWin;
    }

    function t(l10n, key, fallback) {
        if (l10n && typeof l10n[key] === 'string' && l10n[key] !== '') {
            return l10n[key];
        }
        return fallback || key;
    }

    /** English msgids — must match l10n JSON from PHP ($l->t('January') etc.) */
    const MONTH_MSGIDS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    /**
     * Localized month + year for dropdown (uses app translations, same keys as calendar).
     * @param {Record<string, string>} l10n
     * @param {number} y
     * @param {number} m 1–12
     */
    function monthLabel(l10n, y, m) {
        const mi = parseInt(String(m), 10);
        if (mi < 1 || mi > 12) {
            return String(y) + '-' + String(m).padStart(2, '0');
        }
        const monthName = t(l10n, MONTH_MSGIDS[mi - 1], MONTH_MSGIDS[mi - 1]);
        return monthName + ' ' + String(y);
    }

    function buildPdfUrl(cfg, userId, year, month) {
        const base = cfg.pdfUrlBase || '';
        if (!base) {
            return '#';
        }
        let u;
        try {
            u = new URL(base, window.location.origin);
        } catch (e) {
            u = new URL(window.location.origin + base);
        }
        u.searchParams.set('year', String(year));
        u.searchParams.set('month', String(month));
        u.searchParams.set('userId', userId);
        return u.pathname + u.search + u.hash;
    }

    function downloadAriaLabel(l10n, displayName) {
        const tpl = t(l10n, 'Download revision PDF for {name}', 'Download revision PDF for {name}');
        return tpl.replace(/\{name\}/g, displayName);
    }

    function init() {
        const cfg = readConfigFromDom();
        const l10n = readL10nMap();

        const Utils = window.ArbeitszeitCheckUtils;
        const ajax = Utils && typeof Utils.ajax === 'function' ? Utils.ajax.bind(Utils) : null;

        if (!cfg.revisionPdfAvailableMonthsUrl || !cfg.revisionPdfUsersForMonthUrl) {
            return;
        }

        const monthSel = document.getElementById('manager-mc-month-select');
        const monthLoadStatus = document.getElementById('manager-mc-month-load-status');
        const peopleList = document.getElementById('manager-mc-people-list');
        const peopleEmpty = document.getElementById('manager-mc-people-empty');
        const peopleStatus = document.getElementById('manager-mc-people-status');
        const pageErr = document.getElementById('manager-mc-page-error');

        if (!monthSel || !peopleList || !peopleEmpty || !peopleStatus) {
            return;
        }

        function showPageError(msg) {
            if (!pageErr) {
                return;
            }
            pageErr.textContent = msg;
            pageErr.hidden = false;
        }

        function clearPageError() {
            if (pageErr) {
                pageErr.hidden = true;
                pageErr.textContent = '';
            }
        }

        function setMonthSelectLoading(loading) {
            monthSel.disabled = !!loading;
            monthSel.setAttribute('aria-busy', loading ? 'true' : 'false');
        }

        function renderPeople(users, year, month) {
            peopleList.innerHTML = '';
            peopleList.hidden = true;
            peopleEmpty.hidden = true;
            peopleStatus.textContent = '';

            if (!users || users.length === 0) {
                peopleEmpty.textContent = t(
                    l10n,
                    'No one has a finalized revision for this month in your scope.',
                    'No one has a finalized revision for this month in your scope.'
                );
                peopleEmpty.hidden = false;
                return;
            }

            users.forEach(function (u) {
                const uid = u.userId || '';
                const name = (u.displayName && String(u.displayName).trim()) ? String(u.displayName) : uid;
                const email = u.email ? String(u.email) : '';
                const li = document.createElement('li');
                li.className = 'manager-mc-person-row';

                const info = document.createElement('div');
                info.className = 'manager-mc-person-info';
                const nameEl = document.createElement('span');
                nameEl.className = 'manager-mc-person-name';
                nameEl.textContent = name;
                info.appendChild(nameEl);
                if (email) {
                    const meta = document.createElement('span');
                    meta.className = 'manager-mc-person-meta';
                    meta.textContent = uid + ' · ' + email;
                    info.appendChild(meta);
                } else {
                    const meta = document.createElement('span');
                    meta.className = 'manager-mc-person-meta';
                    meta.textContent = uid;
                    info.appendChild(meta);
                }

                const btn = document.createElement('a');
                const pdfHref = buildPdfUrl(cfg, uid, year, month);
                const pdfMissing = !cfg.pdfUrlBase || pdfHref === '#';
                btn.href = pdfHref;
                btn.className = 'btn btn--primary manager-mc-person-download' +
                    (pdfMissing ? ' manager-mc-person-download--disabled' : '');
                if (pdfMissing) {
                    btn.setAttribute('aria-disabled', 'true');
                }
                btn.textContent = t(l10n, 'Download PDF', 'Download PDF');
                btn.setAttribute('aria-label', downloadAriaLabel(l10n, name));

                li.appendChild(info);
                li.appendChild(btn);
                peopleList.appendChild(li);
            });

            peopleList.hidden = false;
        }

        function loadPeopleForMonth(year, month) {
            peopleEmpty.hidden = true;
            peopleList.hidden = true;
            peopleList.innerHTML = '';
            peopleStatus.textContent = t(l10n, 'Loading…', 'Loading…');
            clearPageError();

            if (!ajax) {
                peopleStatus.textContent = '';
                showPageError(t(l10n, 'Could not initialize the month list. Please reload the page.', 'Could not initialize the month list. Please reload the page.'));
                return;
            }

            const url = cfg.revisionPdfUsersForMonthUrl +
                (cfg.revisionPdfUsersForMonthUrl.indexOf('?') >= 0 ? '&' : '?') +
                'year=' + encodeURIComponent(String(year)) +
                '&month=' + encodeURIComponent(String(month));

            ajax(url, {
                method: 'GET',
                onSuccess: function (data) {
                    peopleStatus.textContent = '';
                    if (!data || !data.success || !Array.isArray(data.users)) {
                        showPageError(t(l10n, 'Could not load people for this month.', 'Could not load people for this month.'));
                        return;
                    }
                    renderPeople(data.users, year, month);
                },
                onError: function (err) {
                    peopleStatus.textContent = '';
                    let msg = t(l10n, 'Could not load people for this month.', 'Could not load people for this month.');
                    if (err && typeof err.error === 'string' && err.error !== '') {
                        msg = err.error;
                    } else if (err && err.data && typeof err.data.error === 'string' && err.data.error !== '') {
                        msg = err.data.error;
                    }
                    showPageError(msg);
                }
            });
        }

        function loadAvailableMonths() {
            if (!ajax) {
                monthSel.innerHTML = '';
                monthSel.appendChild(new Option(
                    t(l10n, 'Could not initialize the month list. Please reload the page.', 'Could not initialize the month list. Please reload the page.'),
                    ''
                ));
                monthSel.disabled = true;
                monthLoadStatus.textContent = '';
                showPageError(t(l10n, 'Could not initialize the month list. Please reload the page.', 'Could not initialize the month list. Please reload the page.'));
                return;
            }

            setMonthSelectLoading(true);
            monthLoadStatus.textContent = t(l10n, 'Loading…', 'Loading…');
            clearPageError();

            try {
                ajax(cfg.revisionPdfAvailableMonthsUrl, {
                    method: 'GET',
                    onSuccess: function (data) {
                        try {
                            setMonthSelectLoading(false);
                            monthLoadStatus.textContent = '';
                            monthSel.innerHTML = '';

                            if (!data || !data.success || !Array.isArray(data.months)) {
                                monthSel.appendChild(new Option(t(l10n, 'Could not load months.', 'Could not load months.'), ''));
                                monthSel.disabled = true;
                                showPageError(t(l10n, 'Could not load months.', 'Could not load months.'));
                                return;
                            }

                            if (data.months.length === 0) {
                                const opt = new Option(
                                    t(l10n, 'No finalized months are available for your access yet.', 'No finalized months are available for your access yet.'),
                                    ''
                                );
                                monthSel.add(opt);
                                monthSel.disabled = true;
                                peopleEmpty.textContent = t(
                                    l10n,
                                    'No finalized months are available for your access yet.',
                                    'No finalized months are available for your access yet.'
                                );
                                peopleEmpty.hidden = false;
                                return;
                            }

                            monthSel.add(new Option(t(l10n, 'Choose month…', 'Choose month…'), ''));
                            data.months.forEach(function (row) {
                                const v = String(row.year) + '-' + String(row.month).padStart(2, '0');
                                monthSel.add(new Option(monthLabel(l10n, row.year, row.month), v));
                            });
                            monthSel.disabled = false;

                            peopleEmpty.textContent = t(
                                l10n,
                                'Select a month to see who you can download for.',
                                'Select a month to see who you can download for.'
                            );
                            peopleEmpty.hidden = false;
                        } catch (e) {
                            setMonthSelectLoading(false);
                            monthLoadStatus.textContent = '';
                            monthSel.innerHTML = '';
                            monthSel.appendChild(new Option(t(l10n, 'Could not load months.', 'Could not load months.'), ''));
                            monthSel.disabled = true;
                            showPageError(t(l10n, 'Could not load months.', 'Could not load months.'));
                        }
                    },
                    onError: function (err) {
                        setMonthSelectLoading(false);
                        monthLoadStatus.textContent = '';
                        monthSel.innerHTML = '';
                        monthSel.appendChild(new Option(t(l10n, 'Could not load months.', 'Could not load months.'), ''));
                        monthSel.disabled = true;
                        let msg = t(l10n, 'Could not load months.', 'Could not load months.');
                        if (err && typeof err.error === 'string' && err.error !== '') {
                            msg = err.error;
                        } else if (err && err.data && typeof err.data.error === 'string' && err.data.error !== '') {
                            msg = err.data.error;
                        }
                        showPageError(msg);
                    }
                });
            } catch (e) {
                setMonthSelectLoading(false);
                monthLoadStatus.textContent = '';
                monthSel.innerHTML = '';
                monthSel.appendChild(new Option(t(l10n, 'Could not load months.', 'Could not load months.'), ''));
                monthSel.disabled = true;
                showPageError(t(l10n, 'Could not load months.', 'Could not load months.'));
            }
        }

        monthSel.addEventListener('change', function () {
            const v = monthSel.value;
            clearPageError();
            if (!v || v.indexOf('-') < 0) {
                peopleList.innerHTML = '';
                peopleList.hidden = true;
                peopleEmpty.textContent = t(
                    l10n,
                    'Select a month to see who you can download for.',
                    'Select a month to see who you can download for.'
                );
                peopleEmpty.hidden = false;
                peopleStatus.textContent = '';
                return;
            }
            const p = v.split('-');
            const y = parseInt(p[0], 10);
            const m = parseInt(p[1], 10);
            loadPeopleForMonth(y, m);
        });

        loadAvailableMonths();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
