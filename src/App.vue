<template>
	<div class="arbeitszeitcheck-app">
		<!-- Mobile Menu Toggle Button -->
		<button
			v-if="isMobile"
			class="arbeitszeitcheck-app__menu-toggle"
			:aria-expanded="sidebarOpen"
			:aria-label="$t('arbeitszeitcheck', 'Toggle navigation menu')"
			@click="toggleSidebar"
		>
			<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
				<line x1="3" y1="6" x2="21" y2="6"/>
				<line x1="3" y1="12" x2="21" y2="12"/>
				<line x1="3" y1="18" x2="21" y2="18"/>
			</svg>
		</button>

		<!-- Mobile Backdrop -->
		<div
			v-if="isMobile && sidebarOpen"
			class="arbeitszeitcheck-app__backdrop"
			@click="closeSidebar"
			@keydown.esc="closeSidebar"
		/>

		<div class="arbeitszeitcheck-app__layout">
			<Navigation
				class="arbeitszeitcheck-app__sidebar"
				:class="{ 'arbeitszeitcheck-sidebar--open': sidebarOpen }"
				@close="closeSidebar"
			/>
			<main class="arbeitszeitcheck-app__content">
				<router-view />
			</main>
		</div>
		<OnboardingTour />
	</div>
</template>

<script>
import { ref, onMounted, onUnmounted } from 'vue'
import Navigation from './components/Navigation.vue'
import OnboardingTour from './components/OnboardingTour.vue'

export default {
	name: 'App',
	components: {
		Navigation,
		OnboardingTour
	},
	setup() {
		const sidebarOpen = ref(false)
		const isMobile = ref(false)

		const checkMobile = () => {
			isMobile.value = window.innerWidth <= 768
			if (!isMobile.value) {
				sidebarOpen.value = false
			}
		}

		const toggleSidebar = () => {
			sidebarOpen.value = !sidebarOpen.value
		}

		const closeSidebar = () => {
			sidebarOpen.value = false
		}

		onMounted(() => {
			checkMobile()
			window.addEventListener('resize', checkMobile)
		})

		onUnmounted(() => {
			window.removeEventListener('resize', checkMobile)
		})

		return {
			sidebarOpen,
			isMobile,
			toggleSidebar,
			closeSidebar
		}
	}
}
</script>

<style scoped>
.arbeitszeitcheck-app {
	min-height: 100vh;
	background-color: var(--color-main-background);
	position: relative;
}

.arbeitszeitcheck-app__layout {
	display: flex;
	min-height: 100vh;
}

.arbeitszeitcheck-app__sidebar {
	flex-shrink: 0;
}

.arbeitszeitcheck-app__content {
	flex: 1;
	background: var(--color-main-background);
	position: relative;
	display: flex;
	flex-direction: column;
	height: 100vh;
	overflow-y: auto;
	overflow-x: hidden;
	width: 100%;
}

/* Router view wrapper - full width */
.arbeitszeitcheck-app__content > * {
	width: 100%;
	flex: 1;
	display: flex;
	flex-direction: column;
	min-height: auto;
	overflow: visible;
	box-sizing: border-box;
}

/* Mobile Menu Toggle Button */
.arbeitszeitcheck-app__menu-toggle {
	display: none;
	position: fixed;
	top: 1rem;
	left: 1rem;
	z-index: 1001;
	background-color: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	padding: 0.5rem;
	cursor: pointer;
	box-shadow: 0 2px 8px var(--color-box-shadow, rgba(0, 0, 0, 0.1));
	min-width: 44px;
	min-height: 44px;
	align-items: center;
	justify-content: center;
	transition: all 0.2s ease;
}

.arbeitszeitcheck-app__menu-toggle:hover {
	background-color: var(--color-background-hover);
}

.arbeitszeitcheck-app__menu-toggle:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 2px;
}

.arbeitszeitcheck-app__menu-toggle svg {
	width: 24px;
	height: 24px;
	color: var(--color-main-text);
}

/* Mobile Backdrop */
.arbeitszeitcheck-app__backdrop {
	position: fixed;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	background-color: var(--color-backdrop, rgba(0, 0, 0, 0.5));
	z-index: 999;
	animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
	from {
		opacity: 0;
	}
	to {
		opacity: 1;
	}
}

/* Optimize animations for 60 FPS */
.arbeitszeitcheck-app__menu-toggle {
	will-change: transform, background-color;
}

.arbeitszeitcheck-app__backdrop {
	will-change: opacity;
}

/* Responsive: Mobile layout */
@media (max-width: 768px) {
	.arbeitszeitcheck-app__menu-toggle {
		display: flex;
	}

	.arbeitszeitcheck-app__content {
		padding: calc(var(--default-grid-baseline) * 0.5);
		padding-top: calc(var(--default-grid-baseline) * 3);
	}
}
</style>