<?php

declare(strict_types=1);

/**
 * Mutation gauntlet: admin/manager desklet batch live-status (no N×getStatus).
 *
 * Run: php tests/Mutation/run-admin-desklet-batch-status-mutations.php
 *
 * @copyright Copyright (c) 2026
 * @license AGPL-3.0-or-later
 */

$root = dirname(__DIR__, 2);
$service = (string)file_get_contents($root . '/lib/Service/DashboardWidgetDataService.php');
$mapper = (string)file_get_contents($root . '/lib/Db/TimeEntryMapper.php');

function assertTrue(bool $cond, string $msg): void {
	if (!$cond) {
		fwrite(STDERR, "KILL FAILED: $msg\n");
		exit(1);
	}
	echo "killed: $msg\n";
}

// M1: remove TimeEntryMapper dependency → admin falls back to N×getStatus
assertTrue(
	str_contains($service, 'TimeEntryMapper $timeEntryMapper'),
	'M1 TimeEntryMapper injected into DashboardWidgetDataService'
);

// M2: admin must batch instead of looping getStatus for every scanned user
$adminPos = strpos($service, 'function getAdminWidgetData');
$managerPos = strpos($service, 'function getManagerWidgetData');
assertTrue($adminPos !== false && $managerPos !== false, 'M2 admin+manager methods exist');
$adminBody = substr($service, $adminPos, 3500);
assertTrue(
	str_contains($adminBody, 'findLiveStatusByUserIds'),
	'M2 admin uses findLiveStatusByUserIds'
);
$slicePos = strpos($adminBody, 'array_slice($enabled');
$getStatusPos = strpos($adminBody, '->getStatus(');
assertTrue(
	$slicePos !== false
		&& $getStatusPos !== false
		&& $getStatusPos > $slicePos
		&& str_contains($adminBody, '$enabled[] = $user;'),
	'M2 admin getStatus only after display slice (not on full directory scan)'
);

// M3: manager also batches for full-team summary
$managerBody = substr($service, $managerPos, 2500);
assertTrue(
	str_contains($managerBody, 'findLiveStatusByUserIds'),
	'M3 manager uses findLiveStatusByUserIds'
);

// M4: live-status mapper uses QueryInChunker (Oracle/1000 IN safety)
assertTrue(
	str_contains($mapper, 'function findLiveStatusByUserIds'),
	'M4 findLiveStatusByUserIds exists'
);
$livePos = strpos($mapper, 'function findLiveStatusByUserIds');
$liveBody = substr($mapper, $livePos, 1800);
assertTrue(
	str_contains($liveBody, 'QueryInChunker::in'),
	'M4 live status query uses QueryInChunker'
);

// M5: priority ranking cannot be inverted (paused must not beat active)
assertTrue(
	preg_match('/STATUS_ACTIVE\s*=>\s*3/', $liveBody) === 1
		&& preg_match('/STATUS_BREAK\s*=>\s*2/', $liveBody) === 1
		&& preg_match('/STATUS_PAUSED\s*=>\s*1/', $liveBody) === 1,
	'M5 status priority active>break>paused'
);

// M6: getStatus only for displayed live users (hours enrichment)
assertTrue(
	str_contains($adminBody, "statusKey !== 'clocked_out'")
		|| str_contains($adminBody, '!== \'clocked_out\''),
	'M6 admin enriches hours only for non-clocked-out display rows'
);

echo "\nAll admin-desklet batch-status mutants killed.\n";
exit(0);
