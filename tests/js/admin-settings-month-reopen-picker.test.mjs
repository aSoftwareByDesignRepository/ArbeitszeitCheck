/**
 * Month-reopen picker must not fire on single-topic admin settings pages.
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */
import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'

const root = join(dirname(fileURLToPath(import.meta.url)), '../..')
const adminSettingsJs = readFileSync(join(root, 'js/admin-settings.js'), 'utf8')
const outlookJs = readFileSync(join(root, 'js/admin-outlook-ical-subscription.js'), 'utf8')

describe('admin-settings month reopen picker guard', () => {
	it('skips init when month-closure reopen controls are absent', () => {
		expect(adminSettingsJs).toMatch(/#monthClosureReopenUserSearch/)
		expect(adminSettingsJs).toMatch(/#monthClosureReopenUserId/)
		expect(adminSettingsJs).toMatch(/#monthClosureReopenUserListbox/)
		expect(adminSettingsJs).toMatch(/if \(!search \|\| !hidden \|\| !list\)/)
		expect(adminSettingsJs).toMatch(/skip quietly instead of surfacing a misleading user-search error/)
	})

	it('uses outlook-specific load-failure copy instead of generic user search error', () => {
		expect(outlookJs).toMatch(/outlookTeamLoadFailed/)
		expect(outlookJs).not.toMatch(/l10n\.searchError \|\| 'Failed to load teams\.'/)
	})

	it('requires scope and calendar language before creating a link', () => {
		expect(outlookJs).toMatch(/#outlookIcalFeedLanguage/)
		expect(outlookJs).toMatch(/#outlookIcalCreateBtn/)
		expect(outlookJs).toMatch(/languageCode/)
		expect(outlookJs).toMatch(/hasFeedLanguageSelected/)
		expect(outlookJs).toMatch(/effectiveSearchTerm/)
		expect(outlookJs).not.toMatch(/clearManagerSelection/)
	})
})
