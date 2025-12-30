<template>
	<div class="timetracking-dashboard">
		<div class="timetracking-dashboard__header">
			<h2 class="timetracking-dashboard__title">{{ $t('arbeitszeitcheck', 'Time Tracking Dashboard') }}</h2>
			<p class="timetracking-dashboard__subtitle">{{ $t('arbeitszeitcheck', 'Track your working hours legally and compliantly') }}</p>
		</div>

		<div class="timetracking-dashboard__content">
			<!-- Clock Section -->
			<div class="timetracking-clock-section">
				<h3 class="timetracking-section-title">{{ $t('arbeitszeitcheck', 'Quick Actions') }}</h3>
				<p class="timetracking-section-help">
					{{ $t('arbeitszeitcheck', 'Use these buttons to track your working hours. Click "Clock In" when you start working and "Clock Out" when you finish.') }}
				</p>
				<div class="timetracking-clock-buttons">
					<div class="action-with-explanation">
						<NcButton
							type="primary"
							:disabled="isLoading || currentStatus.status === 'active' || currentStatus.status === 'break'"
							:aria-label="$t('arbeitszeitcheck', 'Clock in to start tracking time')"
							:title="$t('arbeitszeitcheck', 'Click this button when you start working. The system will track your working hours automatically.')"
							@click="clockIn"
						>
							{{ $t('arbeitszeitcheck', 'Clock In') }}
						</NcButton>
						<p class="action-explanation">
							{{ $t('arbeitszeitcheck', 'Click when you start working') }}
						</p>
					</div>
					<div class="action-with-explanation">
						<NcButton
							type="secondary"
							:disabled="isLoading || currentStatus.status !== 'active'"
							:aria-label="$t('arbeitszeitcheck', 'Clock out to end tracking time')"
							:title="$t('arbeitszeitcheck', 'Click this button when you finish working. The system will stop tracking your working hours.')"
							@click="clockOut"
						>
							{{ $t('arbeitszeitcheck', 'Clock Out') }}
						</NcButton>
						<p class="action-explanation">
							{{ $t('arbeitszeitcheck', 'Click when you finish working') }}
						</p>
					</div>
					<div class="action-with-explanation">
						<NcButton
							type="tertiary"
							:disabled="isLoading || currentStatus.status !== 'active'"
							:aria-label="$t('arbeitszeitcheck', 'Start break')"
							:title="$t('arbeitszeitcheck', 'Click to start your break. German labor law requires breaks after 6 hours of work.')"
							@click="startBreak"
						>
							{{ $t('arbeitszeitcheck', 'Start Break') }}
						</NcButton>
						<p class="action-explanation">
							{{ $t('arbeitszeitcheck', 'Start your break time') }}
						</p>
					</div>
					<div class="action-with-explanation">
						<NcButton
							type="tertiary"
							:disabled="isLoading || currentStatus.status !== 'break'"
							:aria-label="$t('arbeitszeitcheck', 'End break')"
							:title="$t('arbeitszeitcheck', 'Click to end your break and resume working.')"
							@click="endBreak"
						>
							{{ $t('arbeitszeitcheck', 'End Break') }}
						</NcButton>
						<p class="action-explanation">
							{{ $t('arbeitszeitcheck', 'End your break time') }}
						</p>
					</div>
				</div>
			</div>

			<!-- Status Section -->
			<div class="timetracking-status-section">
				<h3 class="timetracking-section-title">{{ $t('arbeitszeitcheck', 'Current Status') }}</h3>
				<p class="timetracking-section-help">
					{{ $t('arbeitszeitcheck', 'Your current working status and today\'s working hours.') }}
				</p>
				<NcLoadingIcon v-if="isLoading" />
				<div v-else class="timetracking-status-display">
					<p :class="getStatusClass()">
						<strong>{{ $t('arbeitszeitcheck', 'Status') }}:</strong>
						<span :aria-live="currentStatus.status === 'active' || currentStatus.status === 'break' ? 'polite' : 'off'">
							{{ getStatusText() }}
						</span>
					</p>
					<p v-if="currentStatus.current_session_duration">
						<strong>{{ $t('arbeitszeitcheck', 'Current Session') }}:</strong>
						{{ formatDuration(currentStatus.current_session_duration) }}
					</p>
					<p v-if="currentStatus.working_today_hours !== undefined">
						<strong>{{ $t('arbeitszeitcheck', 'Today\'s Hours') }}:</strong>
						{{ formatHours(currentStatus.working_today_hours) }}
					</p>
				</div>
			</div>

			<!-- Break Warning Section -->
			<div v-if="breakStatus.break_required" class="break-warning-section">
				<div class="break-warning" :class="breakWarningClass" role="alert">
					<div class="break-warning__icon" aria-hidden="true">⏰</div>
					<div class="break-warning__content">
						<h4 class="break-warning__title">
							{{ $t('arbeitszeitcheck', 'Break Required') }}
						</h4>
						<p class="break-warning__message">
							{{ breakWarningMessage }}
						</p>
						<NcButton
							type="primary"
							@click="startBreak"
							:disabled="isLoading || currentStatus.status !== 'active'"
							:aria-label="$t('arbeitszeitcheck', 'Start your break now')"
						>
							{{ $t('arbeitszeitcheck', 'Take Break Now') }}
						</NcButton>
					</div>
				</div>
			</div>

			<!-- Today's Summary -->
			<div class="timetracking-today-section">
				<h3 class="timetracking-section-title">{{ $t('arbeitszeitcheck', 'Today\'s Summary') }}</h3>
				<p class="timetracking-section-help">
					{{ $t('arbeitszeitcheck', 'Overview of your working hours, break time, and overtime for today.') }}
				</p>
				<div class="timetracking-today-summary">
					<div class="timetracking-summary-item">
						<div class="timetracking-summary-item__label">{{ $t('arbeitszeitcheck', 'Working Hours') }}</div>
						<div class="timetracking-summary-item__value">{{ formatHours(todayStats.workingHours) }}</div>
					</div>
					<div class="timetracking-summary-item">
						<div class="timetracking-summary-item__label">{{ $t('arbeitszeitcheck', 'Break Time') }}</div>
						<div class="timetracking-summary-item__value">{{ formatHours(todayStats.breakTime) }}</div>
					</div>
					<div class="timetracking-summary-item">
						<div class="timetracking-summary-item__label">{{ $t('arbeitszeitcheck', 'Overtime') }}</div>
						<div class="timetracking-summary-item__value">{{ formatHours(todayStats.overtime) }}</div>
					</div>
					<div 
						class="timetracking-summary-item timetracking-summary-item--clickable"
						@click="navigateToCompliance"
						role="button"
						tabindex="0"
						:aria-label="$t('arbeitszeitcheck', 'View compliance details and recommendations')"
						@keydown.enter="navigateToCompliance"
						@keydown.space.prevent="navigateToCompliance"
					>
						<div class="timetracking-summary-item__label">
							{{ $t('arbeitszeitcheck', 'Compliance') }}
							<button 
								class="help-icon-button"
								:title="$t('arbeitszeitcheck', 'Compliance status shows whether your working hours follow German labor law (ArbZG) requirements. Click for more information.')"
								:aria-label="$t('arbeitszeitcheck', 'Show help about compliance status')"
								@click.stop="showComplianceHelp = !showComplianceHelp"
							>
								ℹ️
							</button>
						</div>
						<div class="timetracking-summary-item__value" :class="todayStats.complianceStatus === 'good' ? 'timetracking-status--good' : 'timetracking-status--warning'">
							<!-- ✅ CORRECT: Icon + Text together -->
							<span class="compliance-status-display">
								<span class="compliance-status-display__icon" aria-hidden="true">
									{{ todayStats.complianceStatus === 'good' ? '✓' : '⚠' }}
								</span>
								<span class="compliance-status-display__text">
									{{ todayStats.complianceStatus === 'good' 
										? $t('arbeitszeitcheck', 'Compliant') 
										: $t('arbeitszeitcheck', 'Attention Required') }}
								</span>
							</span>
						</div>
						<p class="compliance-status-explanation">
							{{ todayStats.complianceStatus === 'good'
								? $t('arbeitszeitcheck', 'All working time regulations are being followed correctly')
								: $t('arbeitszeitcheck', 'Some regulations may not be met. Click for details.') }}
						</p>
						<div class="compliance-action-hint">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
								<polyline points="9 18 15 12 9 6"/>
							</svg>
							<span>{{ $t('arbeitszeitcheck', 'Click to view details') }}</span>
						</div>
						<div v-if="showComplianceHelp" class="compliance-help-text" @click.stop>
							<p>
								{{ $t('arbeitszeitcheck', 'German labor law (ArbZG) requires:') }}
							</p>
							<ul>
								<li>{{ $t('arbeitszeitcheck', 'Maximum 8 hours of work per day (can be extended to 10 hours in special cases)') }}</li>
								<li>{{ $t('arbeitszeitcheck', 'At least 30 minutes break after 6 hours of work') }}</li>
								<li>{{ $t('arbeitszeitcheck', 'At least 45 minutes break after 9 hours of work') }}</li>
								<li>{{ $t('arbeitszeitcheck', 'At least 11 hours of rest between shifts') }}</li>
							</ul>
						</div>
					</div>
				</div>
			</div>

			<!-- Recent Entries -->
			<div class="timetracking-recent-section">
				<h3 class="timetracking-section-title">{{ $t('arbeitszeitcheck', 'Recent Time Entries') }}</h3>
				<NcEmptyContent
					v-if="recentEntries.length === 0"
					:title="$t('arbeitszeitcheck', 'No time entries yet')"
					:description="$t('arbeitszeitcheck', 'Start tracking your time by clicking the Clock In button above')"
				>
					<template #icon>
						<span aria-hidden="true">📋</span>
					</template>
				</NcEmptyContent>
				<table v-else class="timetracking-table">
					<thead>
						<tr>
							<th>{{ $t('arbeitszeitcheck', 'Date') }}</th>
							<th>{{ $t('arbeitszeitcheck', 'Start Time') }}</th>
							<th>{{ $t('arbeitszeitcheck', 'End Time') }}</th>
							<th>{{ $t('arbeitszeitcheck', 'Duration') }}</th>
							<th>{{ $t('arbeitszeitcheck', 'Status') }}</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="entry in recentEntries" :key="entry.id">
							<td>{{ formatDate(entry.startTime) }}</td>
							<td>{{ formatTime(entry.startTime) }}</td>
							<td>{{ entry.endTime ? formatTime(entry.endTime) : '-' }}</td>
							<td>{{ entry.durationHours ? formatHours(entry.durationHours) : '-' }}</td>
							<td>
								<span :class="getEntryStatusClass(entry.status)">
									{{ getEntryStatusText(entry.status) }}
								</span>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</template>

<script>
import { NcButton, NcLoadingIcon, NcEmptyContent } from '@nextcloud/vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { formatDateGerman } from '../utils/dateUtils.js'
import { getUserFriendlyError } from '../utils/errorMessages.js'

export default {
	name: 'Dashboard',
	components: {
		NcButton,
		NcLoadingIcon,
		NcEmptyContent
	},
	data() {
		return {
			isLoading: false,
			currentStatus: {
				status: 'clocked_out',
				current_entry: null,
				working_today_hours: 0,
				current_session_duration: null
			},
			todayStats: {
				workingHours: 0,
				breakTime: 0,
				overtime: 0,
				complianceStatus: 'good'
			},
			breakStatus: {
				hours_worked: 0,
				required_break_minutes: 0,
				taken_break_minutes: 0,
				remaining_break_minutes: 0,
				break_required: false,
				warning_level: 'none'
			},
			recentEntries: [],
			statusUpdateInterval: null,
			breakStatusInterval: null,
			showComplianceHelp: false
		}
	},
	computed: {
		breakWarningClass() {
			if (this.breakStatus.warning_level === 'critical') {
				return 'break-warning--critical'
			} else if (this.breakStatus.warning_level === 'warning') {
				return 'break-warning--warning'
			}
			return 'break-warning--info'
		},
		breakWarningMessage() {
			const hours = this.breakStatus.hours_worked.toFixed(1)
			const remaining = Math.ceil(this.breakStatus.remaining_break_minutes)
			
			if (this.breakStatus.hours_worked >= 9) {
				return this.$t('arbeitszeitcheck',
					'You have worked {hours} hours today. According to German labor law, you must take a {minutes}-minute break. You still need {remaining} minutes of break time.',
					{ hours, minutes: 45, remaining }
				)
			} else if (this.breakStatus.hours_worked >= 6) {
				return this.$t('arbeitszeitcheck',
					'You have worked {hours} hours today. According to German labor law, you must take a {minutes}-minute break. You still need {remaining} minutes of break time.',
					{ hours, minutes: 30, remaining }
				)
			}
			return ''
		}
	},
	mounted() {
		this.loadStatus()
		this.loadRecentEntries()
		this.loadBreakStatus()
		// Update status every 30 seconds when active
		this.statusUpdateInterval = setInterval(() => {
			if (this.currentStatus.status === 'active' || this.currentStatus.status === 'break') {
				this.loadStatus()
				this.loadBreakStatus()
			}
		}, 30000)
		// Update break status every minute when working
		if (this.currentStatus.status === 'active') {
			this.breakStatusInterval = setInterval(() => {
				this.loadBreakStatus()
			}, 60000)
		}
	},
	beforeUnmount() {
		if (this.statusUpdateInterval) {
			clearInterval(this.statusUpdateInterval)
		}
		if (this.breakStatusInterval) {
			clearInterval(this.breakStatusInterval)
		}
	},
	methods: {
		async loadStatus() {
			try {
				const response = await axios.get(generateUrl('/apps/arbeitszeitcheck/api/clock/status'))
				if (response.data.success) {
					this.currentStatus = response.data.status
					this.updateTodayStats()
				}
			} catch (error) {
				console.error('Failed to load status:', error)
			}
		},

		async loadRecentEntries() {
			try {
				const response = await axios.get(generateUrl('/apps/arbeitszeitcheck/api/time-entries?limit=10'))
				if (response.data.success) {
					this.recentEntries = response.data.entries || []
				}
			} catch (error) {
				console.error('Failed to load recent entries:', error)
			}
		},

		async loadBreakStatus() {
			try {
				const response = await axios.get(generateUrl('/apps/arbeitszeitcheck/api/break/status'))
				if (response.data.success) {
					this.breakStatus = response.data.breakStatus
				}
			} catch (error) {
				console.error('Failed to load break status:', error)
			}
		},

		async clockIn() {
			this.isLoading = true
			try {
				const response = await axios.post(generateUrl('/apps/arbeitszeitcheck/api/clock/in'))
				if (response.data.success) {
					this.currentStatus = {
						status: 'active',
						current_entry: response.data.timeEntry,
						working_today_hours: this.currentStatus.working_today_hours,
						current_session_duration: 0
					}
					this.updateTodayStats()
					this.showNotification(this.$t('arbeitszeitcheck', 'Successfully clocked in'), 'success')
				}
			} catch (error) {
				const userMessage = getUserFriendlyError(error, this.$t.bind(this))
				this.showNotification(userMessage, 'error')
			} finally {
				this.isLoading = false
			}
		},

		async clockOut() {
			this.isLoading = true
			try {
				const response = await axios.post(generateUrl('/apps/arbeitszeitcheck/api/clock/out'))
				if (response.data.success) {
					this.currentStatus = {
						status: 'clocked_out',
						current_entry: null,
						working_today_hours: this.currentStatus.working_today_hours,
						current_session_duration: null
					}
					this.updateTodayStats()
					this.loadRecentEntries()
					this.showNotification(this.$t('arbeitszeitcheck', 'Successfully clocked out'), 'success')
				}
			} catch (error) {
				this.showNotification(error.response?.data?.error || this.$t('arbeitszeitcheck', 'Failed to clock out'), 'error')
			} finally {
				this.isLoading = false
			}
		},

		async startBreak() {
			this.isLoading = true
			try {
				const response = await axios.post(generateUrl('/apps/arbeitszeitcheck/api/break/start'))
				if (response.data.success) {
					this.currentStatus.status = 'break'
					this.currentStatus.current_entry = response.data.timeEntry
					this.loadBreakStatus()
					this.showNotification(this.$t('arbeitszeitcheck', 'Break started'), 'info')
				}
			} catch (error) {
				const userMessage = getUserFriendlyError(error, this.$t.bind(this))
				this.showNotification(userMessage, 'error')
			} finally {
				this.isLoading = false
			}
		},

		async endBreak() {
			this.isLoading = true
			try {
				const response = await axios.post(generateUrl('/apps/arbeitszeitcheck/api/break/end'))
				if (response.data.success) {
					this.currentStatus.status = 'active'
					this.currentStatus.current_entry = response.data.timeEntry
					this.loadBreakStatus()
					this.showNotification(this.$t('arbeitszeitcheck', 'Break ended'), 'info')
				}
			} catch (error) {
				const userMessage = getUserFriendlyError(error, this.$t.bind(this))
				this.showNotification(userMessage, 'error')
			} finally {
				this.isLoading = false
			}
		},

		updateTodayStats() {
			this.todayStats.workingHours = this.currentStatus.working_today_hours || 0
			this.todayStats.breakTime = 0
			this.todayStats.overtime = Math.max(0, this.todayStats.workingHours - 8)
			this.todayStats.complianceStatus = this.todayStats.workingHours <= 10 ? 'good' : 'warning'
		},

		getStatusClass() {
			switch (this.currentStatus.status) {
				case 'active':
					return 'timetracking-status--active'
				case 'break':
					return 'timetracking-status--break'
				default:
					return 'timetracking-status--inactive'
			}
		},

		getStatusText() {
			switch (this.currentStatus.status) {
				case 'active':
					return this.$t('arbeitszeitcheck', 'Working')
				case 'break':
					return this.$t('arbeitszeitcheck', 'On Break')
				default:
					return this.$t('arbeitszeitcheck', 'Clocked Out')
			}
		},

		getEntryStatusClass(status) {
			switch (status) {
				case 'completed':
					return 'timetracking-status--success'
				case 'pending_approval':
					return 'timetracking-status--warning'
				case 'rejected':
					return 'timetracking-status--error'
				default:
					return 'timetracking-status--inactive'
			}
		},

		getEntryStatusText(status) {
			switch (status) {
				case 'completed':
					return this.$t('arbeitszeitcheck', 'Completed')
				case 'pending_approval':
					return this.$t('arbeitszeitcheck', 'Pending Approval')
				case 'rejected':
					return this.$t('arbeitszeitcheck', 'Rejected')
				default:
					return status
			}
		},

		formatDuration(seconds) {
			const hours = Math.floor(seconds / 3600)
			const minutes = Math.floor((seconds % 3600) / 60)
			return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}`
		},

		formatHours(hours) {
			return `${hours.toFixed(2)}h`
		},

		formatDate(dateString) {
			return formatDateGerman(dateString)
		},

		formatTime(dateString) {
			return new Date(dateString).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
		},

		showNotification(message, type) {
			// Use Nextcloud's notification system
			if (typeof OC !== 'undefined' && OC.Notification) {
				OC.Notification.showTemporary(message, {
					timeout: 5000,
					isHTML: false
				})
			} else {
				// Fallback for development
				console.log(`${type}: ${message}`)
			}
		},

		navigateToCompliance() {
			// Navigate to compliance dashboard for detailed view
			this.$router.push({ name: 'ComplianceDashboard' })
		}
	}
}
</script>

<style scoped>
/* Component-specific styles only - most styles are in main.css */

.action-with-explanation {
	padding: 1.5rem;
}

.action-with-explanation button {
	width: 100%;
}

.timetracking-summary-item {
	text-align: center;
	padding: 1.5rem;
}

.timetracking-summary-item__value {
	font-size: 36px;
}

/* Compliance status styles are in main.css */

.help-icon-button {
	background: none;
	border: none;
	cursor: pointer;
	font-size: 14px;
	margin-left: calc(var(--default-grid-baseline) * 0.5);
	padding: 2px 4px;
	border-radius: 50%;
	width: 20px;
	height: 20px;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	vertical-align: middle;
	transition: background-color 0.2s ease;
}

.help-icon-button:hover {
	background-color: var(--color-background-hover);
}

.help-icon-button:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 2px;
}

.compliance-help-text {
	margin-top: calc(var(--default-grid-baseline) * 1);
	padding: calc(var(--default-grid-baseline) * 1);
	background-color: var(--color-background-hover);
	border-radius: var(--border-radius);
	border: 1px solid var(--color-border);
	font-size: 13px;
	color: var(--color-main-text);
	line-height: 1.6;
}

.compliance-help-text p {
	margin: 0 0 calc(var(--default-grid-baseline) * 0.5) 0;
	font-weight: 600;
}

.compliance-help-text ul {
	margin: 0;
	padding-left: calc(var(--default-grid-baseline) * 2);
}

.compliance-help-text li {
	margin-bottom: calc(var(--default-grid-baseline) * 0.5);
}

/* Break warning variant styles */
.break-warning--info {
	border-color: var(--color-primary);
	background-color: rgba(var(--color-primary-rgb), 0.1);
}

.break-warning--warning {
	border-color: var(--color-warning);
	background-color: rgba(var(--color-warning-rgb), 0.1);
}

.break-warning--critical {
	border-color: var(--color-error);
	background-color: rgba(var(--color-error-rgb), 0.1);
}

@media (max-width: 768px) {
	.timetracking-clock-buttons {
		flex-direction: column;
		gap: calc(var(--default-grid-baseline) * 1.5);
	}

	.timetracking-clock-buttons .timetracking-btn,
	.timetracking-clock-buttons .button-vue {
		width: 100%;
	}

	.action-with-explanation {
		width: 100%;
	}

	.compliance-status-display {
		flex-direction: column;
		gap: calc(var(--default-grid-baseline) * 0.25);
	}

	.break-warning {
		flex-direction: column;
		align-items: flex-start;
	}

	.break-warning__icon {
		align-self: center;
	}
}
</style>