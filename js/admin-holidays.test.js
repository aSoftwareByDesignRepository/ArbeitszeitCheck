/**
 * @vitest-environment jsdom
 *
 * Unit coverage for DACH helpers on the admin holidays page
 * (country-of-region rule + region <select> rebuild).
 */

import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'

beforeAll(() => {
	window.ArbeitszeitCheckUtils = {
		$: (sel) => document.querySelector(sel),
		on: () => {},
		$$: () => [],
	}
	window.OC = {
		generateUrl: (u) => u,
		requestToken: 'test',
	}
	vi.stubGlobal('fetch', vi.fn(async () => ({
		json: async () => ({ success: true, holidays: [] }),
	})))
})

beforeEach(() => {
	document.body.innerHTML = ''
})

/** @type {typeof window.__ArbeitszeitCheckAdminHolidaysTestables} */
let t

beforeAll(async () => {
	await import('./admin-holidays.js')
	t = window.__ArbeitszeitCheckAdminHolidaysTestables
})

describe('admin-holidays DACH helpers', () => {
	it('exposes testables after load', () => {
		expect(t).toBeTruthy()
		expect(typeof t.countryOfRegion).toBe('function')
		expect(typeof t.rebuildRegionSelect).toBe('function')
		expect(typeof t.parseRegionDataFromDom).toBe('function')
	})

	it('countryOfRegion maps legacy DE codes and prefixed AT/CH codes', () => {
		expect(t.countryOfRegion('NW')).toBe('DE')
		expect(t.countryOfRegion('bw')).toBe('DE')
		expect(t.countryOfRegion('AT-W')).toBe('AT')
		expect(t.countryOfRegion('AT-OOE')).toBe('AT')
		expect(t.countryOfRegion('CH-ZH')).toBe('CH')
		expect(t.countryOfRegion('')).toBe('DE')
	})

	it('rebuildRegionSelect filters to the country and falls back to country default', () => {
		const select = document.createElement('select')
		document.body.appendChild(select)

		const regionData = {
			defaultRegionByCountry: { DE: 'NW', AT: 'AT-W', CH: 'CH-ZH' },
			regionsByCountry: {
				DE: [
					{ code: 'NW', label: 'Nordrhein-Westfalen' },
					{ code: 'BY', label: 'Bayern' },
				],
				AT: [
					{ code: 'AT-W', label: 'Wien' },
					{ code: 'AT-OOE', label: 'Oberösterreich' },
				],
				CH: [
					{ code: 'CH-ZH', label: 'Zurich' },
					{ code: 'CH-BE', label: 'Bern' },
				],
			},
		}

		expect(t.rebuildRegionSelect(select, 'DE', regionData, 'BY')).toBe('BY')
		expect(select.value).toBe('BY')
		expect(Array.from(select.options).map((o) => o.value)).toEqual(['NW', 'BY'])

		expect(t.rebuildRegionSelect(select, 'AT', regionData, 'BY')).toBe('AT-W')
		expect(select.value).toBe('AT-W')
		expect(Array.from(select.options).map((o) => o.value)).toEqual(['AT-W', 'AT-OOE'])

		expect(t.rebuildRegionSelect(select, 'AT', regionData, 'AT-OOE')).toBe('AT-OOE')
		expect(select.value).toBe('AT-OOE')

		expect(t.rebuildRegionSelect(select, 'CH', regionData, 'NW')).toBe('CH-ZH')
		expect(select.value).toBe('CH-ZH')
	})

	it('rebuildRegionSelect is a no-op for empty country lists', () => {
		const select = document.createElement('select')
		select.innerHTML = '<option value="NW">NW</option>'
		document.body.appendChild(select)
		expect(t.rebuildRegionSelect(select, 'XX', { regionsByCountry: {}, defaultRegionByCountry: {} }, 'NW')).toBe('NW')
		expect(select.value).toBe('NW')
	})

	it('parseRegionDataFromDom reads #azc-holidays-region-data', () => {
		document.body.innerHTML = `<script type="application/json" id="azc-holidays-region-data">${JSON.stringify({
			defaultRegionByCountry: { AT: 'AT-W' },
			regionsByCountry: { AT: [{ code: 'AT-W', label: 'Wien' }] },
		})}</script>`
		const parsed = t.parseRegionDataFromDom()
		expect(parsed.defaultRegionByCountry.AT).toBe('AT-W')
		expect(parsed.regionsByCountry.AT).toHaveLength(1)
		expect(parsed.regionsByCountry.AT[0].code).toBe('AT-W')
	})

	it('suggestedKindFromPayload preserves half-day company suggestions', () => {
		expect(t.suggestedKindFromPayload({ kind: 'half' })).toBe('half')
		expect(t.suggestedKindFromPayload({ kind: 'full' })).toBe('full')
		expect(t.suggestedKindFromPayload({})).toBe('full')
		expect(t.suggestedKindFromPayload(null)).toBe('full')
	})
})
