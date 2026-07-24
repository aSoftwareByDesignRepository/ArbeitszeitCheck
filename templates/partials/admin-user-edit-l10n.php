<?php

declare(strict_types=1);

/**
 * Shared holiday-region data + edit-employee l10n (list + detail pages).
 *
 * Expects $l (\OCP\IL10N). Optionally $holidayRegionContext
 * (['country' => 'DE', 'defaultRegion' => 'NW']) from the controller.
 * Regions come from RegionRegistry — do not hardcode lists here (B-4).
 */

use OCA\ArbeitszeitCheck\Support\RegionRegistry;
use OCA\ArbeitszeitCheck\Util\TemplateL10n;

$holidayRegionContext = (isset($holidayRegionContext) && is_array($holidayRegionContext))
	? $holidayRegionContext
	: ['country' => RegionRegistry::COUNTRY_DE, 'defaultRegion' => RegionRegistry::defaultRegionForCountry(RegionRegistry::COUNTRY_DE)];

$holidayStatesForJs = [];
$holidayRegionGroupsForJs = [];
foreach (RegionRegistry::supportedCountries() as $regionCountry) {
	$group = [
		'country' => $regionCountry,
		'countryLabel' => $l->t(RegionRegistry::countryLabels()[$regionCountry] ?? $regionCountry),
		'regions' => [],
	];
	foreach (RegionRegistry::regionsForCountry($regionCountry) as $code => $name) {
		$entry = ['code' => $code, 'label' => $l->t($name)];
		$group['regions'][] = $entry;
		$holidayStatesForJs[] = $entry;
	}
	$holidayRegionGroupsForJs[] = $group;
}

$holidayRegionContextForJs = [
	'country' => $holidayRegionContext['country'],
	'defaultRegion' => $holidayRegionContext['defaultRegion'],
	'defaultRegionLabel' => $l->t(RegionRegistry::regionLabel((string)$holidayRegionContext['defaultRegion'])),
];
?>
window.ArbeitszeitCheck = window.ArbeitszeitCheck || {};
    window.ArbeitszeitCheck.l10n = window.ArbeitszeitCheck.l10n || {};
    window.ArbeitszeitCheck.l10n.editUser = <?php echo json_encode($l->t('Edit User'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.save = <?php echo json_encode($l->t('Save'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.cancel = <?php echo json_encode($l->t('Cancel'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.workingTimeModel = <?php echo json_encode($l->t('Working Time Model'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.vacationDaysPerYear = <?php echo json_encode($l->t('Vacation Days Per Year'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.startDate = <?php echo json_encode($l->t('Start Date'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.endDate = <?php echo json_encode($l->t('End Date (Optional)'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.noModel = <?php echo json_encode($l->t('No Model Assigned'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.selectWorkScheduleHelp = <?php echo json_encode($l->t('Select a work schedule to assign to this employee'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.vacationDaysHelp = <?php echo json_encode($l->t('Number of vacation days per year (standard in Germany: 25 days)'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.vacationCarryoverLabel = <?php echo json_encode($l->t('Vacation carryover (opening balance)'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.vacationCarryoverHelp = <?php echo json_encode($l->t('Opening balance of carryover days for the selected calendar year (Resturlaub), e.g. from HR or migration. This is not the annual vacation entitlement from the working time model. The last day carryover can be used is set globally in Admin settings.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.vacationCarryoverYearLabel = <?php echo json_encode($l->t('Year for carryover balance'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.vacationCarryoverYearHelp = <?php echo json_encode($l->t('The calendar year this opening balance applies to (same year as in employees’ vacation statistics—usually the current year). When a new year starts or after migrating from another system, set the Resturlaub opening balance for that year here or use the CSV import command; the app does not roll balances forward automatically.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.endDateHelp = <?php echo json_encode($l->t('Leave empty if the assignment has no end date'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.endDateOptional = <?php echo json_encode($l->t('End Date (Optional)'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.userUpdated = <?php echo json_encode($l->t('User updated successfully'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.error = <?php echo json_encode($l->t('An error occurred'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.currentAssignment = <?php echo json_encode($l->t('Current assignment'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.changeAssignment = <?php echo json_encode($l->t('Change assignment'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.assignmentHistory = <?php echo json_encode($l->t('Assignment history'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.noAssignmentHistory = <?php echo json_encode($l->t('No assignment history'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.active = <?php echo json_encode($l->t('Active'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.ended = <?php echo json_encode($l->t('Ended'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.vacationDays = <?php echo json_encode($l->t('vacation days'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.loading = <?php echo json_encode($l->t('Loading'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.ongoing = <?php echo json_encode($l->t('ongoing'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.notAssigned = <?php echo json_encode($l->t('Not assigned'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.history = <?php echo json_encode($l->t('History'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.close = <?php echo json_encode($l->t('Close'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.workSchedule = <?php echo json_encode($l->t('Work schedule'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.vacationDaysCol = <?php echo json_encode($l->t('Vacation days'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.validFrom = <?php echo json_encode($l->t('Valid from'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.validTo = <?php echo json_encode($l->t('Valid to'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.status = <?php echo json_encode($l->t('Status'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.germanStateLabel = <?php echo json_encode($l->t('Region for public holidays'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.germanStateHelp = <?php echo json_encode($l->t('Select the region whose holiday calendar applies to this person. If not set, the instance default region is used.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.germanStateDefault = <?php echo json_encode($l->t('Instance default (currently: %s)', [$holidayRegionContextForJs['defaultRegionLabel']]), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.regionCrossBorderNote = <?php echo json_encode($l->t('Public holidays follow this region. Working time rules follow the country configured for the whole organisation.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.failedToLoadUserDetails = <?php echo json_encode($l->t('Failed to load user details'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.errorLoadingHistory = <?php echo json_encode($l->t('Error loading assignment history'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.ddmmYYYY = <?php echo json_encode($l->t('dd.mm.yyyy'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.loadingEllipsis = <?php echo json_encode($l->t('Loading…'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.errorLoadingUsers = <?php echo json_encode($l->t('Error loading users'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.failedToLoadUsersRetry = <?php echo json_encode($l->t('Failed to load users. Please try again.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.noUsersFound = <?php echo json_encode($l->t('No users found'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.enabled = <?php echo json_encode($l->t('Enabled'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.disabled = <?php echo json_encode($l->t('Disabled'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.actions = <?php echo json_encode($l->t('Actions'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.colName = <?php echo json_encode($l->t('Name'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.colEmail = <?php echo json_encode($l->t('Email'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.colValidFromTo = <?php echo json_encode($l->t('Valid from / to'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.colOvertimeStichtag = <?php echo json_encode($l->t('Overtime Stichtag'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.edit = <?php echo json_encode($l->t('Edit'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.invalidUserData = <?php echo json_encode($l->t('Invalid user data'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.failedToUpdateUser = <?php echo json_encode($l->t('Failed to update user'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.vacationMode = <?php echo json_encode($l->t('Vacation mode'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.vacationModeSimpleLabel = <?php echo json_encode($l->t('How should annual vacation be calculated?'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.manualFixed = <?php echo json_encode($l->t('Manual fixed'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.manualFixedSimple = <?php echo json_encode($l->t('Fixed value per person'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.modelBasedSimple = <?php echo json_encode($l->t('Model based'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.tariffRuleBased = <?php echo json_encode($l->t('Tariff rule based'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.manualException = <?php echo json_encode($l->t('Manual exception'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.manualExceptionSimple = <?php echo json_encode($l->t('Manual exception (with reason)'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.vacationModeHelp = <?php echo json_encode($l->t('Choose how annual entitlement is calculated for this person.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.vacationModeHelpSimple = <?php echo json_encode($l->t('Use fixed value, automatic from schedule, or tariff rule. Exception mode requires a reason.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.vacationModeInherit = <?php echo json_encode($l->t('Inherit from team / model / organisation'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.vacationModeHelpSimpleInherit = <?php echo json_encode($l->t('Inherit follows the deepest team policy, then the work-schedule default, then the organisation default. Fixed/automatic/tariff/exception set an individual rule for this employee.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.manualDays = <?php echo json_encode($l->t('Manual annual days'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.manualDaysHelp = <?php echo json_encode($l->t('Example: 30 or 24.5 days per year'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.tariffRuleSetId = <?php echo json_encode($l->t('Tariff rule set ID'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.tariffRuleSetLabel = <?php echo json_encode($l->t('Tariff rule set'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.tariffRuleSetHelp = <?php echo json_encode($l->t('Choose the active tariff rule set that should apply to this person.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.overrideReason = <?php echo json_encode($l->t('Override reason'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.effectiveEntitlement = <?php echo json_encode($l->t('Effective entitlement preview'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.quickSetupTitle = <?php echo json_encode($l->t('Quick setup in 3 steps'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.quickSetupStepWorkSchedule = <?php echo json_encode($l->t('Choose work schedule and state for holidays'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.quickSetupStepMode = <?php echo json_encode($l->t('Choose vacation calculation mode'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.quickSetupStepPreview = <?php echo json_encode($l->t('Check preview, then save'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.previewTraceManual = <?php echo json_encode($l->t('Uses manually entered annual days.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.previewTraceModel = <?php echo json_encode($l->t('Formula: 30 × (work days per week ÷ 5).'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.previewTraceError = <?php echo json_encode($l->t('Preview unavailable.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.previewTechnicalDetails = <?php echo json_encode($l->t('Technical details (audit)'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.previewResolvedByLayer = <?php echo json_encode($l->t('Determined by: {layer}.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.previewDegradedHint = <?php echo json_encode($l->t('Resolution ran in a degraded state — open technical details or check layered vacation settings.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.organisationDefault = <?php echo json_encode($l->t('Organisation default'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.workingTimeModelDefault = <?php echo json_encode($l->t('Working time model default'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.teamPolicy = <?php echo json_encode($l->t('Team policy'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.individualRule = <?php echo json_encode($l->t('Individual rule'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.legacyFallback = <?php echo json_encode($l->t('Legacy fallback (25 d.)'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.formatDdmmyyyy = <?php echo json_encode($l->t('Format: dd.mm.yyyy'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.invalidDateDdmmyyyy = <?php echo json_encode($l->t('Please enter a valid date (dd.mm.yyyy).'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.employmentPeriod = <?php echo json_encode($l->t('Employment period (for pro-rata vacation)'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.employmentPeriodIntro = <?php echo json_encode($l->t('Set the hire date (and leaving date, if any). When the employment does not cover the whole calendar year, the annual vacation entitlement is reduced proportionally. Leave both empty for the full annual entitlement.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.employmentStart = <?php echo json_encode($l->t('Employment start date (Eintrittsdatum)'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.employmentStartHelp = <?php echo json_encode($l->t('First day of employment. Vacation for the year of hire is prorated from this date.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.employmentEnd = <?php echo json_encode($l->t('Employment end date (Austrittsdatum)'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.employmentEndHelp = <?php echo json_encode($l->t('Last day of employment. Leave empty for ongoing employment. Vacation for the year of leaving is prorated up to this date.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.employmentEndAfterStart = <?php echo json_encode($l->t('The employment end date must be on or after the employment start date.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.entitlementProratedTwelfths = <?php echo json_encode($l->t('Prorated for partial year: {prorated} of {full} days (employed {months} of 12 months).'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.entitlementProratedDaily = <?php echo json_encode($l->t('Prorated for partial year: {prorated} of {full} days (daily method).'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.entitlementNotEmployedThisYear = <?php echo json_encode($l->t('No entitlement this year: the employment period does not cover this calendar year.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.previewSelectTariffRuleSet = <?php echo json_encode($l->t('Select a tariff rule set to see the preview.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.notAvailable = <?php echo json_encode($l->t('Not available'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.sourceManual = <?php echo json_encode($l->t('Manual'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.sourceManualException = <?php echo json_encode($l->t('Manual exception'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.sourceSimpleModel = <?php echo json_encode($l->t('Model based'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.sourceTariff = <?php echo json_encode($l->t('Tariff'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.showingEmployees = <?php echo json_encode(
        TemplateL10n::translate($l, 'Showing {shown} of {total} employees'),
        TemplateL10n::JSON_ENCODE_FLAGS
    ); ?>;
    window.ArbeitszeitCheck.l10n.showingEmployeesRange = <?php echo json_encode(
        TemplateL10n::translate($l, 'Showing employees {from}–{to} of {total}'),
        TemplateL10n::JSON_ENCODE_FLAGS
    ); ?>;
    window.ArbeitszeitCheck.l10n.searchMatches = <?php echo json_encode(
        TemplateL10n::translate($l, '{count} employees match your search'),
        TemplateL10n::JSON_ENCODE_FLAGS
    ); ?>;
    window.ArbeitszeitCheck.l10n.searchRefineHint = <?php echo json_encode(
        TemplateL10n::translate($l, 'More than {count} matches — refine your search to find a specific person.'),
        TemplateL10n::JSON_ENCODE_FLAGS
    ); ?>;
    window.ArbeitszeitCheck.l10n.searchMinLength = <?php echo json_encode(
        TemplateL10n::translate($l, 'Type at least 2 characters to search.'),
        TemplateL10n::JSON_ENCODE_FLAGS
    ); ?>;
    window.ArbeitszeitCheck.l10n.notSet = <?php echo json_encode($l->t('Not set'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    window.ArbeitszeitCheck.l10n.backToEmployees = <?php echo json_encode($l->t('Back to employees'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.employeeProfile = <?php echo json_encode($l->t('Employee profile'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.howToEditTitle = <?php echo json_encode($l->t('How to edit this employee'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.howToEditIntro = <?php echo json_encode($l->t('Go through each section below. Open a section heading for a short explanation. When you are done, press Save at the bottom.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.sectionGuideWorkSchedule = <?php echo json_encode($l->t('Pick the work schedule and the region used for public holidays.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.sectionGuideTimeRecording = <?php echo json_encode($l->t('Choose whether this person may clock in/out and/or enter time manually. At least one method must stay on.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.sectionGuideVacation = <?php echo json_encode($l->t('Set how annual vacation is calculated, then check the preview before saving.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.sectionGuideOvertime = <?php echo json_encode($l->t('Optional: set the overtime start date (Stichtag) and an opening balance for a calendar year.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.sectionGuideValidity = <?php echo json_encode($l->t('When this work-schedule assignment starts and (optionally) ends.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.sectionGuideEmployment = <?php echo json_encode($l->t('Hire and leaving dates used to reduce vacation in partial years. Leave empty for a full-year entitlement.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.sectionGuideHistory = <?php echo json_encode($l->t('Read-only list of past and current work-schedule assignments.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.unsavedChanges = <?php echo json_encode($l->t('You have unsaved changes. Leave this page anyway?'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.saveChanges = <?php echo json_encode($l->t('Save changes'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.discardAndBack = <?php echo json_encode($l->t('Back without saving'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.discardUnsavedConfirm = <?php echo json_encode($l->t('You have unsaved changes. Leave this page without saving?'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.userIdLabel = <?php echo json_encode($l->t('User ID'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.openSectionHelp = <?php echo json_encode($l->t('Show explanation'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.jumpToSection = <?php echo json_encode($l->t('Jump to section'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.historySectionTitle = <?php echo json_encode($l->t('Work schedule history'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.editEmployee = <?php echo json_encode($l->t('Edit employee'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.viewHistory = <?php echo json_encode($l->t('View history'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    window.ArbeitszeitCheck.l10n.overtimeSettings = <?php echo json_encode($l->t('Overtime balance'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.overtimeTrackingFrom = <?php echo json_encode($l->t('Overtime tracking from (Stichtag)'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.overtimeTrackingFromHelp = <?php echo json_encode($l->t('Leave empty for legacy calculation from 1 January. When set, year-to-date overtime counts only from this date.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.overtimeOpeningBalance = <?php echo json_encode($l->t('Opening overtime balance (hours)'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.overtimeOpeningBalanceHelp = <?php echo json_encode($l->t('Eröffnungssaldo in hours for the selected year (can be negative).'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.overtimeOpeningBalanceYear = <?php echo json_encode($l->t('Year for opening balance'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.yearFourDigitsHelp = <?php echo json_encode($l->t('Enter a four-digit year (e.g. 2026).'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.timeRecordingMethods = <?php echo json_encode($l->t('Time recording'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.timeRecordingMethodsIntro = <?php echo json_encode($l->t('Choose how this employee may record working time. At least one method must stay enabled.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.timeRecordingOrgRestrictionNote = <?php echo json_encode($l->t('Greyed-out options are disabled organisation-wide in Global settings. You can only restrict this person further.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.clockStampingLabel = <?php echo json_encode($l->t('Clock in / out (stamping)'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.clockStampingHelp = <?php echo json_encode($l->t('Live punch clock on the dashboard and in the mobile app.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.manualTimeEntryLabel = <?php echo json_encode($l->t('Manual time entries'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.manualTimeEntryHelp = <?php echo json_encode($l->t('Add completed work blocks by date and time in the web app.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.timeCaptureAtLeastOne = <?php echo json_encode($l->t('Enable clock in/out or manual time entries — at least one method is required.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.saving = <?php echo json_encode($l->t('Saving…'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.formHasErrors = <?php echo json_encode($l->t('Please correct the highlighted fields and try again.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.manualDaysRequired = <?php echo json_encode($l->t('Enter the annual vacation days (e.g. 30 or 24.5).'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.manualDaysRange = <?php echo json_encode($l->t('Vacation days must be between 0 and 366.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.overrideReasonRequired = <?php echo json_encode($l->t('A reason is required for a manual exception.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.tariffRuleSetRequired = <?php echo json_encode($l->t('Select a tariff rule set.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.vacationDaysRange = <?php echo json_encode($l->t('Vacation days per year must be between 0 and 365.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.carryoverRange = <?php echo json_encode($l->t('Carryover must be a number between 0 and 366.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.yearRange2000 = <?php echo json_encode($l->t('Year must be between 2000 and 2100.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.openingBalanceYearRange = <?php echo json_encode($l->t('Opening balance year must be between 2000 and 2100.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.openingBalanceHoursRange = <?php echo json_encode($l->t('Opening balance hours must be a number between -9999 and 9999.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.endDateAfterStart = <?php echo json_encode($l->t('The end date must be on or after the start date.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.l10n.vacationCarryoverHelpDecimals = <?php echo json_encode($l->t('Up to two decimal places are allowed, e.g. 1.5 or 4.25 — comma or dot both work.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.states = <?php echo json_encode($holidayStatesForJs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.regionGroups = <?php echo json_encode($holidayRegionGroupsForJs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.ArbeitszeitCheck.holidayRegionContext = <?php echo json_encode($holidayRegionContextForJs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
