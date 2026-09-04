<?php
/**
 * Mutation suite: ProjectCheck connection uses instance install + template scope.
 * Run: php tests/Mutation/run-projectcheck-connection-mutations.php
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

$service = (string)file_get_contents($root . '/lib/Service/ProjectCheckIntegrationService.php');
$labor = (string)file_get_contents($root . '/lib/Service/ProjectCheckLaborTimeSyncService.php');
$admin = (string)file_get_contents($root . '/lib/Controller/AdminController.php');
$settings = (string)file_get_contents($root . '/lib/Settings/AdminSettings.php');
$caps = (string)file_get_contents($root . '/lib/Capabilities.php');
$tpl = (string)file_get_contents($root . '/templates/admin-settings.php');
$partial = (string)file_get_contents($root . '/templates/partials/projectcheck-admin-settings-section.php');
$constants = (string)file_get_contents($root . '/lib/Constants.php');

assertTrue(str_contains($constants, 'APP_ID_PROJECTCHECK'), 'APP_ID_PROJECTCHECK constant');
assertTrue(str_contains($service, 'isInstalled(Constants::APP_ID_PROJECTCHECK)'), 'integration uses isInstalled');
assertTrue(!str_contains($service, "isEnabledForUser('projectcheck')"), 'integration does not use current-user enable for availability');
assertTrue(str_contains($labor, 'isInstalled(Constants::APP_ID_PROJECTCHECK)'), 'labor sync uses isInstalled');
assertTrue(!str_contains($labor, "isEnabledForUser('projectcheck')"), 'labor sync not gated on current user');
assertTrue(str_contains($admin, 'isProjectCheckInstalledOnInstance'), 'admin helper present');
assertTrue(str_contains($admin, 'projectCheckEnabledForCurrentUser'), 'admin passes current-user flag');
assertTrue(str_contains($settings, 'isProjectCheckInstalledOnInstance'), 'NC admin settings uses instance install');
assertTrue(str_contains($caps, 'isInstalled(Constants::APP_ID_PROJECTCHECK)'), 'capabilities uses isInstalled');
assertTrue(str_contains($tpl, '$projectCheckAvailable = !empty($_[\'projectCheckAvailable\'])'), 'dispatcher extracts availability');
assertTrue(preg_match('/\$includeSection = static function \(string \$slug\) use \([^)]*\$projectCheckAvailable/s', $tpl) === 1, 'include closure imports $projectCheckAvailable');
assertTrue(str_contains($partial, "\$calloutId = 'azc-projectcheck-app-required'"), 'missing-app callout id');
assertTrue(str_contains($partial, "\$calloutId = 'azc-projectcheck-group-limited'"), 'group-limited callout id');
assertTrue(str_contains($partial, 'id="projectCheckIntegrationEnabled"'), 'connection switch id');
assertTrue(str_contains($partial, 'You can still turn this connection on.'), 'group-limited copy keeps the switch usable');

$mutTpl = str_replace('$projectCheckAvailable,', '$projectCheckAvailableRemoved,', $tpl);
assertTrue(!str_contains($mutTpl, 'use (' . "\n") || !preg_match('/use \([^)]*\$projectCheckAvailable,/s', $mutTpl), 'mutation can drop import');
assertTrue(preg_match('/use \([^)]*\$projectCheckAvailable/s', $tpl) === 1, 'original keeps import');

$mutSvc = str_replace('isInstalled(Constants::APP_ID_PROJECTCHECK)', "isEnabledForUser('projectcheck')", $service);
assertTrue(str_contains($mutSvc, "isEnabledForUser('projectcheck')"), 'mutation reintroduces user check');
assertTrue(str_contains($service, 'isInstalled(Constants::APP_ID_PROJECTCHECK)'), 'original keeps isInstalled');

if ($failures > 0) {
	fwrite(STDERR, "\n$failures mutation contract failure(s)\n");
	exit(1);
}
fwrite(STDOUT, "\nAll ProjectCheck connection mutation contracts passed.\n");
exit(0);
