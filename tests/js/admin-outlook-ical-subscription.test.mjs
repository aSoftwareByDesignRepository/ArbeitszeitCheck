/**
 * Outlook iCal subscription admin UI contracts.
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */
import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'

const root = join(dirname(fileURLToPath(import.meta.url)), '../..')
const outlookJs = readFileSync(join(root, 'js/admin-outlook-ical-subscription.js'), 'utf8')
const outlookTemplate = readFileSync(
	join(root, 'templates/partials/admin-settings/outlook-ical-subscription.php'),
	'utf8',
)

describe('admin-outlook-ical-subscription contracts', () => {
	it('shows a loading indicator while subscription rows are fetched', () => {
		expect(outlookTemplate).toMatch(/id="outlookIcalSubscriptionsLoading"/)
		expect(outlookTemplate).toMatch(/class="azc-loading outlook-ical-subscription-table__loading"/)
		expect(outlookTemplate).toMatch(/aria-busy="true"/)
		expect(outlookJs).toMatch(/setSubscriptionsLoading/)
		expect(outlookJs).toMatch(/#outlookIcalSubscriptionsLoading/)
	})

	it('guards against stale active-subscription responses', () => {
		expect(outlookJs).toMatch(/activeSubscriptionsRequestId/)
		expect(outlookJs).toMatch(/requestId !== activeSubscriptionsRequestId/)
	})

	it('surfaces load failures without pretending the list is empty', () => {
		expect(outlookJs).toMatch(/showSubscriptionsLoadError/)
		expect(outlookJs).toMatch(/outlookActiveLoadFailed/)
		expect(outlookJs).toMatch(/resetSubscriptionsEmptyMessage/)
		expect(outlookTemplate).toMatch(/data-empty-text=/)
	})

	it('uses design-system loading, table, and theme-safe contracts', () => {
		expect(outlookTemplate).toMatch(/id="outlookIcalSubscriptionsLoading"/)
		expect(outlookTemplate).toMatch(/class="azc-loading outlook-ical-subscription-table__loading"/)
		expect(outlookTemplate).toMatch(/aria-live="polite"/)
		expect(outlookTemplate).toMatch(/azc-table--responsive/)
		expect(outlookJs).toMatch(/setSubscriptionsLoading/)
		expect(outlookJs).toMatch(/activeSubscriptionsRequestId/)
		expect(outlookJs).toMatch(/showSubscriptionsLoadError/)
	})

	it('does not rely on hardcoded English fallbacks for primary load error', () => {
		expect(outlookJs).toMatch(/l10n\.outlookActiveLoadFailed/)
	})
})
