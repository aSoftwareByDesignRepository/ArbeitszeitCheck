import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

/**
 * Desklet clock-in / daily-max / project payload contract.
 * Mutation-sensitive: empty project must omit the key; daily max must disable Kommen.
 */

describe('AzcDeskletActions', () => {
	beforeEach(async () => {
		vi.resetModules()
		delete globalThis.window.AzcDeskletActions
		await import('./desklet-actions.js')
	})

	afterEach(() => {
		delete globalThis.window.AzcDeskletActions
	})

	it('normaliseStatus reads atDailyMaximum and timeCapture flags', () => {
		const api = window.AzcDeskletActions
		const data = api.normaliseStatus({
			status: 'paused',
			workingTodayHours: '8.5',
			currentSessionDuration: '120',
			atDailyMaximum: true,
			timeCapture: { clockStampingEnabled: false, manualTimeEntryEnabled: true },
		})
		expect(data.status).toBe('paused')
		expect(data.workingTodayHours).toBe(8.5)
		expect(data.currentSessionDuration).toBe(120)
		expect(data.atDailyMaximum).toBe(true)
		expect(data.clockStampingEnabled).toBe(false)
		expect(data.manualTimeEntryEnabled).toBe(true)
	})

	it('normaliseStatus defaults safely for null input', () => {
		const data = window.AzcDeskletActions.normaliseStatus(null)
		expect(data).toEqual({
			status: 'clocked_out',
			workingTodayHours: 0,
			currentSessionDuration: 0,
			sessionStartFormatted: '',
			clockStampingEnabled: true,
			manualTimeEntryEnabled: true,
			atDailyMaximum: false,
		})
	})

	it('getEffectiveButtonStates disables clock-in at daily maximum', () => {
		const api = window.AzcDeskletActions
		expect(api.getEffectiveButtonStates('clocked_out', true, true)['dz-clock-in']).toBe(false)
		expect(api.getEffectiveButtonStates('paused', true, true)['dz-clock-in']).toBe(false)
		expect(api.getEffectiveButtonStates('completed', true, true)['dz-clock-in']).toBe(false)
		// Active sessions keep break/clock-out; clock-in stays off either way.
		expect(api.getEffectiveButtonStates('active', true, true)['dz-clock-out']).toBe(true)
		expect(api.getEffectiveButtonStates('clocked_out', true, false)['dz-clock-in']).toBe(true)
	})

	it('getEffectiveButtonStates respects stamping disabled', () => {
		const api = window.AzcDeskletActions
		expect(api.getEffectiveButtonStates('clocked_out', false, false)['dz-clock-in']).toBe(false)
	})

	it('shouldShowDailyMaxNotice only when clock-in would otherwise be relevant', () => {
		const api = window.AzcDeskletActions
		expect(api.shouldShowDailyMaxNotice('clocked_out', true)).toBe(true)
		expect(api.shouldShowDailyMaxNotice('active', true)).toBe(false)
		expect(api.shouldShowDailyMaxNotice('clocked_out', false)).toBe(false)
	})

	it('canShowProjectPicker tracks clock-in availability', () => {
		const api = window.AzcDeskletActions
		expect(api.canShowProjectPicker('clocked_out', true, false)).toBe(true)
		expect(api.canShowProjectPicker('clocked_out', true, true)).toBe(false)
		expect(api.canShowProjectPicker('active', true, false)).toBe(false)
	})

	it('buildClockInJsonBody omits empty project and trims values', () => {
		const api = window.AzcDeskletActions
		expect(api.buildClockInJsonBody('')).toEqual({})
		expect(api.buildClockInJsonBody('   ')).toEqual({})
		expect(api.buildClockInJsonBody(null)).toEqual({})
		expect(api.buildClockInJsonBody(undefined)).toEqual({})
		expect(api.buildClockInJsonBody(' 42 ')).toEqual({ projectCheckProjectId: '42' })
	})

	it('resolveActionErrorMessage prefers result.error then body fields', () => {
		const api = window.AzcDeskletActions
		expect(api.resolveActionErrorMessage({
			error: 'From result',
			data: { error: 'From body', message: 'Msg' },
		})).toBe('From result')
		expect(api.resolveActionErrorMessage({
			error: '',
			data: { error: 'Body error', message: 'Msg' },
		})).toBe('Body error')
		expect(api.resolveActionErrorMessage({
			error: '',
			data: { message: 'Only message' },
		})).toBe('Only message')
		expect(api.resolveActionErrorMessage({ ok: false }, { actionFailed: 'Fallback' }))
			.toBe('Fallback')
	})
})
