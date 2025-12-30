/**
 * Basic JavaScript tests for the dashboard
 */

import { mount } from '@vue/test-utils'
import { createApp } from 'vue'
import Dashboard from '../src/views/Dashboard.vue'

// Mock Nextcloud dependencies
jest.mock('@nextcloud/l10n', () => ({
	translate: (app, key) => key,
	translatePlural: (app, key, count) => key
}))

jest.mock('@nextcloud/axios', () => ({
	get: jest.fn(() => Promise.resolve({ data: { success: true, status: { status: 'clocked_out' } } })),
	post: jest.fn(() => Promise.resolve({ data: { success: true } }))
}))

jest.mock('@nextcloud/router', () => ({
	generateUrl: (url) => url
}))

describe('Dashboard Component', () => {
	let wrapper

	beforeEach(() => {
		wrapper = mount(Dashboard, {
			global: {
				mocks: {
					$t: (key) => key,
					$n: (key, count) => key
				}
			}
		})
	})

	afterEach(() => {
		wrapper.unmount()
	})

	test('renders correctly', () => {
		expect(wrapper.exists()).toBe(true)
		expect(wrapper.find('.timetracking-dashboard').exists()).toBe(true)
	})

	test('has clock buttons', () => {
		expect(wrapper.find('.timetracking-clock-buttons').exists()).toBe(true)
		expect(wrapper.findAll('button').length).toBeGreaterThan(0)
	})

	test('has status display', () => {
		expect(wrapper.find('.timetracking-status-display').exists()).toBe(true)
	})

	test('has today summary', () => {
		expect(wrapper.find('.timetracking-today-summary').exists()).toBe(true)
	})
})