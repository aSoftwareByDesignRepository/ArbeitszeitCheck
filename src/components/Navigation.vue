<template>
	<nav class="arbeitszeitcheck-sidebar" role="navigation" aria-label="Main navigation">
		<!-- Sidebar Header -->
		<div class="arbeitszeitcheck-sidebar__header">
			<div class="arbeitszeitcheck-sidebar__brand">
				<div class="arbeitszeitcheck-sidebar__icon">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="arbeitszeitcheck-sidebar__icon-svg">
						<circle cx="12" cy="12" r="10"/>
						<polyline points="12,6 12,12 16,14"/>
					</svg>
				</div>
				<div class="arbeitszeitcheck-sidebar__info">
					<h3>{{ $t('arbeitszeitcheck', 'ArbeitszeitCheck') }}</h3>
					<p>{{ $t('arbeitszeitcheck', 'Time tracking and compliance') }}</p>
				</div>
			</div>
		</div>

		<!-- Navigation Menu -->
		<ul class="arbeitszeitcheck-sidebar__menu">
			<li>
				<router-link
					to="/"
					class="arbeitszeitcheck-sidebar__link"
					active-class="arbeitszeitcheck-sidebar__link--active"
					:aria-current="$route.path === '/' ? 'page' : undefined"
					:title="$t('arbeitszeitcheck', 'View your time tracking dashboard')">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="arbeitszeitcheck-sidebar__icon-svg" aria-hidden="true">
						<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
						<polyline points="9,22 9,12 15,12 15,22"/>
					</svg>
					<span>{{ $t('arbeitszeitcheck', 'Dashboard') }}</span>
				</router-link>
			</li>
			<li>
				<router-link
					to="/time-entries"
					class="arbeitszeitcheck-sidebar__link"
					active-class="arbeitszeitcheck-sidebar__link--active"
					:aria-current="$route.path === '/time-entries' ? 'page' : undefined"
					:title="$t('arbeitszeitcheck', 'View and manage your time entries')">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="arbeitszeitcheck-sidebar__icon-svg" aria-hidden="true">
						<circle cx="12" cy="12" r="10"/>
						<polyline points="12,6 12,12 16,14"/>
					</svg>
					<span>{{ $t('arbeitszeitcheck', 'Time Entries') }}</span>
				</router-link>
			</li>
			<li>
				<router-link
					to="/absences"
					class="arbeitszeitcheck-sidebar__link"
					active-class="arbeitszeitcheck-sidebar__link--active"
					:aria-current="$route.path === '/absences' ? 'page' : undefined"
					:title="$t('arbeitszeitcheck', 'View and manage your absences')">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="arbeitszeitcheck-sidebar__icon-svg" aria-hidden="true">
						<path d="M4.18 4.18A2 2 0 0 0 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 1.82-1.18"/>
						<path d="M21 15.5V6a2 2 0 0 0-2-2H9.5"/>
						<path d="M16 2v4"/>
						<path d="M3 10h7"/>
						<path d="M21 10h-5.5"/>
						<line x1="2" y1="2" x2="22" y2="22"/>
					</svg>
					<span>{{ $t('arbeitszeitcheck', 'Absences') }}</span>
				</router-link>
			</li>
			<li>
				<router-link
					to="/reports"
					class="arbeitszeitcheck-sidebar__link"
					active-class="arbeitszeitcheck-sidebar__link--active"
					:aria-current="$route.path === '/reports' ? 'page' : undefined"
					:title="$t('arbeitszeitcheck', 'View time tracking reports')">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="arbeitszeitcheck-sidebar__icon-svg" aria-hidden="true">
						<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
						<polyline points="14,2 14,8 20,8"/>
						<line x1="16" y1="13" x2="8" y2="13"/>
						<line x1="16" y1="17" x2="8" y2="17"/>
						<polyline points="10,9 9,9 8,9"/>
					</svg>
					<span>{{ $t('arbeitszeitcheck', 'Reports') }}</span>
				</router-link>
			</li>
			<li>
				<router-link
					to="/calendar"
					class="arbeitszeitcheck-sidebar__link"
					active-class="arbeitszeitcheck-sidebar__link--active"
					:aria-current="$route.path === '/calendar' ? 'page' : undefined"
					:title="$t('arbeitszeitcheck', 'View your time entries in calendar format')">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="arbeitszeitcheck-sidebar__icon-svg" aria-hidden="true">
						<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
						<line x1="16" y1="2" x2="16" y2="6"/>
						<line x1="8" y1="2" x2="8" y2="6"/>
						<line x1="3" y1="10" x2="21" y2="10"/>
					</svg>
					<span>{{ $t('arbeitszeitcheck', 'Calendar') }}</span>
				</router-link>
			</li>
			<li>
				<router-link
					to="/timeline"
					class="arbeitszeitcheck-sidebar__link"
					active-class="arbeitszeitcheck-sidebar__link--active"
					:aria-current="$route.path === '/timeline' ? 'page' : undefined"
					:title="$t('arbeitszeitcheck', 'View your time entries in timeline format')">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="arbeitszeitcheck-sidebar__icon-svg" aria-hidden="true">
						<polyline points="22,12 18,12 15,21 9,3 6,12 2,12"/>
					</svg>
					<span>{{ $t('arbeitszeitcheck', 'Timeline') }}</span>
				</router-link>
			</li>
		</ul>

		<!-- Sidebar Footer with Stats (optional) -->
		<div v-if="stats || fetchStats" class="arbeitszeitcheck-sidebar__footer">
			<div class="arbeitszeitcheck-sidebar__stats">
				<div class="arbeitszeitcheck-sidebar__stat-item" :title="$t('arbeitszeitcheck', 'Total time entries')">
					<span class="arbeitszeitcheck-sidebar__stat-number" :aria-label="$t('arbeitszeitcheck', 'Total time entries: {count}', { count: stats?.timeEntryCount || 0 })">
						{{ isLoadingStats ? '...' : (stats?.timeEntryCount || 0) }}
					</span>
					<span class="arbeitszeitcheck-sidebar__stat-label">{{ $t('arbeitszeitcheck', 'Entries') }}</span>
				</div>
				<div class="arbeitszeitcheck-sidebar__stat-item" :title="$t('arbeitszeitcheck', 'Total absences')">
					<span class="arbeitszeitcheck-sidebar__stat-number" :aria-label="$t('arbeitszeitcheck', 'Total absences: {count}', { count: stats?.absenceCount || 0 })">
						{{ isLoadingStats ? '...' : (stats?.absenceCount || 0) }}
					</span>
					<span class="arbeitszeitcheck-sidebar__stat-label">{{ $t('arbeitszeitcheck', 'Absences') }}</span>
				</div>
			</div>
		</div>
	</nav>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

// Define props for stats (optional - can be passed from parent or fetched)
const props = defineProps({
	stats: {
		type: Object,
		default: null
	},
	fetchStats: {
		type: Boolean,
		default: false
	}
})

// Emit events
const emit = defineEmits(['close'])

const route = useRoute()
const stats = ref(props.stats)
const isLoadingStats = ref(false)

// Fetch stats if enabled
onMounted(async () => {
	if (props.fetchStats && !stats.value) {
		await loadStats()
	}
})

// Close sidebar on mobile when route changes
watch(() => route.path, () => {
	// Check if we're on mobile (window width <= 768px)
	if (window.innerWidth <= 768) {
		emit('close')
	}
})

async function loadStats() {
	isLoadingStats.value = true
	try {
		// Fetch time entry count
		const timeEntriesResponse = await axios.get(generateUrl('/apps/arbeitszeitcheck/api/time-entries'), {
			params: { limit: 1 }
		})
		
		// Fetch absence count
		const absencesResponse = await axios.get(generateUrl('/apps/arbeitszeitcheck/api/absences'), {
			params: { limit: 1 }
		})
		
		stats.value = {
			timeEntryCount: timeEntriesResponse.data?.total || 0,
			absenceCount: absencesResponse.data?.total || 0
		}
	} catch (error) {
		// Silently fail - stats are optional
		console.warn('Failed to load navigation stats:', error)
		stats.value = {
			timeEntryCount: 0,
			absenceCount: 0
		}
	} finally {
		isLoadingStats.value = false
	}
}
</script>

<style scoped>
/* Sidebar container */
.arbeitszeitcheck-sidebar {
	display: flex;
	flex-direction: column;
	width: 260px;
	min-height: 100vh;
	background: linear-gradient(180deg, var(--color-main-background) 0%, var(--color-background-hover) 100%);
	border-right: 2px solid var(--color-border);
	flex-shrink: 0;
	box-shadow: 2px 0 12px var(--color-box-shadow, rgba(0, 0, 0, 0.08));
}

/* Sidebar Header */
.arbeitszeitcheck-sidebar__header {
	padding: 1.5rem 1.25rem;
	border-bottom: 1px solid var(--color-border);
}

.arbeitszeitcheck-sidebar__brand {
	display: flex;
	align-items: center;
	gap: 1rem;
}

.arbeitszeitcheck-sidebar__icon {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 3.5rem;
	height: 3.5rem;
	background: linear-gradient(135deg, var(--color-primary-element) 0%, var(--color-primary-element-hover) 100%);
	border-radius: var(--border-radius-large);
	box-shadow: 0 4px 16px var(--color-box-shadow, rgba(0, 130, 201, 0.3));
	flex-shrink: 0;
	transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.arbeitszeitcheck-sidebar__icon-svg {
	width: 1.75rem;
	height: 1.75rem;
	color: var(--color-primary-element-text);
	stroke-width: 1.5;
}

.arbeitszeitcheck-sidebar__info {
	flex: 1;
	min-width: 0;
}

.arbeitszeitcheck-sidebar__info h3 {
	margin: 0;
	font-size: 1.1rem;
	font-weight: 600;
	color: var(--color-main-text);
	line-height: 1.3;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.arbeitszeitcheck-sidebar__info p {
	margin: 0.25rem 0 0 0;
	font-size: 0.8rem;
	color: var(--color-text-maxcontrast);
	line-height: 1.2;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

/* Navigation Menu */
.arbeitszeitcheck-sidebar__menu {
	list-style: none;
	margin: 0;
	padding: 0.5rem 0;
	flex: 1;
}

.arbeitszeitcheck-sidebar__menu li {
	margin: 0;
	padding: 0;
}

.arbeitszeitcheck-sidebar__link {
	display: flex;
	align-items: center;
	gap: 0.875rem;
	padding: 1rem 1.5rem;
	text-decoration: none;
	color: var(--color-main-text);
	border-radius: var(--border-radius-large);
	margin: 0.25rem 0.75rem;
	transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
	font-weight: 600;
	font-size: 0.95rem;
	line-height: 1.3;
	min-height: 48px;
	will-change: background-color, transform;
	position: relative;
	overflow: hidden;
}

.arbeitszeitcheck-sidebar__link::before {
	content: '';
	position: absolute;
	left: 0;
	top: 0;
	bottom: 0;
	width: 4px;
	background: linear-gradient(180deg, var(--color-primary-element) 0%, var(--color-primary-element-hover) 100%);
	transform: scaleY(0);
	transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
	transform-origin: bottom;
}

.arbeitszeitcheck-sidebar__link:hover::before,
.arbeitszeitcheck-sidebar__link--active::before {
	transform: scaleY(1);
	transform-origin: top;
}

.arbeitszeitcheck-sidebar__link:hover,
.arbeitszeitcheck-sidebar__link:focus {
	background: linear-gradient(90deg, var(--color-background-hover) 0%, var(--color-background-dark) 100%);
	transform: translateX(4px);
	outline: 2px solid var(--color-primary-element);
	outline-offset: 2px;
	box-shadow: 0 2px 8px var(--color-box-shadow, rgba(0, 0, 0, 0.1));
}

.arbeitszeitcheck-sidebar__link--active {
	background: linear-gradient(90deg, var(--color-primary-element-light) 0%, var(--color-background-hover) 100%);
	font-weight: 700;
	box-shadow: 0 2px 8px var(--color-box-shadow, rgba(0, 130, 201, 0.2));
}

.arbeitszeitcheck-sidebar__link--active .arbeitszeitcheck-sidebar__icon-svg {
	color: var(--color-primary-element);
}

.arbeitszeitcheck-sidebar__link .arbeitszeitcheck-sidebar__icon-svg {
	width: 1.125rem;
	height: 1.125rem;
	flex-shrink: 0;
	stroke-width: 1.75;
	opacity: 0.9;
	color: var(--color-text-maxcontrast);
	transition: opacity 0.2s cubic-bezier(0.4, 0, 0.2, 1),
		color 0.2s cubic-bezier(0.4, 0, 0.2, 1),
		transform 0.15s cubic-bezier(0.4, 0, 0.2, 1);
}

.arbeitszeitcheck-sidebar__link:hover .arbeitszeitcheck-sidebar__icon-svg {
	transform: scale(1.05);
}

.arbeitszeitcheck-sidebar__link--active .arbeitszeitcheck-sidebar__icon-svg {
	opacity: 1;
}

.arbeitszeitcheck-sidebar__link span {
	flex: 1;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

/* Sidebar Footer */
.arbeitszeitcheck-sidebar__footer {
	margin-top: auto;
	padding: 1rem 1.25rem;
	border-top: 1px solid var(--color-border);
}

.arbeitszeitcheck-sidebar__stats {
	display: flex;
	gap: 1rem;
	justify-content: space-between;
}

.arbeitszeitcheck-sidebar__stat-item {
	text-align: center;
	flex: 1;
	padding: calc(var(--default-grid-baseline) * 1);
	border-radius: var(--border-radius);
	background-color: var(--color-main-background);
	transition: all 0.3s ease;
}

.arbeitszeitcheck-sidebar__stat-item:hover {
	background-color: var(--color-background-hover);
	transform: scale(1.05);
}

.arbeitszeitcheck-sidebar__stat-number {
	display: block;
	font-size: 1.3rem;
	font-weight: 800;
	color: var(--color-primary-element);
	line-height: 1.2;
	letter-spacing: -0.02em;
}

.arbeitszeitcheck-sidebar__stat-label {
	display: block;
	font-size: 0.75rem;
	color: var(--color-text-maxcontrast);
	margin-top: 0.25rem;
	line-height: 1.2;
}

/* Responsive: Mobile sidebar overlay */
@media (max-width: 768px) {
	.arbeitszeitcheck-sidebar {
		position: fixed;
		left: 0;
		top: 0;
		z-index: 1000;
		transform: translateX(-100%);
		transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
		box-shadow: 2px 0 12px var(--color-box-shadow, rgba(0, 0, 0, 0.15));
		height: 100vh;
		overflow-y: auto;
		will-change: transform;
	}

	.arbeitszeitcheck-sidebar--open {
		transform: translateX(0);
	}
}

/* Accessibility: Focus indicators */
.arbeitszeitcheck-sidebar__link:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 2px;
	background-color: var(--color-background-hover);
}

/* Smooth icon container transitions */
.arbeitszeitcheck-sidebar__icon {
	transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1),
		box-shadow 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.arbeitszeitcheck-sidebar__brand:hover .arbeitszeitcheck-sidebar__icon {
	transform: scale(1.05);
	box-shadow: 0 6px 16px var(--color-box-shadow, rgba(0, 0, 0, 0.2));
}

/* Dark theme support (automatic with CSS variables) */
@media (prefers-color-scheme: dark) {
	.arbeitszeitcheck-sidebar {
		background-color: var(--color-main-background);
	}
}
</style>
