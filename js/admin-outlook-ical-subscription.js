/**
 * Admin UI for calendar (iCal) subscription links.
 */
(function () {
	'use strict'

	const Utils = window.ArbeitszeitCheckUtils || {}
	const Messaging = window.ArbeitszeitCheckMessaging || {}
	const App = window.ArbeitszeitCheck || {}
	const l10n = App.l10n || {}

	function q(selector) {
		return document.querySelector(selector)
	}

	function setLive(message, isError) {
		const live = q('#outlookIcalLive')
		if (!live) {
			return
		}
		live.textContent = message || ''
		live.classList.toggle('admin-settings-live--error', !!isError)
		live.classList.toggle('admin-settings-live--success', !!message && !isError)
	}

	function escapeHtml(value) {
		if (Utils.escapeHtml) {
			return Utils.escapeHtml(value)
		}
		const div = document.createElement('div')
		div.textContent = String(value)
		return div.innerHTML
	}

	function formatWindowRange(windowStart, windowEnd) {
		if (!windowStart || !windowEnd) {
			return ''
		}
		const template = l10n.outlookWindowRange || 'Current window: %1$s through %2$s (last 3 months through next 12 months).'
		return template.replace('%1$s', windowStart).replace('%2$s', windowEnd)
	}

	function initTeamPicker() {
		const search = q('#outlookIcalTeamSearch')
		const hidden = q('#outlookIcalTeamId')
		const list = q('#outlookIcalTeamListbox')
		const status = q('#outlookIcalTeamStatus')
		const clear = q('#outlookIcalTeamClear')
		const wrap = q('#outlook-ical-team-picker')
		if (!search || !hidden || !list || !wrap || !App.outlookIcalTeamsUrl) {
			return null
		}

		let requestId = 0

		function closeList() {
			list.hidden = true
			list.innerHTML = ''
			search.setAttribute('aria-expanded', 'false')
		}

		function openList() {
			list.hidden = false
			search.setAttribute('aria-expanded', 'true')
		}

		function setStatus(message) {
			if (status) {
				status.textContent = message || ''
			}
		}

		function selectTeam(team) {
			hidden.value = String(team.id)
			search.value = team.path || team.name || String(team.id)
			search.dataset.selectedLabel = search.value
			if (clear) {
				clear.hidden = false
			}
			closeList()
			setStatus(search.value)
			invalidateResult()
			refreshButtons()
		}

		function clearSelection() {
			hidden.value = ''
			search.value = ''
			search.dataset.selectedLabel = ''
			if (clear) {
				clear.hidden = true
			}
			closeList()
			setStatus('')
			invalidateResult()
			refreshButtons()
		}

		function effectiveSearchTerm() {
			const query = String(search.value || '').trim()
			const selected = String(search.dataset.selectedLabel || '').trim()
			// Re-opening the picker after a selection must list every scope, not filter by the label already shown.
			if (query !== '' && selected !== '' && query === selected) {
				return ''
			}
			return query
		}

		function renderTeams(teams) {
			if (!Array.isArray(teams) || teams.length === 0) {
				list.innerHTML = '<div class="user-picker__item user-picker__item--muted" role="presentation">' + escapeHtml(l10n.outlookNoTeams || 'No matching teams found.') + '</div>'
				openList()
				setStatus(l10n.outlookNoTeams || 'No matching teams found.')
				return
			}
			list.innerHTML = teams.map((team, index) => {
				const label = team.path || team.name || String(team.id)
				return '<button type="button" class="user-picker__item" role="option" data-team-index="' + index + '">' + escapeHtml(label) + '</button>'
			}).join('')
			openList()
			list.querySelectorAll('[data-team-index]').forEach((button) => {
				button.addEventListener('click', () => {
					const idx = Number(button.getAttribute('data-team-index'))
					selectTeam(teams[idx])
				})
			})
			setStatus(String(teams.length))
		}

		function loadTeams() {
			const params = new URLSearchParams({
				search: effectiveSearchTerm(),
				limit: '20',
			})
			const url = App.outlookIcalTeamsUrl + '?' + params.toString()
			const thisRequest = ++requestId
			list.innerHTML = '<div class="user-picker__item user-picker__item--muted" role="presentation">' + escapeHtml(l10n.outlookLoadingTeams || 'Loading teams…') + '</div>'
			openList()
			Utils.ajax(url, {
				method: 'GET',
				onSuccess(data) {
					if (thisRequest !== requestId) {
						return
					}
					renderTeams(data && data.teams ? data.teams : [])
				},
				onError(err) {
					if (thisRequest !== requestId) {
						return
					}
					const message = (err && err.error) || l10n.outlookTeamLoadFailed || 'Failed to load teams. Please try again.'
					list.innerHTML = '<div class="user-picker__item user-picker__item--muted" role="presentation">' + escapeHtml(message) + '</div>'
					openList()
					setStatus(message)
				},
			})
		}

		search.addEventListener('focus', () => {
			loadTeams()
		})
		search.addEventListener('input', () => {
			const query = String(search.value || '').trim()
			const selected = String(search.dataset.selectedLabel || '').trim()
			if (query !== selected) {
				hidden.value = ''
				search.dataset.selectedLabel = ''
			}
			if (clear) {
				clear.hidden = query === ''
			}
			invalidateResult()
			refreshButtons()
			loadTeams()
		})
		search.addEventListener('keydown', (event) => {
			if (event.key === 'Escape') {
				closeList()
			}
		})
		document.addEventListener('click', (event) => {
			if (!wrap.contains(event.target)) {
				closeList()
			}
		})
		if (clear) {
			clear.addEventListener('click', clearSelection)
		}

		return {
			getValue: () => hidden.value,
			clear: clearSelection,
			prefetchOrgWide() {
				if (hidden.value !== '' || App.useAppTeams) {
					return
				}
				Utils.ajax(App.outlookIcalTeamsUrl + '?' + new URLSearchParams({ limit: '5' }).toString(), {
					method: 'GET',
					onSuccess(data) {
						const teams = data && data.teams ? data.teams : []
						const orgWide = teams.find((team) => team.orgWide || Number(team.id) === 0)
						if (orgWide) {
							selectTeam(orgWide)
						}
					},
				})
			},
		}
	}

	function invalidateResult() {
		const resultCard = q('#outlookIcalResultCard')
		const feedUrl = q('#outlookIcalFeedUrl')
		const rotateButton = q('#outlookIcalRotateBtn')
		const windowRange = q('#outlookIcalWindowRange')
		if (resultCard) {
			resultCard.hidden = true
		}
		if (feedUrl) {
			feedUrl.value = ''
		}
		if (windowRange) {
			windowRange.hidden = true
			windowRange.textContent = ''
		}
		if (rotateButton) {
			rotateButton.disabled = true
		}
	}

	function hasTeamScopeSelected() {
		const raw = q('#outlookIcalTeamId')?.value
		return raw !== null && raw !== undefined && String(raw) !== ''
	}

	function hasFeedLanguageSelected() {
		const select = q('#outlookIcalFeedLanguage')
		return !!(select && String(select.value || '').trim() !== '')
	}

	function refreshButtons() {
		const generateButton = q('#outlookIcalGenerateBtn')
		if (generateButton) {
			generateButton.disabled = !(hasTeamScopeSelected() && hasFeedLanguageSelected())
		}
	}

	function initLanguageSelect() {
		const select = q('#outlookIcalFeedLanguage')
		if (!select) {
			return
		}
		const root = q('#outlook-ical-subscription')
		if (root && root.dataset.defaultFeedLanguage && !select.value) {
			select.value = root.dataset.defaultFeedLanguage
		}
		select.addEventListener('change', () => {
			invalidateResult()
			refreshButtons()
		})
	}

	function updateResult(data) {
		const resultCard = q('#outlookIcalResultCard')
		const feedUrl = q('#outlookIcalFeedUrl')
		const eventCount = q('#outlookIcalEventCount')
		const windowRange = q('#outlookIcalWindowRange')
		const rotateButton = q('#outlookIcalRotateBtn')
		if (resultCard) {
			resultCard.hidden = false
		}
		if (feedUrl) {
			feedUrl.value = String(data.feedWebcalUrl || data.feedUrl || '')
		}
		if (eventCount) {
			const template = l10n.outlookEventCount || 'Approved absences in the current window: %d'
			let text = template.replace('%d', String(data.eventCount || 0))
			if (data.feedLanguageCode) {
				const langTemplate = l10n.outlookFeedLanguageSaved || 'Calendar language for this link: %s'
				text += ' ' + langTemplate.replace('%s', String(data.feedLanguageCode))
			}
			eventCount.textContent = text
		}
		if (windowRange) {
			const rangeText = formatWindowRange(data.windowStart, data.windowEnd)
			if (rangeText) {
				windowRange.textContent = rangeText
				windowRange.hidden = false
			} else {
				windowRange.hidden = true
				windowRange.textContent = ''
			}
		}
		if (rotateButton) {
			rotateButton.disabled = !data.feedUrl
		}
	}

	function rotateToken(requireConfirm) {
		const teamId = q('#outlookIcalTeamId')?.value
		const languageCode = q('#outlookIcalFeedLanguage')?.value || ''
		const generateButton = q('#outlookIcalGenerateBtn')
		const rotateButton = q('#outlookIcalRotateBtn')
		if (!hasTeamScopeSelected() || !hasFeedLanguageSelected()) {
			const message = l10n.outlookPickScopeLanguage || 'Pick who is included and a calendar language first.'
			Messaging.showError?.(message)
			setLive(message, true)
			return
		}
		if (requireConfirm && !(window.confirm(l10n.outlookRotateConfirm || 'Rotate the subscription link now?'))) {
			return
		}

		generateButton && (generateButton.disabled = true)
		rotateButton && (rotateButton.disabled = true)
		setLive(l10n.outlookRotating || 'Generating subscription link…', false)

		Utils.ajax(App.outlookIcalRotateUrl, {
			method: 'POST',
			data: {
				teamId: Number(teamId),
				languageCode,
			},
			onSuccess(data) {
				updateResult(data || {})
				const message = l10n.outlookFeedReady || 'Subscription link ready.'
				Messaging.showSuccess?.(message)
				setLive(message, false)
				refreshButtons()
			},
			onError(err) {
				const message = (err && err.error) || 'Failed to generate the subscription link.'
				Messaging.showError?.(message)
				setLive(message, true)
				refreshButtons()
			},
		})
	}

	function initWebcalLocalAccessPanel() {
		const panel = q('#outlookIcalWebcalLocalAccess')
		const text = q('#outlookIcalWebcalLocalAccessText')
		const button = q('#outlookIcalEnableWebcalLocalBtn')
		const url = App.outlookIcalWebcalLocalAccessUrl
		if (!panel || !text || !url) {
			return
		}

		function renderState(data) {
			if (!data || data.enabled) {
				panel.hidden = true
				text.textContent = ''
				if (button) {
					button.hidden = true
				}
				return
			}

			panel.hidden = false
			if (data.canEnable) {
				text.textContent = l10n.outlookWebcalLocalAccessNeeded || 'Nextcloud Calendar on this server needs a one-time server setting before it can subscribe to this link.'
				if (button) {
					button.hidden = false
				}
				return
			}

			text.textContent = l10n.outlookWebcalLocalAccessAskAdmin || 'To subscribe in Nextcloud Calendar on this server, ask a Nextcloud server administrator to open ArbeitszeitCheck admin settings and enable calendar subscriptions here once. Thunderbird, Outlook, and phones work without this step.'
			if (button) {
				button.hidden = true
			}
		}

		Utils.ajax(url, {
			method: 'GET',
			onSuccess(data) {
				renderState(data || {})
			},
			onError() {
				panel.hidden = true
			},
		})

		button?.addEventListener('click', () => {
			button.disabled = true
			Utils.ajax(url, {
				method: 'POST',
				onSuccess(data) {
					renderState(data || { enabled: true })
					const message = l10n.outlookWebcalLocalAccessEnabled || 'Nextcloud Calendar can now subscribe to feeds on this server.'
					Messaging.showSuccess?.(message)
					setLive(message, false)
				},
				onError(err) {
					const message = (err && err.error) || (l10n.outlookWebcalLocalAccessFailed || 'Could not enable calendar subscriptions on this server.')
					Messaging.showError?.(message)
					setLive(message, true)
					button.disabled = false
				},
			})
		})
	}

	function initCopyButton() {
		const copyButton = q('#outlookIcalCopyBtn')
		const feedUrl = q('#outlookIcalFeedUrl')
		if (!copyButton || !feedUrl) {
			return
		}

		copyButton.addEventListener('click', async () => {
			const value = String(feedUrl.value || '')
			if (!value) {
				return
			}
			try {
				await navigator.clipboard.writeText(value)
				Messaging.showSuccess?.(l10n.outlookCopySuccess || 'Subscription link copied.')
				setLive(l10n.outlookCopySuccess || 'Subscription link copied.', false)
			} catch (_error) {
				feedUrl.focus()
				feedUrl.select()
				document.execCommand('copy')
				Messaging.showSuccess?.(l10n.outlookCopyFallback || 'Copy the subscription link manually.')
				setLive(l10n.outlookCopyFallback || 'Copy the subscription link manually.', false)
			}
		})
	}

	function init() {
		if (!q('#section-outlook-subscription-heading')) {
			return
		}
		const root = q('#outlook-ical-subscription')
		if (root) {
			App.outlookIcalTeamsUrl = App.outlookIcalTeamsUrl || root.dataset.outlookTeamsUrl || ''
			App.outlookIcalRotateUrl = App.outlookIcalRotateUrl || root.dataset.outlookRotateUrl || ''
			App.outlookIcalWebcalLocalAccessUrl = App.outlookIcalWebcalLocalAccessUrl || root.dataset.outlookWebcalLocalAccessUrl || ''
			App.useAppTeams = root.dataset.useAppTeams === '1'
			App.outlookIcalOrgWideAvailable = root.dataset.orgWideAvailable === '1'
		}
		const teamPicker = initTeamPicker()
		initLanguageSelect()
		initWebcalLocalAccessPanel()
		q('#outlookIcalGenerateBtn')?.addEventListener('click', () => rotateToken(false))
		q('#outlookIcalRotateBtn')?.addEventListener('click', () => rotateToken(true))
		initCopyButton()
		refreshButtons()
		if (teamPicker && App.outlookIcalOrgWideAvailable) {
			teamPicker.prefetchOrgWide?.()
		}
	}

	document.addEventListener('DOMContentLoaded', init)
})()
