<?php

declare(strict_types=1);

$appRoot = dirname(__DIR__, 2);
$phpunit = $appRoot . '/vendor/bin/phpunit';
if (!is_file($phpunit)) {
	$phpunit = 'phpunit';
}

$mutations = [
	[
		'file' => $appRoot . '/lib/Service/PermissionService.php',
		'from' => "\t\tif (\$this->groupManager->isAdmin(\$userId)) {\n\t\t\treturn true;\n\t\t}",
		'to' => "\t\tif (false && \$this->groupManager->isAdmin(\$userId)) {\n\t\t\treturn true;\n\t\t}",
		'label' => 'drops_system_admin_or',
	],
	[
		'file' => $appRoot . '/lib/Service/PermissionService.php',
		'from' => 'return in_array($userId, $this->getConfiguredAppAdminUserIds(), true);',
		'to' => 'return false;',
		'label' => 'ignores_dedicated_list',
	],
	[
		'file' => $appRoot . '/templates/admin-settings.php',
		'from' => 'without making them a Nextcloud admin',
		'to' => 'Only Nextcloud admins may administer',
		'label' => 'reverts_help_copy',
	],
	[
		'file' => $appRoot . '/js/admin-vacation-layers.js',
		'from' => "const userId = simSelectedUserId || '';",
		'to' => "const userId = simSelectedUserId || typedValue;",
		'label' => 'vacation_sim_allows_freetext_uid',
	],
	[
		'file' => $appRoot . '/js/admin-vacation-layers.js',
		'from' => 'Please pick an employee from the suggestions first.',
		'to' => 'type a UID exactly',
		'label' => 'vacation_sim_error_reverts_to_uid_hint',
	],
];

function run_phpunit(string $phpunit, string $appRoot): int
{
	$cmd = escapeshellarg($phpunit)
		. ' -c ' . escapeshellarg($appRoot . '/phpunit.unit.xml')
		. ' --filter "DedicatedAppAdminContractTest|PermissionServiceTest::testIsAdminUsesSystemAdminOrDedicatedList|PermissionServiceTest::testNonListedSystemAdminRemainsAppAdminWhenListNonEmpty"';
	passthru($cmd, $code);
	return (int)$code;
}

$config = $appRoot . '/phpunit.unit.xml';
if (!is_file($config)) {
	$config = $appRoot . '/phpunit.xml';
}

echo "Baseline…\n";
$cmd = escapeshellarg($phpunit) . ' -c ' . escapeshellarg($config)
	. ' --filter "DedicatedAppAdminContractTest|testIsAdminUsesSystemAdminOrDedicatedList|testNonListedSystemAdminRemainsAppAdminWhenListNonEmpty|testUpdateAdminSettingsNormalizesAppAdminUsers"';
passthru($cmd, $base);
if ($base !== 0) {
	fwrite(STDERR, "Baseline failed\n");
	exit(1);
}

$failed = 0;
$killed = 0;
foreach ($mutations as $m) {
	$original = (string)file_get_contents($m['file']);
	if (!str_contains($original, $m['from'])) {
		fwrite(STDERR, "SKIP missing: {$m['label']}\n");
		$failed++;
		continue;
	}
	file_put_contents($m['file'], str_replace($m['from'], $m['to'], $original));
	echo "Mutant {$m['label']}…\n";
	passthru($cmd, $code);
	file_put_contents($m['file'], $original);
	if ($code === 0) {
		fwrite(STDERR, "SURVIVED: {$m['label']}\n");
		$failed++;
	} else {
		echo "Killed: {$m['label']}\n";
		$killed++;
	}
}
echo "Done: killed={$killed} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
