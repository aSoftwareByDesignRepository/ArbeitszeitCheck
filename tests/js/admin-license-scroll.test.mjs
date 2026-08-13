/**
 * ArbeitszeitCheck admin license + kiosk — scroll-stability contracts.
 * Run: node --test tests/js/admin-license-scroll.test.mjs
 */
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, it } from 'node:test';

const appRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const read = (rel) => readFileSync(path.join(appRoot, rel), 'utf8');

describe('ArbeitszeitCheck license / kiosk scroll stability', () => {
	it('admin-license never steals scroll on feedback', () => {
		const js = read('js/admin-license.js');
		assert.doesNotMatch(js, /feedback\.scrollIntoView/);
		assert.match(js, /Never scrollIntoView here/);
		assert.match(js, /userSearch\.focus\(\)/);
	});

	it('admin-kiosk never steals scroll on feedback', () => {
		const js = read('js/admin-kiosk.js');
		assert.doesNotMatch(js, /feedback\.scrollIntoView/);
		assert.match(js, /Never scrollIntoView here/);
	});

	it('admin-settings live region does not scrollIntoView', () => {
		const js = read('js/admin-settings.js');
		assert.doesNotMatch(js, /liveRegion\.scrollIntoView/);
	});

	it('admin-notifications live region does not scrollIntoView', () => {
		const js = read('js/admin-notifications.js');
		assert.doesNotMatch(js, /liveRegion\.scrollIntoView/);
	});
});
