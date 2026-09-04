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
		'from' => "\t\tif (!in_array(\$userId, \$this->getConfiguredAppAdminUserIds(), true)) {\n\t\t\treturn false;\n\t\t}",
		'to' => "\t\tif (true) {\n\t\t\treturn false;\n\t\t}",
		'label' => 'ignores_dedicated_list',
	],
	[
		'file' => $appRoot . '/templates/partials/admin-settings/access.php',
		'from' => 'without making them a Nextcloud admin',
		'to' => 'Only Nextcloud admins may administer',
		'label' => 'reverts_help_copy',
	],
	[
		'file' => $appRoot . '/lib/Controller/AdminController.php',
		'from' => "\t#[NoAdminRequired]\n\t#[NoCSRFRequired]\n\tpublic function dashboard()",
		'to' => "\t#[NoCSRFRequired]\n\tpublic function dashboard()",
		'label' => 'drops_no_admin_required_on_dashboard',
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
		. ' -c ' . escapeshellarg($appRoot . '/phpunit.xml')
		. ' --filter "DedicatedAppAdminContractTest|PermissionServiceTest::testIsAdminUsesSystemAdminOrDedicatedList|PermissionServiceTest::testNonListedSystemAdminRemainsAppAdminWhenListNonEmpty|AppAdminMiddlewareTest"';
	passthru($cmd, $code);
	return (int)$code;
}

$config = $appRoot . '/phpunit.xml';
if (!is_file($config)) {
	$config = $appRoot . '/phpunit.xml';
}

echo "Baseline…\n";
$cmd = escapeshellarg($phpunit) . ' -c ' . escapeshellarg($config)
	. ' --filter "DedicatedAppAdminContractTest|testIsAdminUsesSystemAdminOrDedicatedList|testNonListedSystemAdminRemainsAppAdminWhenListNonEmpty|testUpdateAdminSettingsNormalizesAppAdminUsers|AppAdminMiddlewareTest"';
passthru($cmd, $baseline);
if ((int)$baseline !== 0) {
	fwrite(STDERR, "Baseline failed\n");
	exit(1);
}

foreach ($mutations as $mutation) {
	$file = $mutation['file'];
	$from = $mutation['from'];
	$to = $mutation['to'];
	$label = $mutation['label'];
	$original = file_get_contents($file);
	if ($original === false || !str_contains($original, $from)) {
		fwrite(STDERR, "Mutation source not found: {$label}\n");
		exit(1);
	}
	file_put_contents($file, str_replace($from, $to, $original));
	echo "Mutation {$label}…\n";
	$code = run_phpunit($phpunit, $appRoot);
	file_put_contents($file, $original);
	if ($code === 0) {
		fwrite(STDERR, "Mutation survived (tests should have failed): {$label}\n");
		exit(1);
	}
	echo "killed {$label}\n";
}

echo "All dedicated app-admin mutations killed.\n";
