import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const root = join(dirname(fileURLToPath(import.meta.url)), '../..');

describe('admin premium policy JS contract (Phase D)', () => {
	const js = readFileSync(join(root, 'js/admin-notifications.js'), 'utf8');
	const tpl = readFileSync(join(root, 'templates/partials/admin-policy-hour-premiums.php'), 'utf8');
	const overtimePage = readFileSync(join(root, 'templates/admin-overtime-settings.php'), 'utf8');

	it('collects editable night window, stacking, and holiday policy', () => {
		expect(js).toContain('#premium-night-start');
		expect(js).toContain('#premium-night-end');
		expect(js).toContain('#premium-stacking');
		expect(js).toContain('#premium-holiday-policy');
		expect(js).toContain('holiday_policy');
		expect(js).toMatch(/stacking:\s*stacking/);
		expect(js).toContain("window_start: nightStart");
		expect(js).toContain("window_end: nightEnd");
	});

	it('presets write AT/DE night windows into the form fields', () => {
		expect(js).toContain("nightStart: '22:00'");
		expect(js).toContain("nightEnd: '05:00'");
		expect(js).toContain("nightStart: '23:00'");
		expect(js).toContain("nightEnd: '06:00'");
		expect(js).toContain('nightStart.value = r.nightStart');
		expect(js).toContain('nightEnd.value = r.nightEnd');
	});

	it('template exposes accessible night and overlap controls', () => {
		expect(tpl).toContain('id="premium-night-start"');
		expect(tpl).toContain('id="premium-night-end"');
		expect(tpl).toContain('id="premium-stacking"');
		expect(tpl).toContain('id="premium-holiday-policy"');
		expect(tpl).toContain('type="time"');
		expect(tpl).toContain('aria-labelledby="premium-night-window-heading"');
		expect(tpl).toContain('aria-labelledby="premium-rules-heading"');
		expect(tpl).toContain('id="premium-more-options"');
		// Legal-safe wording — no false “gesetzlich” claim on premiums.
		expect(tpl).not.toMatch(/gesetzliche Überstunden/i);
		expect(tpl).toContain('not a legal guarantee');
		expect(overtimePage).toContain('admin-policy-hour-premiums.php');
		expect(overtimePage).toContain('data-policy-scope="overtime"');
	});
});
