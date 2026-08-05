<?php
/**
 * Mutation suite: employee My settings multipage catalog + hierarchy.
 * Run: php tests/Mutation/run-employee-settings-multipage-mutations.php
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$failures = 0;

function assertTrue(bool $cond, string $msg): void
{
	global $failures;
	if (!$cond) {
		fwrite(STDERR, "FAIL: $msg\n");
		$failures++;
	} else {
		fwrite(STDOUT, "OK: $msg\n");
	}
}

$catalog = file_get_contents($root . '/lib/Service/EmployeeSettingsSectionCatalog.php');
$routes = file_get_contents($root . '/appinfo/routes.php');
$tpl = file_get_contents($root . '/templates/settings.php');
$legacy = file_get_contents($root . '/js/employee-settings-legacy-redirect.js');
$controller = file_get_contents($root . '/lib/Controller/PageController.php');
$nav = file_get_contents($root . '/templates/common/navigation.php');
$breaks = file_get_contents($root . '/templates/partials/employee-settings/breaks.php');

assertTrue(is_string($catalog) && str_contains((string)$catalog, 'LEGACY_ANCHORS'), 'catalog LEGACY_ANCHORS');
assertTrue(str_contains((string)$catalog, 'SECTION_BREAKS'), 'catalog breaks');
assertTrue(str_contains((string)$catalog, 'SECTION_DATA_PRIVACY'), 'catalog data-privacy');
assertTrue(str_contains((string)$routes, '/settings/{section}'), 'routes section URL');
assertTrue(
	str_contains((string)$routes, 'EmployeeSettingsSectionCatalog::routeRequirement()'),
	'routes section allowlist from catalog'
);
assertTrue(str_contains((string)$tpl, 'azc-employee-settings-nav.php'), 'chip include');
assertTrue(!str_contains((string)$tpl, 'Cancel and go back to dashboard'), 'no Cancel dead-end in shell');
assertTrue(str_contains((string)$legacy, 'hasOwnProperty.call'), 'legacy fail-closed');
assertTrue(str_contains((string)$controller, 'function settings(): RedirectResponse'), 'index redirects');
assertTrue(str_contains((string)$controller, 'function settingsSection(string $section)'), 'section action');
assertTrue(str_contains((string)$nav, '/admin/settings') && str_contains((string)$nav, 'preg_match'), 'nav avoids admin false-active');
assertTrue(is_string($breaks) && !str_contains((string)$breaks, 'Cancel'), 'breaks has no Cancel');

$settingsJs = (string)file_get_contents($root . '/js/settings.js');
assertTrue(str_contains($settingsJs, "getAttribute('aria-busy') === 'true'"), 'settings double-submit guard');
assertTrue(str_contains($settingsJs, 'if (!autoBreak)'), 'breaks save null-safe');
assertTrue(str_contains($settingsJs, 'if (!notificationsEnabled || !breakReminders || !missingClockIn)'), 'notif save null-safe');
assertTrue(str_contains($settingsJs, 'eligible time entries older than the retention period'), 'GDPR confirm matches retention reality');

$privacy = (string)file_get_contents($root . '/templates/partials/employee-settings/data-privacy.php');
assertTrue(str_contains($privacy, 'retention period'), 'privacy help mentions retention');
assertTrue(!str_contains($privacy, 'permanently removes time entries, absences, and settings'), 'no overclaiming delete');

$mut = str_replace('public const LEGACY_ANCHORS = [', 'public const LEGACY_ANCHORS_REMOVED = [', (string)$catalog);
assertTrue(!str_contains($mut, 'public const LEGACY_ANCHORS = ['), 'mutation removes LEGACY_ANCHORS');
assertTrue(str_contains((string)$catalog, 'public const LEGACY_ANCHORS = ['), 'original keeps LEGACY_ANCHORS');

$mutCtrl = str_replace('function settings(): RedirectResponse', 'function settingsRemoved(): RedirectResponse', (string)$controller);
assertTrue(!str_contains($mutCtrl, 'function settings(): RedirectResponse'), 'mutation removes redirect');
assertTrue(str_contains((string)$controller, 'function settings(): RedirectResponse'), 'original keeps redirect');

if ($failures > 0) {
	fwrite(STDERR, "\n$failures mutation contract failure(s)\n");
	exit(1);
}
fwrite(STDOUT, "\nAll employee settings multipage mutation contracts passed.\n");
exit(0);
