<?php
/**
 * Mutation contract for Outlook iCal subscription feature wiring.
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

$catalog = file_get_contents($root . '/lib/Service/AdminSettingsSectionCatalog.php');
$routes = file_get_contents($root . '/appinfo/routes.php');
$controller = file_get_contents($root . '/lib/Controller/OutlookIcalSubscriptionController.php');
$service = file_get_contents($root . '/lib/Service/OutlookIcalSubscriptionService.php');
$feedService = file_get_contents($root . '/lib/Service/OutlookIcalSubscriptionFeedService.php');
$template = file_get_contents($root . '/templates/partials/admin-settings/outlook-ical-subscription.php');
$js = file_get_contents($root . '/js/admin-outlook-ical-subscription.js');
$adminSettingsJs = file_get_contents($root . '/js/admin-settings.js');

assertTrue(str_contains((string)$catalog, "SECTION_OUTLOOK_SUBSCRIPTION = 'outlook-subscription'"), 'catalog exposes outlook section');
assertTrue(str_contains((string)$routes, '/api/admin/outlook-ical/rotate'), 'routes expose rotate endpoint');
assertTrue(str_contains((string)$service, 'findForTeamScope'), 'service upserts scope token inside transaction');
assertTrue(str_contains((string)$template, 'id="outlookIcalGenerateBtn"'), 'template exposes generate CTA');
assertTrue(!str_contains((string)$template, 'outlookIcalManagerSearch'), 'template no longer asks for manager picker');
assertTrue(str_contains((string)$template, 'Revoke & rotate'), 'template exposes rotate CTA');
assertTrue(str_contains((string)$template, 'data-outlook-teams-url'), 'template exposes teams API URL for JS bootstrap');
assertTrue(str_contains((string)$service, 'listAppAccessUserIds'), 'service supports org-wide employee scope');
assertTrue(str_contains((string)$service, 'feedLanguageCode'), 'service persists explicit feed language on token');
assertTrue(str_contains((string)$service, 'OutlookIcalSubscriptionLanguageCatalog'), 'service validates feed language whitelist');
assertTrue(str_contains((string)$template, 'id="outlookIcalFeedLanguage"'), 'template exposes calendar language selector');
assertTrue(str_contains((string)$service, 'OUTLOOK_ICAL_ORG_WIDE_TEAM_ID'), 'service defines org-wide scope constant');
assertTrue(str_contains((string)$template, 'data-org-wide-available'), 'template exposes org-wide scope');
assertTrue(str_contains((string)$js, 'prefetchOrgWide'), 'js preselects org-wide scope for small teams');
assertTrue(str_contains((string)$feedService, 'buildEventTitle'), 'feed uses thunderbird-safe event title builder');
assertTrue(str_contains((string)$feedService, 'LOCATION:'), 'feed duplicates employee name in LOCATION for calendar clients');
assertTrue(str_contains((string)$template, 'Each event title shows the employee name'), 'template explains event title format');
assertTrue(str_contains((string)$js, 'outlookTeamLoadFailed'), 'js uses team-specific load failure copy');
assertTrue(str_contains((string)$adminSettingsJs, '#monthClosureReopenUserSearch'), 'admin-settings references month reopen search');
assertTrue(str_contains((string)$adminSettingsJs, 'if (!search || !hidden || !list)'), 'admin-settings skips month reopen picker when controls absent');
assertTrue(str_contains((string)$controller, '#[NoAdminRequired]'), 'outlook admin endpoints allow delegated app admins through NC middleware');

$phpunit = is_file($root . '/vendor/bin/phpunit') ? $root . '/vendor/bin/phpunit' : 'phpunit';

function runOutlookMutationFilter(string $root, string $phpunit, string $filter): int
{
	$cmd = 'cd ' . escapeshellarg($root)
		. ' && ' . escapeshellarg(PHP_BINARY)
		. ' -d opcache.enable_cli=0 -d opcache.enable=0 '
		. escapeshellarg($phpunit)
		. ' -c ' . escapeshellarg($root . '/phpunit.xml')
		. ' tests/Unit/Service/OutlookIcalSubscriptionServiceTest.php'
		. ' --filter ' . escapeshellarg($filter)
		. ' > /dev/null 2>&1';
	exec($cmd, $output, $code);
	return (int)$code;
}

function restoreOutlookMutationFile(string $path, string $backup): void
{
	if (is_file($backup)) {
		rename($backup, $path);
	}
}

$servicePath = $root . '/lib/Service/OutlookIcalSubscriptionService.php';
$serviceOriginal = file_get_contents($servicePath);
if ($serviceOriginal === false) {
	fwrite(STDERR, "Cannot read service file\n");
	exit(1);
}

$baseline = runOutlookMutationFilter($root, $phpunit, 'testRotateTokenUpdatesExistingScopeRowInPlace');
if ($baseline !== 0) {
	fwrite(STDERR, "Baseline upsert unit test must pass before mutation run\n");
	exit(1);
}

$anchor = "\$this->tokenMapper->update(\$existing);";
if (!str_contains($serviceOriginal, $anchor)) {
	fwrite(STDERR, "Mutation anchor not found for in-place rotation\n");
	exit(1);
}

$backup = $servicePath . '.mutation-bak';
file_put_contents($backup, $serviceOriginal);
file_put_contents($servicePath, str_replace($anchor, '// mutation: removed in-place update', $serviceOriginal));

echo "\n== mutation: drop_in_place_scope_update ==\n";
$mutated = runOutlookMutationFilter($root, $phpunit, 'testRotateTokenUpdatesExistingScopeRowInPlace');
restoreOutlookMutationFile($servicePath, $backup);

if ($mutated === 0) {
	fwrite(STDERR, "MUTATION SURVIVED: drop_in_place_scope_update\n");
	exit(1);
}
fwrite(STDOUT, "killed drop_in_place_scope_update\n");

if ($failures > 0) {
	fwrite(STDERR, "\n$failures mutation contract failure(s)\n");
	exit(1);
}

fwrite(STDOUT, "\nAll Outlook iCal subscription mutation contracts passed.\n");
exit(0);
