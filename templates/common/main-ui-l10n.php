<?php

declare(strict_types=1);

use OCA\ArbeitszeitCheck\Support\LaborLawProfileFactory;
use OCA\ArbeitszeitCheck\Support\RegionRegistry;

/**
 * Server-translated strings for js/arbeitszeitcheck-main.js (window.t may be unavailable).
 *
 * @var \OCP\IL10N $l
 */
$l = $l ?? ($_['l'] ?? \OCP\Util::getL10N('arbeitszeitcheck'));

try {
	$azcLawProfile = \OCP\Server::get(LaborLawProfileFactory::class)->getProfile();
} catch (\Throwable) {
	$azcLawProfile = LaborLawProfileFactory::profileForCountry(RegionRegistry::COUNTRY_DE);
}
$azcDailyLaw = $azcLawProfile->lawLabel('daily');
$azcMaxDaily = (int)round($azcLawProfile->dailyMaxHoursDefault);

$criticalClockOut = $l->t(
	'CRITICAL: Maximum daily working hours (%1$dh) exceeded! Automatically clocking out to comply with labour law (%2$s).',
	[$azcMaxDaily, $azcDailyLaw]
);
$approachingMax = $l->t(
	'Note: You are approaching the maximum working hours. Extended hours must be compensated within the averaging window (%s).',
	[$azcDailyLaw]
);
$autoClockedOut = $l->t(
	'Automatically clocked out to comply with labour law (%s).',
	[$azcDailyLaw]
);
$autoClockOutFailed = $l->t(
	'Automatic clock-out (%s) could not be completed. Please clock out manually.',
	[$azcDailyLaw]
);
$autoClockOutGiveUp = $l->t(
	'Automatic clock-out (%s) failed repeatedly. Please clock out manually or contact your administrator.',
	[$azcDailyLaw]
);

$mainUiStringIds = [
	'Are you sure you want to delete this item?',
	'Are you sure you want to delete this time entry?',
	'Time entry deleted successfully',
	'Absence request submitted successfully',
	'Absence request updated',
	'Absence shortened successfully. Your actual last day of absence has been updated.',
	'Absence cancelled successfully.',
	'Are you sure you want to cancel this absence request?',
	'Cancel absence request',
	'Delete time entry',
	'The server returned an unexpected page instead of data. Try reloading the page. If the problem persists, sign in again.',
	'January', 'February', 'March', 'April', 'May', 'June',
	'July', 'August', 'September', 'October', 'November', 'December',
	'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday',
	'Break Time',
	'Public holiday', 'Company holiday', 'Custom holiday',
	'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat',
	'Holiday',
	'Vacation',
	'Sick Leave',
	'Personal Leave',
	'Parental Leave',
	'Special Leave',
	'Unpaid Leave',
	'Home Office',
	'Business Trip',
	'Absence',
	'Clock out and end your working day?',
	'Your time entry will be finalized. To pause and continue working, use "Start Break" instead.',
];

$mainUiStrings = [];
foreach ($mainUiStringIds as $msgid) {
	$mainUiStrings[$msgid] = $l->t($msgid);
}

// Profile-aware clock-out copy, keyed under both new and legacy msgids so
// js/arbeitszeitcheck-main.js keeps working without a coordinated JS rewrite.
$clockOutMap = [
	'CRITICAL: Maximum daily working hours (%1$dh) exceeded! Automatically clocking out to comply with labour law (%2$s).' => $criticalClockOut,
	'CRITICAL: Maximum daily working hours (10h) exceeded! Automatically clocking out to comply with German labor law (ArbZG §3).' => $criticalClockOut,
	'Note: You are approaching the maximum working hours. Extended hours must be compensated within the averaging window (%s).' => $approachingMax,
	'Note: You are approaching the maximum working hours. Extended hours must be compensated within 6 months (ArbZG §3).' => $approachingMax,
	'Automatically clocked out to comply with labour law (%s).' => $autoClockedOut,
	'Automatically clocked out to comply with German labor law (ArbZG §3).' => $autoClockedOut,
	'Automatic clock-out (%s) could not be completed. Please clock out manually.' => $autoClockOutFailed,
	'Automatic clock-out (ArbZG §3) could not be completed. Please clock out manually.' => $autoClockOutFailed,
	'Automatic clock-out (%s) failed repeatedly. Please clock out manually or contact your administrator.' => $autoClockOutGiveUp,
	'Automatic clock-out (ArbZG §3) failed repeatedly. Please clock out manually or contact your administrator.' => $autoClockOutGiveUp,
];
foreach ($clockOutMap as $key => $value) {
	$mainUiStrings[$key] = $value;
}

?>
<script nonce="<?php p($_['cspNonce'] ?? ''); ?>">
window.ArbeitszeitCheck = window.ArbeitszeitCheck || {};
window.ArbeitszeitCheck.mainUiStrings = <?php echo json_encode($mainUiStrings, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
window.ArbeitszeitCheck.lawLabels = <?php echo json_encode([
	'daily' => $azcDailyLaw,
	'breaks' => $azcLawProfile->lawLabel('breaks'),
	'rest' => $azcLawProfile->lawLabel('rest'),
	'maxDailyHours' => $azcMaxDaily,
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
</script>
